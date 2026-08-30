<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\Cadet;
use App\Models\User;
use App\Support\GeographicHierarchyValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CadetService
{
    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Page through cadets within the actor's geographic scope.
     *
     * @param  array{search?: string|null, status?: string|null, rank_id?: int|null, province_id?: int|null, district_id?: int|null}  $filters
     */
    public function paginate(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Cadet::query()
            ->with(['rank', 'province', 'district', 'localLevel', 'ward', 'profile']);

        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::Province && $actor->province_id) {
            $query->where('province_id', $actor->province_id);
        } elseif ($scope === AccessScopeType::District && $actor->district_id) {
            $query->where('district_id', $actor->district_id);
        }

        if (! empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $q->where('name', 'like', $search)
                    ->orWhere('cadet_number', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['rank_id'])) {
            $query->where('rank_id', $filters['rank_id']);
        }

        if (! empty($filters['province_id'])) {
            $query->where('province_id', $filters['province_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Ensure the actor is allowed to view the given cadet.
     */
    public function show(User $actor, Cadet $cadet): Cadet
    {
        $this->assertCanManage($actor, $cadet);

        return $cadet->load(['rank', 'province', 'district', 'localLevel', 'ward', 'profile', 'user']);
    }

    /**
     * Create a cadet (and optional profile) within the actor's scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Cadet
    {
        $this->assertWithinScope($actor, $data);

        $scope = $this->accessScope->resolve($actor);
        if ($scope === AccessScopeType::Province && empty($data['province_id'])) {
            $data['province_id'] = $actor->province_id;
        } elseif ($scope === AccessScopeType::District) {
            if (empty($data['province_id']) && $actor->province_id) {
                $data['province_id'] = $actor->province_id;
            }
            if (empty($data['district_id']) && $actor->district_id) {
                $data['district_id'] = $actor->district_id;
            }
        }

        GeographicHierarchyValidator::validate($data);

        $data['status'] ??= 'active';

        $cadet = Cadet::create($this->cadetFields($data));

        if (! empty($data['profile'])) {
            $this->syncProfile($cadet, $data['profile']);
        }

        $this->auditLogger->record($actor, 'created', $cadet);

        return $this->show($actor, $cadet);
    }

    /**
     * Update a cadet's details within the actor's scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Cadet $cadet, array $data): Cadet
    {
        $this->assertCanManage($actor, $cadet);
        $this->assertWithinScope($actor, $data);

        $mergedGeo = [
            'province_id' => array_key_exists('province_id', $data) ? $data['province_id'] : $cadet->province_id,
            'district_id' => array_key_exists('district_id', $data) ? $data['district_id'] : $cadet->district_id,
            'local_level_id' => array_key_exists('local_level_id', $data) ? $data['local_level_id'] : $cadet->local_level_id,
            'ward_id' => array_key_exists('ward_id', $data) ? $data['ward_id'] : $cadet->ward_id,
        ];
        GeographicHierarchyValidator::validate($mergedGeo);

        $cadet->update($this->cadetFields($data));

        if (array_key_exists('profile', $data)) {
            $this->syncProfile($cadet, $data['profile'] ?? []);
        }

        $this->auditLogger->record($actor, 'updated', $cadet);

        return $this->show($actor, $cadet);
    }

    /**
     * Soft delete a cadet within the actor's scope.
     */
    public function delete(User $actor, Cadet $cadet): void
    {
        $this->assertCanManage($actor, $cadet);

        $cadet->delete();

        $this->auditLogger->record($actor, 'deleted', $cadet);
    }

    /**
     * Persist (or clear) the cadet's extended profile.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncProfile(Cadet $cadet, array $data): void
    {
        if ($data === []) {
            if ($cadet->profile()->exists()) {
                $cadet->profile()->delete();
            }

            return;
        }

        $cadet->profile()->updateOrCreate(['cadet_id' => $cadet->id], $data);
    }

    /**
     * Extract the cadet table fields from the validated payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function cadetFields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'cadet_number',
            'name',
            'rank_id',
            'province_id',
            'district_id',
            'local_level_id',
            'ward_id',
            'email',
            'phone',
            'status',
            'user_id',
        ]));
    }

    /**
     * Throw if the actor lacks scope over the given cadet.
     */
    protected function assertCanManage(User $actor, Cadet $cadet): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        if ($scope === AccessScopeType::Province && $cadet->province_id === $actor->province_id) {
            return;
        }

        if ($scope === AccessScopeType::District && $cadet->district_id === $actor->district_id) {
            return;
        }

        throw ValidationException::withMessages([
            'cadet' => ['You do not have permission to manage this cadet.'],
        ]);
    }

    /**
     * Ensure the geography being assigned is within the actor's scope.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertWithinScope(User $actor, array $data): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope !== AccessScopeType::Province && $scope !== AccessScopeType::District) {
            return;
        }

        $geography = $this->accessScope->scopedGeography($actor);

        if ($geography === null) {
            return;
        }

        if ($scope === AccessScopeType::Province) {
            if (array_key_exists('province_id', $data) && $data['province_id'] !== null
                && ! in_array((int) $data['province_id'], $geography['province_ids'], true)) {
                throw ValidationException::withMessages([
                    'province_id' => ['Province is outside your scope.'],
                ]);
            }

            if (! empty($data['district_id']) && ! in_array((int) $data['district_id'], $geography['district_ids'], true)) {
                throw ValidationException::withMessages([
                    'district_id' => ['District is outside your scope.'],
                ]);
            }
        }

        if ($scope === AccessScopeType::District) {
            if (array_key_exists('province_id', $data) && $data['province_id'] !== null
                && $actor->province_id !== null && (int) $data['province_id'] !== (int) $actor->province_id) {
                throw ValidationException::withMessages([
                    'province_id' => ['Province is outside your scope.'],
                ]);
            }

            if (array_key_exists('district_id', $data) && $data['district_id'] !== null
                && ! in_array((int) $data['district_id'], $geography['district_ids'], true)) {
                throw ValidationException::withMessages([
                    'district_id' => ['District is outside your scope.'],
                ]);
            }
        }
    }
}
