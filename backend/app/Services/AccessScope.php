<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Models\District;
use App\Models\Role;
use App\Models\User;

class AccessScope
{
    /**
     * Resolve the geographic scope a user has over cadet records.
     *
     * Super admins and central admins see everything. Province admins are
     * restricted to their province; district admins to their district.
     * Other roles are restricted to the records they own (null scope).
     */
    public function resolve(User $user): ?AccessScopeType
    {
        if ($this->isUnrestricted($user)) {
            return AccessScopeType::All;
        }

        if ($user->hasRole(Role::PROVINCE_ADMIN) && $user->province_id !== null) {
            return AccessScopeType::Province;
        }

        if ($user->hasRole(Role::DISTRICT_ADMIN) && $user->district_id !== null) {
            return AccessScopeType::District;
        }

        return null;
    }

    /**
     * Determine whether the user can access every record.
     */
    public function isUnrestricted(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPER_ADMIN,
            Role::CENTRAL_ADMIN,
            Role::RECRUITMENT_MANAGER,
        ]);
    }

    /**
     * Return the ids the user is allowed to see (province/district ids),
     * or null when the user is unrestricted.
     *
     * @return array{province_ids: array<int, int>, district_ids: array<int, int>}|null
     */
    public function scopedGeography(User $user): ?array
    {
        $type = $this->resolve($user);

        if ($type === null || $type === AccessScopeType::All) {
            return null;
        }

        if ($type === AccessScopeType::Province) {
            $districtIds = District::where('province_id', $user->province_id)->pluck('id')->all();

            return [
                'province_ids' => [$user->province_id],
                'district_ids' => $districtIds,
            ];
        }

        return [
            'province_ids' => [],
            'district_ids' => [$user->district_id],
        ];
    }
}
