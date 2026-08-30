<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\Role;
use App\Models\User;
use App\Support\GeographicHierarchyValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Page through users within the actor's geographic scope.
     *
     * @param  array{search?: string|null, status?: string|null, role?: string|null, province_id?: int|null, district_id?: int|null}  $filters
     */
    public function paginate(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles', 'province', 'district'])
            ->withCount('roles');

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
                    ->orWhere('username', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['province_id'])) {
            $query->where('province_id', $filters['province_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn (Builder $q) => $q->where('slug', $filters['role']));
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Ensure the actor is allowed to view the given user.
     */
    public function show(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        return $user->load(['roles', 'province', 'district']);
    }

    /**
     * Create a user within the actor's scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $this->assertWithinScope($actor, $data);

        if (! empty($data['role_ids'])) {
            $this->assertCanAssignRoles($actor, $data['role_ids']);
        }

        GeographicHierarchyValidator::validate($data);

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

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'] ?? 'active',
            'cadet_number' => $data['cadet_number'] ?? null,
            'rank_id' => $data['rank_id'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'local_level' => $data['local_level'] ?? null,
            'ward_number' => $data['ward_number'] ?? null,
            'photo_path' => $this->storePhoto($data['photo'] ?? null),
        ]);

        if (! empty($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        $this->auditLogger->record($actor, 'created', $user, null, null, $user->toArray());

        return $user->load(['roles', 'province', 'district', 'rank']);
    }

    /**
     * Update a user's basic attributes within the actor's scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->assertCanManage($actor, $user);
        $this->assertWithinScope($actor, $data);

        $mergedGeo = [
            'province_id' => $data['province_id'] ?? $user->province_id,
            'district_id' => $data['district_id'] ?? $user->district_id,
        ];
        GeographicHierarchyValidator::validate($mergedGeo);

        $old = $user->only(['name', 'username', 'email', 'phone', 'status', 'cadet_number', 'rank_id', 'province_id', 'district_id', 'local_level', 'ward_number']);

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'username' => $data['username'] ?? $user->username,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? $user->phone,
            'status' => $data['status'] ?? $user->status,
            'cadet_number' => $data['cadet_number'] ?? $user->cadet_number,
            'rank_id' => $data['rank_id'] ?? $user->rank_id,
            'province_id' => $data['province_id'] ?? $user->province_id,
            'district_id' => $data['district_id'] ?? $user->district_id,
            'local_level' => $data['local_level'] ?? $user->local_level,
            'ward_number' => $data['ward_number'] ?? $user->ward_number,
        ]);

        if (! empty($data['photo'])) {
            $user->photo_path = $this->storePhoto($data['photo'], $user->photo_path);
        }

        $user->save();

        $this->auditLogger->record($actor, 'updated', $user, oldValues: $old, newValues: $user->only(array_keys($old)));

        return $user->load(['roles', 'province', 'district', 'rank']);
    }

    /**
     * Store an uploaded profile photo under a randomized filename, replacing any prior file.
     */
    protected function storePhoto(?UploadedFile $photo, ?string $previousPath = null): ?string
    {
        if ($photo === null) {
            return $previousPath;
        }

        $path = $photo->store('avatars', 'public');

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }

    /**
     * Assign roles to a user.
     *
     * @param  array<int, int>  $roleIds
     */
    public function assignRoles(User $actor, User $user, array $roleIds): User
    {
        $this->assertCanManage($actor, $user);
        $this->assertCanAssignRoles($actor, $roleIds);

        $old = $user->roles->pluck('id')->all();
        $user->roles()->sync($roleIds);

        $this->auditLogger->record(
            $actor,
            'assigned_roles',
            $user,
            oldValues: ['roles' => $old],
            newValues: ['roles' => $roleIds],
        );

        return $user->load('roles');
    }

    /**
     * Assign a geographic scope to a user.
     *
     * @param  array<int, int>  $roleIds
     */
    public function assignGeography(
        User $actor,
        User $user,
        ?int $provinceId,
        ?int $districtId,
        array $roleIds = [],
    ): User {
        $this->assertCanManage($actor, $user);
        $this->assertWithinScope($actor, array_filter([
            'province_id' => $provinceId,
            'district_id' => $districtId,
        ], fn ($v) => $v !== null));

        if ($roleIds !== []) {
            $this->assertCanAssignRoles($actor, $roleIds);
        }

        GeographicHierarchyValidator::validate([
            'province_id' => $provinceId,
            'district_id' => $districtId,
        ]);

        $old = ['province_id' => $user->province_id, 'district_id' => $user->district_id];

        $user->forceFill([
            'province_id' => $provinceId,
            'district_id' => $districtId,
        ])->save();

        if ($roleIds !== []) {
            $user->roles()->sync($roleIds);
        }

        $this->auditLogger->record(
            $actor,
            'assigned_geography',
            $user,
            oldValues: $old,
            newValues: ['province_id' => $provinceId, 'district_id' => $districtId],
        );

        return $user->load(['roles', 'province', 'district']);
    }

    /**
     * Disable a user's account.
     */
    public function disable(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        $old = $user->status;
        $user->forceFill(['status' => 'disabled'])->save();

        $this->auditLogger->record($actor, 'disabled', $user, oldValues: ['status' => $old], newValues: ['status' => 'disabled']);

        return $user;
    }

    /**
     * Re-enable a user's account.
     */
    public function activate(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        $old = $user->status;
        $user->forceFill(['status' => 'active'])->save();

        $this->auditLogger->record($actor, 'activated', $user, oldValues: ['status' => $old], newValues: ['status' => 'active']);

        return $user;
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(User $actor, User $user, string $password): User
    {
        $this->assertCanManage($actor, $user);

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->auditLogger->record($actor, 'reset_password', $user);

        return $user;
    }

    /**
     * Soft delete a user within the actor's scope.
     */
    public function delete(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        $old = $user->only(['name', 'username', 'email', 'status']);
        $user->delete();

        $this->auditLogger->record($actor, 'deleted', $user, oldValues: $old, newValues: ['deleted' => true]);

        return $user;
    }

    /**
     * Throw if the actor lacks permission to manage the target user.
     */
    protected function assertCanManage(User $actor, User $user): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        if ($this->accessScope->isUnrestricted($user)) {
            throw ValidationException::withMessages([
                'user' => ['You do not have permission to manage this user.'],
            ]);
        }

        if ($scope === AccessScopeType::Province) {
            if ($user->hasAnyRole([Role::SUPER_ADMIN, Role::CENTRAL_ADMIN])) {
                throw ValidationException::withMessages([
                    'user' => ['You do not have permission to manage this user.'],
                ]);
            }

            if ($user->province_id === $actor->province_id) {
                return;
            }
        }

        if ($scope === AccessScopeType::District) {
            if ($user->hasAnyRole([Role::SUPER_ADMIN, Role::CENTRAL_ADMIN, Role::PROVINCE_ADMIN])) {
                throw ValidationException::withMessages([
                    'user' => ['You do not have permission to manage this user.'],
                ]);
            }

            if ($user->district_id === $actor->district_id) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'user' => ['You do not have permission to manage this user.'],
        ]);
    }

    /**
     * Ensure the actor is authorized to assign the requested roles.
     *
     * @param  array<int, int>  $roleIds
     */
    protected function assertCanAssignRoles(User $actor, array $roleIds): void
    {
        if (empty($roleIds)) {
            return;
        }

        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        $roles = Role::whereIn('id', $roleIds)->get();

        foreach ($roles as $role) {
            if (in_array($role->slug, [Role::SUPER_ADMIN, Role::CENTRAL_ADMIN], true)) {
                throw ValidationException::withMessages([
                    'role_ids' => ['You do not have authority to assign the '.$role->name.' role.'],
                ]);
            }

            if ($scope === AccessScopeType::District && in_array($role->slug, [Role::PROVINCE_ADMIN, Role::RECRUITMENT_MANAGER], true)) {
                throw ValidationException::withMessages([
                    'role_ids' => ['You do not have authority to assign the '.$role->name.' role.'],
                ]);
            }
        }
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
