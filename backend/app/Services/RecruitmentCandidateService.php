<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\Cadet;
use App\Models\District;
use App\Models\Province;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\User;
use App\Support\GeographicHierarchyValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecruitmentCandidateService
{
    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, province_id?: int|null, district_id?: int|null}  $filters
     */
    public function paginate(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = RecruitmentCandidate::query()
            ->with(['province', 'district']);

        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::Province && $actor->province_id) {
            $query->where('province_id', $actor->province_id);
        } elseif ($scope === AccessScopeType::District && $actor->district_id) {
            $query->where('district_id', $actor->district_id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($candidateQuery) => $candidateQuery
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('contact_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (! empty($filters['status'])) {
            $query->where('recruitment_status', $filters['status']);
        }

        if (! empty($filters['province_id'])) {
            $query->where('province_id', $filters['province_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }

        return $query->latest()->paginate(15);
    }

    /**
     * @return array{imported: int, duplicates: int, skipped: int, warnings: array<int, string>}
     */
    public function importCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['The import file could not be read.']]);
        }
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['The CSV file is empty.']]);
        }

        $headerMap = array_map(fn ($header) => $this->canonicalHeader((string) $header), $headers);
        $results = ['imported' => 0, 'duplicates' => 0, 'skipped' => 0, 'warnings' => []];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $values = $this->rowValues($row, $headerMap);
            $phone = $this->normalizePhone($values['contact_number']);
            if ($values['full_name'] === '' || $phone === '') {
                $results['skipped']++;
                $results['warnings'][] = "Row {$rowNumber}: skipped because name or contact number is missing.";

                continue;
            }
            if (RecruitmentCandidate::where('contact_number', $phone)->exists() || User::where('phone', $phone)->exists()) {
                $results['duplicates']++;
                $results['warnings'][] = "Row {$rowNumber}: {$phone} already exists in recruitment or user records.";

                continue;
            }
            [$province, $district, $warning] = $this->matchGeography($values['province'], $values['district']);
            if ($warning !== null) {
                $results['warnings'][] = "Row {$rowNumber}: {$warning}";
            }
            RecruitmentCandidate::create([
                'full_name' => $values['full_name'], 'gender' => $values['gender'] ?: null,
                'province_id' => $province?->id, 'district_id' => $district?->id,
                'local_level' => $values['local_level'] ?: null,
                'ward_number' => is_numeric($values['ward_number']) ? (int) $values['ward_number'] : null,
                'contact_number' => $phone, 'email' => $values['email'] ?: null,
                'skills' => $this->skillsFromText($values['skills']),
                'recruitment_status' => RecruitmentCandidate::STATUS_IMPORTED,
            ]);
            $results['imported']++;
        }
        fclose($handle);

        return $results;
    }

    /** @param array{recruitment_status?: string, notes?: string|null} $data */
    public function update(User $actor, RecruitmentCandidate $candidate, array $data): RecruitmentCandidate
    {
        $this->assertCanManage($actor, $candidate);

        $old = $candidate->only(['recruitment_status', 'notes']);

        if (($data['recruitment_status'] ?? null) === RecruitmentCandidate::STATUS_OUTREACH_SENT) {
            $data['outreach_sent_at'] = now();
        }
        $candidate->update($data);

        $this->auditLogger->record($actor, 'updated_candidate', $candidate, oldValues: $old, newValues: $candidate->only(array_keys($old)));

        return $candidate->fresh(['province', 'district']);
    }

    /** @param array{cadet_number: string, rank_id: int, username: string, password: string} $data */
    public function promote(User $actor, RecruitmentCandidate $candidate, array $data): RecruitmentCandidate
    {
        $this->assertCanManage($actor, $candidate);

        if ($candidate->recruitment_status === RecruitmentCandidate::STATUS_CONVERTED) {
            throw ValidationException::withMessages(['candidate' => ['This candidate has already been promoted.']]);
        }

        if (User::where('cadet_number', $data['cadet_number'])->exists() || Cadet::where('cadet_number', $data['cadet_number'])->exists()) {
            throw ValidationException::withMessages([
                'cadet_number' => ['The cadet number has already been taken.'],
            ]);
        }

        if (User::where('username', $data['username'])->exists()) {
            throw ValidationException::withMessages([
                'username' => ['The username has already been taken.'],
            ]);
        }

        GeographicHierarchyValidator::validate([
            'province_id' => $candidate->province_id,
            'district_id' => $candidate->district_id,
        ]);

        return DB::transaction(function () use ($actor, $candidate, $data): RecruitmentCandidate {
            $user = User::create([
                'name' => $candidate->full_name,
                'username' => $data['username'],
                'email' => $candidate->email ?: 'candidate-'.Str::lower(Str::random(12)).'@nccaa.local',
                'phone' => $candidate->contact_number,
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'cadet_number' => $data['cadet_number'],
                'rank_id' => $data['rank_id'],
                'province_id' => $candidate->province_id,
                'district_id' => $candidate->district_id,
                'local_level' => $candidate->local_level,
                'ward_number' => $candidate->ward_number,
            ]);

            $cadetRole = Role::where('slug', Role::CADET)->first();
            if ($cadetRole) {
                $user->roles()->sync([$cadetRole->id]);
            }

            $cadet = Cadet::create([
                'cadet_number' => $data['cadet_number'],
                'name' => $candidate->full_name,
                'rank_id' => $data['rank_id'],
                'province_id' => $candidate->province_id,
                'district_id' => $candidate->district_id,
                'email' => $candidate->email,
                'phone' => $candidate->contact_number,
                'status' => 'active',
                'user_id' => $user->id,
            ]);

            $candidate->update([
                'recruitment_status' => RecruitmentCandidate::STATUS_CONVERTED,
                'converted_user_id' => $user->id,
                'converted_at' => now(),
            ]);

            $this->auditLogger->record($actor, 'promoted_to_cadet', $candidate, null, null, [
                'user_id' => $user->id,
                'cadet_id' => $cadet->id,
                'cadet_number' => $data['cadet_number'],
            ]);

            return $candidate->fresh(['province', 'district', 'convertedUser']);
        });
    }

    /**
     * Throw if the actor lacks scope over the given candidate.
     */
    protected function assertCanManage(User $actor, RecruitmentCandidate $candidate): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        if ($scope === AccessScopeType::Province && $candidate->province_id === $actor->province_id) {
            return;
        }

        if ($scope === AccessScopeType::District && $candidate->district_id === $actor->district_id) {
            return;
        }

        throw ValidationException::withMessages([
            'candidate' => ['You do not have permission to manage this candidate.'],
        ]);
    }

    /** @param array<int, string|null> $row @param array<int, string> $headerMap @return array<string, string> */
    private function rowValues(array $row, array $headerMap): array
    {
        $values = array_fill_keys(['full_name', 'gender', 'province', 'district', 'local_level', 'ward_number', 'contact_number', 'email', 'skills'], '');
        foreach ($row as $index => $value) {
            $key = $headerMap[$index] ?? null;
            if ($key !== null && array_key_exists($key, $values)) {
                $values[$key] = trim((string) $value);
            }
        }

        return $values;
    }

    private function canonicalHeader(string $header): string
    {
        $header = Str::lower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? ''));

        return [
            'तपाईंको नाम-थर (full name)' => 'full_name', 'full name' => 'full_name', 'name' => 'full_name',
            'लिङ्ग (gender)' => 'gender', 'gender' => 'gender', 'प्रदेश (province)' => 'province', 'province' => 'province',
            'जिल्ला (district)' => 'district', 'district' => 'district', 'local level' => 'local_level', 'पालिका' => 'local_level',
            'ward' => 'ward_number', 'ward number' => 'ward_number', 'वडा नम्बर' => 'ward_number',
            'सम्पर्क नम्बर (contact number)' => 'contact_number', 'contact number' => 'contact_number', 'phone' => 'contact_number',
            'इ-मेल (e-mail)' => 'email', 'e-mail' => 'email', 'email' => 'email', 'skills' => 'skills', 'सीपहरू' => 'skills', 'skill' => 'skills',
        ][$header] ?? $header;
    }

    /** @return array{0: Province|null, 1: District|null, 2: string|null} */
    private function matchGeography(string $provinceName, string $districtName): array
    {
        $province = $this->findLocation(Province::query(), $provinceName);
        $district = $this->findLocation(District::query(), $districtName, $province?->id);
        $warnings = [];
        if ($provinceName !== '' && $province === null) {
            $warnings[] = "province '{$provinceName}' was not matched";
        }
        if ($districtName !== '' && $district === null) {
            $warnings[] = "district '{$districtName}' was not matched";
        }

        return [$province, $district, $warnings ? implode('; ', $warnings) : null];
    }

    private function findLocation($query, string $name, ?int $provinceId = null): mixed
    {
        if ($name === '') {
            return null;
        }
        if ($provinceId !== null) {
            $query->where('province_id', $provinceId);
        }
        $normalized = Str::lower(trim(Str::replace([' province', ' pradesh'], '', $name)));

        return $query->get()->first(fn ($model) => in_array($normalized, [Str::lower($model->name_en), Str::lower((string) $model->name_ne)], true));
    }

    /** @return array<int, string> */
    private function skillsFromText(string $skills): array
    {
        return collect(preg_split('/[,;|\n]+/', $skills) ?: [])->map(fn ($skill) => trim($skill))->filter()->values()->all();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+|-/', '', trim($phone)) ?? '';
    }
}
