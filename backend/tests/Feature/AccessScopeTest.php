<?php

namespace Tests\Feature;

use App\Enums\AccessScopeType;
use App\Models\District;
use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_all_scope(): void
    {
        $user = $this->userWithRole(Role::SUPER_ADMIN);

        $scope = app(AccessScope::class)->resolve($user);

        $this->assertSame(AccessScopeType::All, $scope);
        $this->assertNull(app(AccessScope::class)->scopedGeography($user));
    }

    public function test_province_admin_has_province_scope(): void
    {
        $province = Province::factory()->create();
        $districtA = District::factory()->create(['province_id' => $province->id]);
        District::factory()->create(); // another province

        $user = $this->userWithRole(Role::PROVINCE_ADMIN, ['province_id' => $province->id]);

        $scope = app(AccessScope::class)->resolve($user);

        $this->assertSame(AccessScopeType::Province, $scope);

        $geography = app(AccessScope::class)->scopedGeography($user);

        $this->assertSame([$province->id], $geography['province_ids']);
        $this->assertContains($districtA->id, $geography['district_ids']);
    }

    public function test_district_admin_has_district_scope(): void
    {
        $district = District::factory()->create();

        $user = $this->userWithRole(Role::DISTRICT_ADMIN, ['district_id' => $district->id]);

        $scope = app(AccessScope::class)->resolve($user);

        $this->assertSame(AccessScopeType::District, $scope);

        $geography = app(AccessScope::class)->scopedGeography($user);

        $this->assertSame([$district->id], $geography['district_ids']);
    }

    public function test_cadet_has_no_scope(): void
    {
        $user = $this->userWithRole(Role::CADET);

        $this->assertNull(app(AccessScope::class)->resolve($user));
    }

    private function userWithRole(string $roleSlug, array $attrs = []): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);

        $user = User::factory()->create($attrs);
        $user->roles()->attach($role);

        return $user;
    }
}
