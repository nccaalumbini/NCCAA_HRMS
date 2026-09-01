<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use App\Models\RecruitmentCandidate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecruitmentCandidateSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('app/cadetsdata/cadets.json');

        if (! is_file($path)) {
            throw new RuntimeException("Recruitment source file not found: {$path}");
        }

        $records = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $provinces = Province::all();
        $districts = District::all();
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($records, $provinces, $districts, &$imported, &$skipped): void {
            foreach ($records as $record) {
                $name = $this->value($record, 'तपाईंको नाम-थर (Full Name)');
                $phone = $this->normalizePhone($this->value($record, 'सम्पर्क नम्बर (Contact Number)'));

                if ($name === '' || $phone === '') {
                    $skipped++;

                    continue;
                }

                if (RecruitmentCandidate::where('contact_number', $phone)->exists()) {
                    $skipped++;

                    continue;
                }

                $provinceName = $this->value($record, 'प्रदेश (Province)');
                $districtName = $this->value($record, 'जिल्ला (District)');
                $province = $this->findProvince($provinces, $provinceName);
                $district = $this->findDistrict($districts, $districtName, $province?->id);

                RecruitmentCandidate::create([
                    'full_name' => $name,
                    'gender' => $this->value($record, 'लिङ्ग (Gender)') ?: null,
                    'province_id' => $province?->id,
                    'district_id' => $district?->id,
                    'contact_number' => $phone,
                    'email' => $this->normalizeEmail($this->value($record, 'इ-मेल (E-Mail)')),
                    'skills' => $this->skills($record),
                    'source' => 'cadets_json_import',
                    'recruitment_status' => RecruitmentCandidate::STATUS_IMPORTED,
                    'priority_score' => $this->priorityScore($record),
                ]);
                $imported++;
            }
        });

        $this->command?->info("Imported {$imported} recruitment candidates; skipped {$skipped} blank or duplicate records.");
    }

    private function value(array $record, string $key): string
    {
        return trim((string) ($record[$key] ?? ''));
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[\s-]+/', '', $phone) ?? '';
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? Str::lower($email) : null;
    }

    /** @return array<int, string> */
    private function skills(array $record): array
    {
        $skills = [];
        $source = $this->value($record, 'दक्षता (Skills)');

        foreach (preg_split('/[,;|\n]+/', $source) ?: [] as $skill) {
            $skill = trim($skill);
            if ($skill !== '') {
                $skills[] = $skill;
            }
        }

        return array_values(array_unique($skills));
    }

    private function priorityScore(array $record): int
    {
        $score = count($this->skills($record)) * 10;
        $score += $this->value($record, 'इ-मेल (E-Mail)') !== '' ? 10 : 0;
        $score += $this->value($record, 'सम्पर्क नम्बर (Contact Number)') !== '' ? 10 : 0;
        $score += $this->value($record, 'प्रदेश (Province)') !== '' ? 5 : 0;
        $score += $this->value($record, 'जिल्ला (District)') !== '' ? 5 : 0;

        return $score;
    }

    private function findProvince($provinces, string $name): ?Province
    {
        $normalized = $this->normalizeLocation($name);

        return $provinces->first(fn (Province $province): bool => $normalized === $this->normalizeLocation($province->name_en) || $normalized === $this->normalizeLocation($province->name_ne));
    }

    private function findDistrict($districts, string $name, ?int $provinceId): ?District
    {
        $normalized = $this->normalizeLocation($name);

        return $districts->first(function (District $district) use ($normalized, $provinceId): bool {
            if ($provinceId !== null && $district->province_id !== $provinceId) {
                return false;
            }

            return $normalized === $this->normalizeLocation($district->name_en) || $normalized === $this->normalizeLocation($district->name_ne);
        });
    }

    private function normalizeLocation(string $value): string
    {
        return Str::lower(trim(Str::replace([' province', ' pradesh', ' district', ' जिल्ला'], '', $value)));
    }
}
