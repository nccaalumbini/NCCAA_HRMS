<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $roleSlug, array $permissionSlugs = [], array $attrs = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst(str_replace('-', ' ', $roleSlug))]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug),
                'group' => explode('.', $slug)[0],
            ]);
            if (! $role->permissions->contains($permission->id)) {
                $role->permissions()->attach($permission->id);
            }
        }

        $user = User::factory()->create($attrs);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function authToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_district_admin_cannot_assign_super_admin_or_province_admin_role(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);

        $superAdminRole = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super Admin']);
        $provinceAdminRole = Role::firstOrCreate(['slug' => Role::PROVINCE_ADMIN], ['name' => 'Province Admin']);
        $cadetRole = Role::firstOrCreate(['slug' => Role::CADET], ['name' => 'Cadet']);

        $districtAdmin = $this->actor(Role::DISTRICT_ADMIN, ['users.create', 'users.assign-role'], [
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $token = $this->authToken($districtAdmin);

        // Attempt to create user with Super Admin role
        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Escalated User',
            'username' => 'escalated.user1',
            'email' => 'escalated1@example.com',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'role_ids' => [$superAdminRole->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_ids']);

        // Attempt to create user with Province Admin role
        $response2 = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Escalated User 2',
            'username' => 'escalated.user2',
            'email' => 'escalated2@example.com',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'role_ids' => [$provinceAdminRole->id],
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['role_ids']);
    }

    public function test_province_admin_cannot_assign_super_admin_role(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);
        $superAdminRole = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super Admin']);

        $provinceAdmin = $this->actor(Role::PROVINCE_ADMIN, ['users.create', 'users.assign-role'], [
            'province_id' => $province->id,
        ]);

        $token = $this->authToken($provinceAdmin);

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Escalated User',
            'username' => 'escalated.user3',
            'email' => 'escalated3@example.com',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'role_ids' => [$superAdminRole->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_ids']);
    }

    public function test_district_admin_cannot_modify_province_admin_or_super_admin(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);

        $districtAdmin = $this->actor(Role::DISTRICT_ADMIN, ['users.update', 'users.delete', 'users.reset-password'], [
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $superAdmin = $this->actor(Role::SUPER_ADMIN, [], [
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $provinceAdmin = $this->actor(Role::PROVINCE_ADMIN, [], [
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $token = $this->authToken($districtAdmin);

        // Cannot disable super admin
        $this->withToken($token)->postJson("/api/v1/users/{$superAdmin->id}/disable")
            ->assertStatus(422);

        // Cannot reset password for province admin
        $this->withToken($token)->postJson("/api/v1/users/{$provinceAdmin->id}/reset-password", [
            'password' => 'NewPassword#123456',
            'password_confirmation' => 'NewPassword#123456',
        ])->assertStatus(422);

        // Cannot delete province admin
        $this->withToken($token)->deleteJson("/api/v1/users/{$provinceAdmin->id}")
            ->assertStatus(422);
    }

    public function test_system_roles_cannot_be_deleted_or_have_slug_mutated(): void
    {
        $superAdmin = $this->actor(Role::SUPER_ADMIN, ['roles.update', 'roles.delete']);
        $token = $this->authToken($superAdmin);

        $cadetRole = Role::firstOrCreate(['slug' => Role::CADET], ['name' => 'Cadet']);

        // Cannot delete system role
        $response = $this->withToken($token)->deleteJson("/api/v1/roles/{$cadetRole->id}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        // Cannot change slug of system role
        $response2 = $this->withToken($token)->patchJson("/api/v1/roles/{$cadetRole->id}", [
            'slug' => 'custom-cadet-role',
        ]);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }
}
