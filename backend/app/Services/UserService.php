<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'] ?? 'active',
            'province_id' => $data['province_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
        ]);

        if (! empty($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        $this->auditLogger->record($actor, 'created', $user, null, null, $user->toArray());

        return $user->load(['roles', 'province', 'district']);
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

        $old = $user->only(['name', 'username', 'email', 'phone', 'status', 'province_id', 'district_id']);

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'username' => $data['username'] ?? $user->username,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? $user->phone,
            'status' => $data['status'] ?? $user->status,
            'province_id' => $data['province_id'] ?? $user->province_id,
            'district_id' => $data['district_id'] ?? $user->district_id,
        ]);
        $user->save();

        $this->auditLogger->record($actor, 'updated', $user, oldValues: $old, newValues: $user->only(array_keys($old)));

        return $user->load(['roles', 'province', 'district']);
    }

    /**
     * Assign roles to a user.
     *
     * @param  array<int, int>  $roleIds
     */
    public function assignRoles(User $actor, User $user, array $roleIds): User
    {
        $this->assertCanManage($actor, $user);

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
     * Throw if the actor lacks permission to manage the target user.
     */
    protected function assertCanManage(User $actor, User $user): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        if ($scope === AccessScopeType::Province && $user->province_id === $actor->province_id) {
            return;
        }

        if ($scope === AccessScopeType::District && $user->district_id === $actor->district_id) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['You do not have permission to manage this user.'],
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

        if (! empty($data['province_id']) && $scope === AccessScopeType::Province
            && ! in_array($data['province_id'], $geography['province_ids'], true)) {
            throw ValidationException::withMessages([
                'province_id' => ['Province is outside your scope.'],
            ]);
        }

        if (! empty($data['district_id']) && ! in_array($data['district_id'], $geography['district_ids'], true)) {
            throw ValidationException::withMessages([
                'district_id' => ['District is outside your scope.'],
            ]);
        }
    }
}
