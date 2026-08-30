<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\Cadet;
use App\Models\CadetProfile;
use App\Models\District;
use App\Models\Province;
use App\Models\Rank;
use App\Models\Role;
use App\Models\User;
use App\Support\GeographicHierarchyValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CadetImportService
{
    public const MAX_ROWS = 2500;

    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Parse and inspect the spreadsheet headers and return sample preview.
     *
     * @return array{headers: array<int, string>, detected_mapping: array<string, string>, sample_rows: array<int, array<string, mixed>>}
     */
    public function inspect(UploadedFile $file): array
    {
        $this->validateFile($file);

        $spreadsheet = $this->loadSpreadsheet($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows) || count($rows) < 1) {
            throw ValidationException::withMessages(['file' => ['The spreadsheet contains no data.']]);
        }

        $headers = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $detectedMapping = $this->detectMapping($headers);

        $sampleRows = [];
        for ($i = 1; $i < min(6, count($rows)); $i++) {
            if (! empty(array_filter($rows[$i]))) {
                $rowAssoc = [];
                foreach ($headers as $idx => $header) {
                    $rowAssoc[$header ?: "Column_{$idx}"] = $rows[$i][$idx] ?? null;
                }
                $sampleRows[] = $rowAssoc;
            }
        }

        return [
            'headers' => array_values(array_filter($headers)),
            'detected_mapping' => $detectedMapping,
            'sample_rows' => $sampleRows,
        ];
    }

    /**
     * Parse spreadsheet, map columns, validate rows, resolve references, and return preview.
     *
     * @param  array<string, string>  $customMapping
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public function preview(UploadedFile $file, array $customMapping, User $actor): array
    {
        $this->validateFile($file);

        $spreadsheet = $this->loadSpreadsheet($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rawRows = $worksheet->toArray(null, true, true, false);

        if (count($rawRows) < 2) {
            throw ValidationException::withMessages(['file' => ['The spreadsheet contains no data rows.']]);
        }

        $headers = array_map(fn ($h) => trim((string) $h), $rawRows[0]);
        $mapping = array_merge($this->detectMapping($headers), array_filter($customMapping));

        $headerIndexMap = [];
        foreach ($headers as $idx => $header) {
            if ($header !== '') {
                $headerIndexMap[$header] = $idx;
            }
        }

        $ranks = Rank::all();
        $provinces = Province::all();
        $districts = District::all();

        $seenCadetNumbers = [];
        $seenEmails = [];
        $seenPhones = [];

        $existingCadetNumbers = Cadet::pluck('cadet_number')->flip()->all() + User::whereNotNull('cadet_number')->pluck('cadet_number')->flip()->all();
        $existingEmails = User::pluck('email')->flip()->all() + Cadet::whereNotNull('email')->pluck('email')->flip()->all();
        $existingPhones = User::whereNotNull('phone')->pluck('phone')->flip()->all() + Cadet::whereNotNull('phone')->pluck('phone')->flip()->all();

        $rows = [];
        $summary = [
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicates' => 0,
            'valid_emails' => 0,
            'missing_emails' => 0,
            'invalid_emails' => 0,
            'duplicate_emails' => 0,
        ];

        $scope = $this->accessScope->resolve($actor);
        $scopedGeography = $this->accessScope->scopedGeography($actor);

        $rowCount = count($rawRows);
        if ($rowCount - 1 > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => ['The spreadsheet exceeds the maximum allowed limit of '.self::MAX_ROWS.' rows.']]);
        }

        for ($i = 1; $i < $rowCount; $i++) {
            $rawRow = $rawRows[$i];
            if (empty(array_filter($rawRow, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue; // Skip empty row
            }

            $summary['total_rows']++;
            $errors = [];
            $warnings = [];

            $rowVal = function (string $field) use ($mapping, $headerIndexMap, $rawRow): string {
                $headerName = $mapping[$field] ?? null;
                if ($headerName && isset($headerIndexMap[$headerName])) {
                    return trim((string) ($rawRow[$headerIndexMap[$headerName]] ?? ''));
                }

                return '';
            };

            $cadetNumber = strtoupper($rowVal('cadet_number'));
            $name = $rowVal('name');
            $rawEmail = strtolower($rowVal('email'));
            $phone = preg_replace('/\s+|-/', '', $rowVal('phone'));
            $rankStr = $rowVal('rank');
            $provinceStr = $rowVal('province');
            $districtStr = $rowVal('district');
            $localLevelStr = $rowVal('local_level');
            $wardStr = $rowVal('ward_number');

            // Name validation
            if ($name === '') {
                $errors[] = 'Full name is required.';
            }

            // Cadet number validation
            if ($cadetNumber === '') {
                $errors[] = 'Cadet number is required.';
            } elseif (! preg_match('/^[A-Za-z0-9\-\/]+$/', $cadetNumber)) {
                $errors[] = 'Cadet number contains invalid characters.';
            } elseif (isset($seenCadetNumbers[$cadetNumber])) {
                $errors[] = 'Duplicate cadet number in this file.';
                $summary['duplicates']++;
            } elseif (isset($existingCadetNumbers[$cadetNumber])) {
                $errors[] = 'Cadet number already exists in database.';
                $summary['duplicates']++;
            } else {
                $seenCadetNumbers[$cadetNumber] = true;
            }

            // Email validation & classification
            $emailStatus = 'MISSING_EMAIL';
            if ($rawEmail === '') {
                $summary['missing_emails']++;
                $warnings[] = 'Email is missing.';
            } elseif (! filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                $emailStatus = 'INVALID_EMAIL';
                $summary['invalid_emails']++;
                $warnings[] = "Invalid email format ({$rawEmail}).";
            } elseif (isset($seenEmails[$rawEmail])) {
                $emailStatus = 'DUPLICATE_EMAIL';
                $summary['duplicate_emails']++;
                $warnings[] = "Duplicate email in this file ({$rawEmail}).";
            } elseif (isset($existingEmails[$rawEmail])) {
                $emailStatus = 'EXISTING_USER';
                $warnings[] = "Email already registered in system ({$rawEmail}).";
            } else {
                $emailStatus = 'VALID_EMAIL';
                $summary['valid_emails']++;
                $seenEmails[$rawEmail] = true;
            }

            // Phone duplicate check
            if ($phone !== '') {
                if (isset($seenPhones[$phone])) {
                    $warnings[] = "Duplicate phone in file ({$phone}).";
                } elseif (isset($existingPhones[$phone])) {
                    $warnings[] = "Phone already exists in database ({$phone}).";
                } else {
                    $seenPhones[$phone] = true;
                }
            }

            // Rank resolution
            $matchedRank = null;
            if ($rankStr !== '') {
                $matchedRank = $this->resolveRank($rankStr, $ranks);
                if (! $matchedRank) {
                    $warnings[] = "Rank '{$rankStr}' could not be matched.";
                }
            }

            // Geography resolution
            $matchedProvince = null;
            if ($provinceStr !== '') {
                $matchedProvince = $this->resolveProvince($provinceStr, $provinces);
                if (! $matchedProvince) {
                    $warnings[] = "Province '{$provinceStr}' could not be matched.";
                }
            }

            $matchedDistrict = null;
            if ($districtStr !== '') {
                $matchedDistrict = $this->resolveDistrict($districtStr, $districts, $matchedProvince?->id);
                if (! $matchedDistrict) {
                    $warnings[] = "District '{$districtStr}' could not be matched.";
                }
            }

            // Check Geographic Hierarchy
            if ($matchedProvince && $matchedDistrict && $matchedDistrict->province_id !== $matchedProvince->id) {
                $errors[] = "District '{$matchedDistrict->name_en}' does not belong to Province '{$matchedProvince->name_en}'.";
            }

            // Geographic Scope Authorization Check for Actor
            if ($scope === AccessScopeType::Province && $matchedProvince) {
                if ($matchedProvince->id !== $actor->province_id) {
                    $errors[] = 'Record province is outside your administrative scope.';
                }
            } elseif ($scope === AccessScopeType::District && $matchedDistrict) {
                if ($matchedDistrict->id !== $actor->district_id) {
                    $errors[] = 'Record district is outside your administrative scope.';
                }
            }

            $isValid = empty($errors);
            if ($isValid) {
                $summary['valid_rows']++;
            } else {
                $summary['invalid_rows']++;
            }

            $rows[] = [
                'row_index' => $i + 1,
                'cadet_number' => $cadetNumber,
                'name' => $name,
                'email' => $rawEmail ?: null,
                'email_status' => $emailStatus,
                'phone' => $phone ?: null,
                'rank_id' => $matchedRank?->id,
                'rank_name' => $matchedRank?->name_en ?? ($rankStr ?: null),
                'province_id' => $matchedProvince?->id,
                'province_name' => $matchedProvince?->name_en ?? ($provinceStr ?: null),
                'district_id' => $matchedDistrict?->id,
                'district_name' => $matchedDistrict?->name_en ?? ($districtStr ?: null),
                'local_level' => $localLevelStr ?: null,
                'ward_number' => is_numeric($wardStr) ? (int) $wardStr : null,
                'gender' => $rowVal('gender') ?: null,
                'blood_group' => $rowVal('blood_group') ?: null,
                'is_valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * Commit the validated rows into database with user and cadet records.
     *
     * @param  array<int, array<string, mixed>>  $approvedRows
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function commit(User $actor, array $approvedRows): array
    {
        $scope = $this->accessScope->resolve($actor);
        $scopedGeography = $this->accessScope->scopedGeography($actor);

        $cadetRole = Role::firstOrCreate(['slug' => Role::CADET], ['name' => 'Cadet']);

        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($approvedRows as $row) {
            $cadetNumber = strtoupper(trim((string) ($row['cadet_number'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));

            if ($cadetNumber === '' || $name === '') {
                $results['skipped']++;

                continue;
            }

            // Check duplicate in database
            if (Cadet::where('cadet_number', $cadetNumber)->exists() || User::where('cadet_number', $cadetNumber)->exists()) {
                $results['skipped']++;
                $results['errors'][] = "Cadet number {$cadetNumber} already exists in database.";

                continue;
            }

            $provinceId = ! empty($row['province_id']) ? (int) $row['province_id'] : null;
            $districtId = ! empty($row['district_id']) ? (int) $row['district_id'] : null;

            // Scope verification
            if ($scope === AccessScopeType::Province && $provinceId && $provinceId !== $actor->province_id) {
                $results['skipped']++;
                $results['errors'][] = "Row with cadet {$cadetNumber} is outside your province scope.";

                continue;
            }
            if ($scope === AccessScopeType::District && $districtId && $districtId !== $actor->district_id) {
                $results['skipped']++;
                $results['errors'][] = "Row with cadet {$cadetNumber} is outside your district scope.";

                continue;
            }

            // Hierarchy verification
            if ($provinceId && $districtId) {
                try {
                    GeographicHierarchyValidator::validate([
                        'province_id' => $provinceId,
                        'district_id' => $districtId,
                    ]);
                } catch (\Throwable $e) {
                    $results['skipped']++;
                    $results['errors'][] = "Row {$cadetNumber}: district does not belong to province.";

                    continue;
                }
            }

            try {
                DB::transaction(function () use ($row, $cadetNumber, $name, $provinceId, $districtId, $cadetRole): void {
                    $email = ! empty($row['email']) ? strtolower(trim((string) $row['email'])) : null;
                    if ($email && User::where('email', $email)->exists()) {
                        $email = null; // Prevent unique constraint collision if user email already exists
                    }

                    $phone = ! empty($row['phone']) ? preg_replace('/\s+|-/', '', (string) $row['phone']) : null;
                    if ($phone && User::where('phone', $phone)->exists()) {
                        $phone = null;
                    }

                    $username = 'cadet.'.Str::lower(Str::random(10));
                    $user = User::create([
                        'name' => $name,
                        'username' => $username,
                        'email' => $email ?: 'cadet-'.Str::lower(Str::random(12)).'@nccaa.local',
                        'phone' => $phone,
                        'password' => Hash::make(Str::random(16)),
                        'status' => 'active',
                        'cadet_number' => $cadetNumber,
                        'rank_id' => ! empty($row['rank_id']) ? (int) $row['rank_id'] : null,
                        'province_id' => $provinceId,
                        'district_id' => $districtId,
                        'local_level' => $row['local_level'] ?? null,
                        'ward_number' => ! empty($row['ward_number']) ? (int) $row['ward_number'] : null,
                    ]);

                    $user->roles()->syncWithoutDetaching([$cadetRole->id]);

                    $cadet = Cadet::create([
                        'cadet_number' => $cadetNumber,
                        'name' => $name,
                        'rank_id' => ! empty($row['rank_id']) ? (int) $row['rank_id'] : null,
                        'province_id' => $provinceId,
                        'district_id' => $districtId,
                        'email' => $email,
                        'phone' => $phone,
                        'status' => 'active',
                        'user_id' => $user->id,
                    ]);

                    if (! empty($row['gender']) || ! empty($row['blood_group'])) {
                        CadetProfile::create([
                            'cadet_id' => $cadet->id,
                            'gender' => $row['gender'] ?? null,
                            'blood_group' => $row['blood_group'] ?? null,
                        ]);
                    }
                });

                $results['imported']++;
            } catch (\Throwable $e) {
                $results['skipped']++;
                $results['errors'][] = "Row {$cadetNumber} failed to import: ".$e->getMessage();
            }
        }

        $this->auditLogger->record($actor, 'cadets_excel_imported', $actor, null, null, [
            'imported' => $results['imported'],
            'skipped' => $results['skipped'],
        ]);

        return $results;
    }

    private function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw ValidationException::withMessages(['file' => ['Only .xlsx, .xls, and .csv files are supported.']]);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => ['File exceeds the maximum size limit of 10MB.']]);
        }
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $path = $file->getRealPath();
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    private function detectMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $norm = Str::lower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? ''));
            $norm = preg_replace('/[_\-\s]+/', ' ', $norm) ?? '';

            if (in_array($norm, ['cadet no', 'cadet number', 'cadet id', 'cadet_no', 'cadet_number', 'roll no', 'दर्ता नं'], true)) {
                $mapping['cadet_number'] = $header;
            } elseif (in_array($norm, ['name', 'full name', 'cadet name', 'नाम', 'नाम थर'], true)) {
                $mapping['name'] = $header;
            } elseif (in_array($norm, ['email', 'e mail', 'email address', 'इमेल', 'इ मेल'], true)) {
                $mapping['email'] = $header;
            } elseif (in_array($norm, ['phone', 'contact', 'mobile', 'phone number', 'contact number', 'सम्पर्क'], true)) {
                $mapping['phone'] = $header;
            } elseif (in_array($norm, ['rank', 'rank name', 'दर्जा'], true)) {
                $mapping['rank'] = $header;
            } elseif (in_array($norm, ['province', 'state', 'प्रदेश'], true)) {
                $mapping['province'] = $header;
            } elseif (in_array($norm, ['district', 'जिल्ला'], true)) {
                $mapping['district'] = $header;
            } elseif (in_array($norm, ['local level', 'municipality', 'gaupalika', 'nagarpalika', 'पालिका'], true)) {
                $mapping['local_level'] = $header;
            } elseif (in_array($norm, ['ward', 'ward no', 'ward number', 'वडा'], true)) {
                $mapping['ward_number'] = $header;
            } elseif (in_array($norm, ['gender', 'sex', 'लिङ्ग'], true)) {
                $mapping['gender'] = $header;
            } elseif (in_array($norm, ['blood group', 'blood', 'रक्त समूह'], true)) {
                $mapping['blood_group'] = $header;
            }
        }

        return $mapping;
    }

    private function resolveRank(string $rankStr, $ranks): ?Rank
    {
        $normalized = Str::lower(trim($rankStr));

        return $ranks->first(function ($r) use ($normalized) {
            return Str::lower($r->short_code) === $normalized
                || Str::lower($r->name_en) === $normalized
                || Str::lower((string) $r->name_ne) === $normalized;
        });
    }

    private function resolveProvince(string $provinceStr, $provinces): ?Province
    {
        $normalized = Str::lower(trim(Str::replace([' province', ' pradesh'], '', $provinceStr)));

        return $provinces->first(function ($p) use ($normalized) {
            return Str::lower($p->name_en) === $normalized
                || Str::lower((string) $p->name_ne) === $normalized
                || Str::lower($p->code) === $normalized;
        });
    }

    private function resolveDistrict(string $districtStr, $districts, ?int $provinceId): ?District
    {
        $normalized = Str::lower(trim($districtStr));

        return $districts->first(function ($d) use ($normalized, $provinceId) {
            if ($provinceId !== null && $d->province_id !== $provinceId) {
                return false;
            }

            return Str::lower($d->name_en) === $normalized
                || Str::lower((string) $d->name_ne) === $normalized
                || Str::lower($d->code) === $normalized;
        });
    }
}
