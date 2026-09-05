<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\ApplicationNote;
use App\Models\JobCategory;
use App\Models\RecruitmentActivity;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    /**
     * The application pipeline stages in review order. Promoted, rejected and
     * withdrawn stages are terminal.
     *
     * @var array<int, string>
     */
    public const PIPELINE = [
        RecruitmentCandidate::STATUS_APPLIED,
        RecruitmentCandidate::STATUS_UNDER_REVIEW,
        RecruitmentCandidate::STATUS_SHORTLISTED,
        RecruitmentCandidate::STATUS_INTERVIEW_SCHEDULED,
        RecruitmentCandidate::STATUS_INTERVIEWED,
        RecruitmentCandidate::STATUS_OFFER_EXTENDED,
        RecruitmentCandidate::STATUS_CONVERTED,
        RecruitmentCandidate::STATUS_REJECTED,
        RecruitmentCandidate::STATUS_WITHDRAWN,
    ];

    /**
     * @var array<int, string>
     */
    public const TERMINAL_STAGES = [
        RecruitmentCandidate::STATUS_CONVERTED,
        RecruitmentCandidate::STATUS_REJECTED,
        RecruitmentCandidate::STATUS_WITHDRAWN,
    ];

    /**
     * Predefined rejection reasons offered by the drawer. Custom reasons are
     * accepted as free text alongside these.
     *
     * @var array<string, string>
     */
    public const REJECTION_REASONS = [
        'Not enough experience',
        'Position filled',
        'Incomplete documents',
        'Other',
    ];

    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{search?: string|null, stage?: string|null, skill_category_id?: int|string|null, division?: string|null, province_id?: int|string|null, district_id?: int|string|null, date_from?: string|null, date_to?: string|null, sort?: string|null}  $filters
     */
    public function paginate(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->scopedQuery($actor)
            ->with(['province', 'district', 'skillCategory', 'trainingCenter']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('cadet_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['stage'])) {
            $query->where('recruitment_status', $filters['stage']);
        }

        if (! empty($filters['skill_category_id'])) {
            $query->where('skill_category_id', $filters['skill_category_id']);
        }

        if (! empty($filters['division'])) {
            $query->where('division', $filters['division']);
        }

        if (! empty($filters['province_id'])) {
            $query->where('province_id', $filters['province_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (($filters['sort'] ?? 'desc') === 'asc') {
            $query->oldest();
        } else {
            $query->latest();
        }

        return $query->paginate(max(1, min(100, $perPage)));
    }

    public function show(User $actor, RecruitmentCandidate $application): RecruitmentCandidate
    {
        $this->assertCanManage($actor, $application);

        return $application->loadMissing([
            'province', 'district', 'skillCategory', 'trainingCenter', 'stageUpdatedBy:id,name',
        ]);
    }

    /**
     * Change the application stage with transition rules.
     *
     * @param  array{stage?: string, rejection_reason?: string|null, reopen?: bool, interview_scheduled_at?: string|null, interview_location?: string|null, interview_link?: string|null}  $data
     */
    public function changeStage(User $actor, RecruitmentCandidate $application, array $data): RecruitmentCandidate
    {
        $this->assertCanManage($actor, $application);

        if ($application->recruitment_status === RecruitmentCandidate::STATUS_CONVERTED) {
            throw ValidationException::withMessages([
                'stage' => ['This application has already been promoted and cannot be changed.'],
            ]);
        }

        if (($data['stage'] ?? '') === RecruitmentCandidate::STATUS_CONVERTED) {
            throw ValidationException::withMessages([
                'stage' => ['Promotion is handled by the dedicated promote-to-cadet flow.'],
            ]);
        }

        $target = $data['stage'] ?? '';
        $current = $application->recruitment_status;

        $rules = ['stage' => ['required', Rule::in(self::PIPELINE)]];

        if ($target === RecruitmentCandidate::STATUS_REJECTED) {
            $rules['rejection_reason'] = ['required', 'string', 'max:255'];
        }

        if ($target === RecruitmentCandidate::STATUS_INTERVIEW_SCHEDULED) {
            $rules['interview_scheduled_at'] = ['nullable', 'date'];
            $rules['interview_location'] = ['nullable', 'string', 'max:255'];
            $rules['interview_link'] = ['nullable', 'url', 'max:500'];
        }

        if ($this->isTerminal($current) && ! $this->isTerminal($target)) {
            $rules['reopen'] = ['accepted'];
        }

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($data, $rules);
        $validator->validate();

        $old = $application->only(['recruitment_status', 'rejection_reason', 'interview_scheduled_at', 'interview_location', 'interview_link']);

        $application->recruitment_status = $target;
        $application->stage_updated_at = now();
        $application->stage_updated_by = $actor->id;

        if ($target === RecruitmentCandidate::STATUS_REJECTED) {
            $application->rejection_reason = $data['rejection_reason'];
        } else {
            $application->rejection_reason = null;
        }

        if ($target === RecruitmentCandidate::STATUS_INTERVIEW_SCHEDULED) {
            $application->interview_scheduled_at = $data['interview_scheduled_at'] ?? null;
            $application->interview_location = $data['interview_location'] ?? null;
            $application->interview_link = $data['interview_link'] ?? null;
        }

        $application->save();

        $description = sprintf('Stage changed from %s to %s.', $this->stageLabel($current), $this->stageLabel($target));
        if ($target === RecruitmentCandidate::STATUS_REJECTED) {
            $description .= ' Reason: '.$data['rejection_reason'];
        }
        $skipped = $this->skippedStages($current, $target);
        if (! empty($skipped)) {
            $description .= ' (skipped '.implode(', ', $skipped).')';
        }

        RecruitmentActivity::create([
            'recruitment_candidate_id' => $application->id,
            'activity_type' => 'stage_changed',
            'description' => $description,
            'performed_by_user_id' => $actor->id,
            'metadata' => [
                'old_stage' => $current,
                'new_stage' => $target,
                'skipped' => $skipped,
                'rejection_reason' => $target === RecruitmentCandidate::STATUS_REJECTED ? $data['rejection_reason'] : null,
            ],
            'performed_at' => now(),
        ]);

        $this->auditLogger->record($actor, 'changed_application_stage', $application, oldValues: $old, newValues: $application->only(['recruitment_status', 'rejection_reason', 'interview_scheduled_at', 'interview_location', 'interview_link']));

        return $application->loadMissing(['province', 'district', 'skillCategory', 'trainingCenter']);
    }

    /**
     * @param  array{note?: string}  $data
     */
    public function addNote(User $actor, RecruitmentCandidate $application, array $data): ApplicationNote
    {
        $this->assertCanManage($actor, $application);

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($data, [
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $validator->validate();

        $note = ApplicationNote::create([
            'application_id' => $application->id,
            'user_id' => $actor->id,
            'note' => $data['note'],
        ]);

        $this->auditLogger->record($actor, 'added_application_note', $application, oldValues: null, newValues: ['note' => $data['note']]);

        return $note->loadMissing('user:id,name');
    }

    /**
     * Aggregate pipeline stats for the applications module.
     *
     * @return array{total: int, month_total: int, by_stage: array<int, array{stage: string, count: int}>, by_category: array<int, array{category_id: int, name: string, count: int}>, avg_time_to_decision_days: float|null}
     */
    public function stats(User $actor): array
    {
        $stages = $this->scopedQuery($actor)
            ->selectRaw('recruitment_status, count(*) as total')
            ->groupBy('recruitment_status')
            ->pluck('total', 'recruitment_status');

        $categoryCounts = $this->scopedQuery($actor)
            ->selectRaw('skill_category_id, count(*) as total')
            ->whereNotNull('skill_category_id')
            ->groupBy('skill_category_id')
            ->get();

        $categoryNames = JobCategory::whereIn('id', $categoryCounts->pluck('skill_category_id')->all())
            ->pluck('name', 'id');

        $decisions = $this->scopedQuery($actor)
            ->whereIn('recruitment_status', self::TERMINAL_STAGES)
            ->whereNotNull('stage_updated_at')
            ->get(['created_at', 'stage_updated_at']);

        $totalDays = 0;
        foreach ($decisions as $decision) {
            $totalDays += $decision->created_at->diffInDays($decision->stage_updated_at);
        }

        return [
            'total' => $this->scopedQuery($actor)->count(),
            'month_total' => $this->scopedQuery($actor)->where('created_at', '>=', now()->startOfMonth())->count(),
            'by_stage' => collect(self::PIPELINE)
                ->filter(fn (string $stage): bool => $stages->has($stage))
                ->map(fn (string $stage): array => ['stage' => $stage, 'count' => (int) $stages[$stage]])
                ->values()
                ->all(),
            'by_category' => $categoryCounts
                ->map(fn ($row): array => [
                    'category_id' => (int) $row->skill_category_id,
                    'name' => $categoryNames[$row->skill_category_id] ?? 'Unknown',
                    'count' => (int) $row->total,
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'avg_time_to_decision_days' => $decisions->isNotEmpty() ? round($totalDays / $decisions->count(), 1) : null,
        ];
    }

    /**
     * Applications or users that collide with the given application on cadet
     * number or email address.
     *
     * @return array<int, array<string, mixed>>
     */
    public function duplicates(RecruitmentCandidate $application): array
    {
        $candidates = collect();

        if ($application->cadet_number !== null) {
            $candidates = $candidates->merge(
                RecruitmentCandidate::where('cadet_number', Str::upper($application->cadet_number))
                    ->where('id', '!=', $application->id)
                    ->get(['id', 'full_name', 'cadet_number', 'email', 'recruitment_status']),
            );
        }

        if ($application->email !== null) {
            $candidates = $candidates->merge(
                RecruitmentCandidate::where('email', Str::lower($application->email))
                    ->where('id', '!=', $application->id)
                    ->get(['id', 'full_name', 'cadet_number', 'email', 'recruitment_status']),
            );
        }

        $duplicates = array_values($candidates->keyBy('id')->map(
            fn (RecruitmentCandidate $match): array => [
                'reason' => implode(' + ', $this->collisionReasons($application, $match)),
                'record_type' => 'candidate',
                'full_name' => $match->full_name,
                'cadet_number' => $match->cadet_number,
                'email' => $match->email,
                'recruitment_status' => $match->recruitment_status,
                'application_id' => $match->id,
            ],
        )->all());

        if ($application->cadet_number !== null || $application->email !== null) {
            foreach (User::select(['id', 'name', 'cadet_number', 'email'])
                ->where(fn ($query) => $query
                    ->where('cadet_number', Str::upper((string) $application->cadet_number))
                    ->orWhere('email', Str::lower((string) $application->email)))
                ->get() as $user) {
                $reasons = $this->collisionReasons($application, $user);
                if (empty($reasons)) {
                    continue;
                }
                $duplicates[] = [
                    'reason' => implode(' + ', $reasons),
                    'record_type' => 'user',
                    'full_name' => $user->name,
                    'cadet_number' => $user->cadet_number,
                    'email' => $user->email,
                    'application_id' => null,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * @return array<int, string>
     */
    private function collisionReasons(RecruitmentCandidate $application, RecruitmentCandidate|User $match): array
    {
        $reasons = [];

        if ($application->cadet_number !== null && $match->cadet_number !== null
            && Str::upper($match->cadet_number) === Str::upper($application->cadet_number)) {
            $reasons[] = 'cadet_number';
        }

        if ($application->email !== null && $match->email !== null
            && Str::lower($match->email) === Str::lower($application->email)) {
            $reasons[] = 'email';
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationPayload(RecruitmentCandidate $application): array
    {
        $application->loadMissing(['province', 'district', 'skillCategory', 'trainingCenter', 'stageUpdatedBy:id,name']);

        return [
            'id' => $application->id,
            'reference' => 'NCC-APP-'.$application->id,
            'full_name' => $application->full_name,
            'gender' => $application->gender,
            'cadet_number' => $application->cadet_number,
            'citizenship_number' => $application->citizenship_number,
            'email' => $application->email,
            'contact_number' => $application->contact_number,
            'division' => $application->division,
            'ncc_batch' => $application->ncc_batch,
            'ncc_year' => $application->ncc_year,
            'school' => $application->school,
            'address' => $application->address,
            'province' => $application->province?->only(['id', 'name_en', 'name_ne']),
            'district' => $application->district?->only(['id', 'name_en', 'name_ne']),
            'skill_category' => $application->skillCategory !== null
                ? [
                    'id' => $application->skillCategory->id,
                    'name' => $application->skillCategory->name,
                    'slug' => $application->skillCategory->slug,
                    'field_schema' => $application->skillCategory->field_schema,
                ]
                : null,
            'training_center' => $application->trainingCenter?->only(['id', 'slug', 'name_en', 'name_ne']),
            'stage' => $application->recruitment_status,
            'rejection_reason' => $application->rejection_reason,
            'interview_scheduled_at' => $application->interview_scheduled_at,
            'interview_location' => $application->interview_location,
            'interview_link' => $application->interview_link,
            'stage_updated_at' => $application->stage_updated_at,
            'stage_updated_by' => $application->stageUpdatedBy?->only(['id', 'name']),
            'photo_url' => $this->storageUrl($application->photo_path),
            'cv_url' => $this->storageUrl($application->cv_path),
            'proof_url' => $this->storageUrl($application->portfolio_path_or_url),
            'proof_is_link' => Str::startsWith($application->portfolio_path_or_url ?? '', ['http://', 'https://']),
            'applicant_confirmed_uniform_photo' => $application->applicant_confirmed_uniform_photo,
            'skill_specific_data' => $application->skill_specific_data ?? [],
            'source' => $application->source,
            'converted_user_id' => $application->converted_user_id,
            'converted_at' => $application->converted_at,
            'created_at' => $application->created_at,
        ];
    }

    /**
     * @return array{notes: array<int, array<string, mixed>>, activities: array<int, array<string, mixed>>, duplicates: array<int, array<string, mixed>>}
     */
    public function detailPayload(RecruitmentCandidate $application): array
    {
        $notes = $application->notes()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn (ApplicationNote $note): array => [
                'id' => $note->id,
                'note' => $note->note,
                'created_at' => $note->created_at,
                'user' => $note->user?->only(['id', 'name']),
            ])
            ->all();

        $activities = $application->activities()
            ->with('performedByUser:id,name')
            ->latest('performed_at')
            ->get()
            ->map(fn (RecruitmentActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->activity_type,
                'description' => $activity->description,
                'performed_by' => $activity->performedByUser?->only(['id', 'name']),
                'performed_at' => $activity->performed_at,
                'metadata' => $activity->metadata ?? [],
            ])
            ->all();

        return [
            'notes' => $notes,
            'activities' => $activities,
            'duplicates' => $this->duplicates($application),
        ];
    }

    public static function stageLabel(string $stage): string
    {
        return str($stage)->replace('_', ' ')->replace('converted to cadet', 'converted to cadet')->title()->toString();
    }

    protected function isTerminal(string $stage): bool
    {
        return in_array($stage, self::TERMINAL_STAGES, true);
    }

    /**
     * @return array<int, string>
     */
    private function skippedStages(string $from, string $to): array
    {
        $fromIndex = array_search($from, self::PIPELINE, true);
        $toIndex = array_search($to, self::PIPELINE, true);

        if ($fromIndex === false || $toIndex === false || $fromIndex >= $toIndex - 1 || $this->isTerminal($from)) {
            return [];
        }

        return collect(array_slice(self::PIPELINE, $fromIndex + 1, $toIndex - $fromIndex - 1))
            ->map(fn (string $stage): string => $this->stageLabel($stage))
            ->all();
    }

    private function storageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://']) ? $path : Storage::disk('public')->url($path);
    }

    private function scopedQuery(User $actor): Builder
    {
        $query = RecruitmentCandidate::query()->where('source', 'portal');

        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::Province && $actor->province_id) {
            $query->where('province_id', $actor->province_id);
        } elseif ($scope === AccessScopeType::District && $actor->district_id) {
            $query->where('district_id', $actor->district_id);
        }

        return $query;
    }

    private function assertCanManage(User $actor, RecruitmentCandidate $application): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        if ($scope === AccessScopeType::Province && $application->province_id === $actor->province_id) {
            return;
        }

        if ($scope === AccessScopeType::District && $application->district_id === $actor->district_id) {
            return;
        }

        throw ValidationException::withMessages([
            'application' => ['You do not have permission to manage this application.'],
        ]);
    }
}
