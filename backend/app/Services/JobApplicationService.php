<?php

namespace App\Services;

use App\Mail\JobApplicationReceivedMailable;
use App\Models\JobCategory;
use App\Models\NccTrainingCenter;
use App\Models\RecruitmentActivity;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Support\BikramSambat;
use App\Support\NepaliPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobApplicationService
{
    /**
     * Divisions an applicant can belong to, stored as their canonical lowecase value.
     *
     * @var array<int, string>
     */
    public const DIVISIONS = ['junior', 'senior'];

    /**
     * The valid NCC training batch range per division, inclusive.
     *
     * @var array<string, array{min: int, max: int}>
     */
    public const DIVISION_BATCH_RANGES = [
        'junior' => ['min' => 1, 'max' => 51],
        'senior' => ['min' => 1, 'max' => 20],
    ];

    /**
     * How many BS (Bikram Sambat) years the portal offers for the NCC year field.
     */
    public const BS_YEAR_RANGE_COUNT = 20;

    public function __construct(private readonly SmtpService $smtp) {}

    /**
     * List the active job categories as public summaries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeCategories(): array
    {
        return JobCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (JobCategory $category): array => $category->summary())
            ->values()
            ->all();
    }

    /**
     * Resolve an active category by slug or throw a validation error.
     */
    public function activeCategory(string $slug): JobCategory
    {
        $category = JobCategory::where('slug', $slug)->where('is_active', true)->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_slug' => ['The selected job category is not available.'],
            ]);
        }

        return $category;
    }

    /**
     * The meta options shared by the portal (batches, divisions, years, limits).
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $batchRanges = [];
        foreach (self::DIVISIONS as $division) {
            $batchRanges[$division] = self::DIVISION_BATCH_RANGES[$division]['max'];
        }

        return [
            'divisions' => self::DIVISIONS,
            'batch_ranges' => $batchRanges,
            'years' => BikramSambat::yearRange(self::BS_YEAR_RANGE_COUNT),
            'training_centers' => NccTrainingCenter::orderBy('sort_order')->get(['id', 'slug', 'name_en', 'name_ne']),
            'limits' => [
                'photo_max_mb' => 2,
                'cv_max_mb' => 5,
                'proof_of_work_max_mb' => 10,
            ],
        ];
    }

    public function cadetNumberAvailable(string $cadetNumber): bool
    {
        $number = $this->normalizeCadetNumber($cadetNumber);

        return $number !== '' && ! $this->cadetNumberTaken($number);
    }

    /**
     * The fixed NCC passout training centers for the portal dropdown.
     *
     * @return Collection<int, NccTrainingCenter>
     */
    public function trainingCenters(): Collection
    {
        return NccTrainingCenter::orderBy('sort_order')->get(['id', 'slug', 'name_en', 'name_ne']);
    }

    public function cadetNumberTaken(string $cadetNumber): bool
    {
        $number = $this->normalizeCadetNumber($cadetNumber);

        return RecruitmentCandidate::where('cadet_number', $number)->exists()
            || User::where('cadet_number', $number)->exists();
    }

    public function emailTaken(string $email): bool
    {
        $email = Str::lower(trim($email));

        if ($email === '') {
            return false;
        }

        return RecruitmentCandidate::where('email', $email)->exists()
            || User::where('email', $email)->exists();
    }

    public static function normalizeCadetNumber(string $cadetNumber): string
    {
        return Str::upper(trim($cadetNumber));
    }

    /**
     * Validate and persist a public job application into the recruitment pipeline.
     *
     * @return array<string, mixed>
     */
    public function submit(Request $request): array
    {
        $category = $this->activeCategory((string) $request->string('category_slug'));

        $validated = $request->validate($this->rules($category, $request));

        $cadetNumber = $this->normalizeCadetNumber($validated['cadet_number']);
        $phone = NepaliPhoneNumber::normalize($validated['phone']);
        $email = Str::lower(trim($validated['email']));

        if ($this->cadetNumberTaken($cadetNumber)) {
            throw ValidationException::withMessages([
                'cadet_number' => ['This Cadet Number has already been used to submit an application.'],
            ]);
        }

        if ($this->emailTaken($email)) {
            throw ValidationException::withMessages([
                'email' => ['An application with this email address already exists. Please contact us if this is a mistake.'],
            ]);
        }

        $candidate = RecruitmentCandidate::create([
            'full_name' => $validated['full_name'],
            'cadet_number' => $cadetNumber,
            'citizenship_number' => $validated['citizenship_number'],
            'contact_number' => $phone,
            'email' => $email,
            'ncc_batch' => $validated['ncc_batch'],
            'ncc_year' => $validated['ncc_year'],
            'division' => $validated['division'],
            'ncc_training_center' => null,
            'ncc_training_center_id' => $validated['ncc_training_center_id'],
            'school' => $validated['school'] ?? null,
            'province_id' => $validated['province_id'],
            'district_id' => $validated['district_id'],
            'address' => $validated['address'],
            'skills' => [$category->name],
            'recruitment_status' => RecruitmentCandidate::STATUS_APPLIED,
            'source' => 'portal',
            'skill_category_id' => $category->id,
            'applicant_confirmed_uniform_photo' => true,
        ]);

        $this->storeFiles($request, $candidate, $category);
        $this->recordActivity($candidate, $category, $email);
        $this->sendAcknowledgment($candidate, $category);

        return [
            'candidate_id' => $candidate->id,
            'reference' => 'NCC-APP-'.$candidate->id,
            'category' => $category->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(JobCategory $category, Request $request): array
    {
        $division = (string) $request->string('division');
        $batchRange = self::DIVISION_BATCH_RANGES[$division] ?? ['min' => 1, 'max' => 51];
        $currentBsYear = BikramSambat::year();
        $bsRange = [$currentBsYear - (self::BS_YEAR_RANGE_COUNT - 1), $currentBsYear];

        $rules = [
            'full_name' => ['required', 'string', 'max:150'],
            'cadet_number' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150'],
            'ncc_batch' => ['required', 'integer', 'between:'.$batchRange['min'].','.$batchRange['max']],
            'ncc_year' => ['required', 'digits:4', 'integer', 'between:'.$bsRange[0].','.$bsRange[1]],
            'division' => ['required', Rule::in(self::DIVISIONS)],
            'ncc_training_center_id' => ['required', 'integer', 'exists:ncc_training_centers,id'],
            'school' => ['nullable', 'string', 'max:150'],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('province_id', $request->integer('province_id'))],
            'address' => ['required', 'string', 'max:500'],
            'citizenship_number' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,30}$/'],
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'uniform_photo_confirmed' => ['required', 'accepted'],
            'cv' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'proof_of_work_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240', 'required_without:proof_of_work_url'],
            'proof_of_work_url' => ['nullable', 'url', 'max:500', 'required_without:proof_of_work_file'],
        ];

        foreach ($category->field_schema['fields'] ?? [] as $field) {
            $key = 'skill_data.'.$field['key'];
            $rules[$key] = $this->fieldRules($field);
            if (($field['type'] ?? '') === 'multiselect' && isset($field['options'])) {
                $rules[$key.'.*'] = [Rule::in($field['options'])];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function fieldRules(array $field): array
    {
        $required = ! empty($field['required']) ? 'required' : 'nullable';
        $options = $field['options'] ?? [];
        $maxMb = (int) ($field['max_mb'] ?? 5);

        return match ($field['type'] ?? 'text') {
            'url' => [$required, 'url', 'max:500'],
            'textarea' => [$required, 'string', 'max:1000'],
            'select' => [$required, Rule::in($options)],
            'multiselect' => $required === 'required'
                ? ['required', 'array', 'min:1', 'max:20']
                : ['nullable', 'array', 'max:20'],
            'file' => ['nullable', 'file', $this->mimesFor($field), 'max:'.($maxMb * 1024)],
            default => [$required, 'string', 'max:200'],
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function mimesFor(array $field): string
    {
        $accept = $field['accept'] ?? '';

        return match (true) {
            str_contains($accept, 'pdf') && str_contains($accept, 'image') => 'mimes:pdf,jpeg,jpg,png,webp',
            str_contains($accept, 'pdf') => 'mimes:pdf',
            str_contains($accept, 'image') => 'mimes:jpeg,jpg,png,webp',
            default => 'mimes:pdf,jpeg,jpg,png,webp,doc,docx,txt',
        };
    }

    private function storeFiles(Request $request, RecruitmentCandidate $candidate, JobCategory $category): void
    {
        $directory = 'job-applications/'.$candidate->id;

        $updates = [
            'photo_path' => $request->file('photo')->storeAs($directory, 'photo.'.$request->file('photo')->guessExtension(), 'public'),
        ];

        if ($request->hasFile('cv')) {
            $updates['cv_path'] = $request->file('cv')->storeAs($directory, 'cv.pdf', 'public');
        }

        $proofOfWork = $request->input('proof_of_work_url');
        if ($request->hasFile('proof_of_work_file')) {
            $proofOfWork = $request->file('proof_of_work_file')->storeAs($directory, 'proof_of_work.'.$request->file('proof_of_work_file')->guessExtension(), 'public');
        }
        $updates['portfolio_path_or_url'] = $proofOfWork;

        $skillData = [];
        foreach ($category->field_schema['fields'] ?? [] as $field) {
            $key = $field['key'];
            if (($field['type'] ?? '') === 'file') {
                $file = $request->file('skill_data.'.$key);
                if ($file !== null) {
                    $skillData[$key] = $file->storeAs($directory, 'skill_'.$key.'.'.$file->guessExtension(), 'public');
                }

                continue;
            }
            $value = $request->input('skill_data.'.$key);
            if ($value !== null && $value !== '') {
                $skillData[$key] = $value;
            }
        }
        $updates['skill_specific_data'] = $skillData;

        $candidate->update($updates);
    }

    private function recordActivity(RecruitmentCandidate $candidate, JobCategory $category, string $email): void
    {
        RecruitmentActivity::create([
            'recruitment_candidate_id' => $candidate->id,
            'activity_type' => 'portal_application',
            'description' => 'Application submitted via the public job portal for '.$category->name,
            'performed_by_user_id' => null,
            'metadata' => ['category_slug' => $category->slug, 'category_name' => $category->name, 'email' => $email],
            'performed_at' => now(),
        ]);
    }

    private function sendAcknowledgment(RecruitmentCandidate $candidate, JobCategory $category): void
    {
        if (app()->environment('testing')) {
            Mail::to($candidate->email)->send(new JobApplicationReceivedMailable($candidate, $category));

            return;
        }

        $this->smtp->applyConfig();
        $provider = config('mail.default', 'log');
        if (in_array($provider, ['log', 'array'], true)) {
            Log::info('Job application acknowledgment skipped: mail provider not configured.', ['candidate_id' => $candidate->id]);

            return;
        }

        try {
            Mail::to($candidate->email)->send(new JobApplicationReceivedMailable($candidate, $category));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send job application acknowledgment.', [
                'candidate_id' => $candidate->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
