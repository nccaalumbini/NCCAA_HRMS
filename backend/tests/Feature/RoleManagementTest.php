<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user bound to a role with the given permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function actor(string $roleSlug = 'central-admin', array $permissionSlugs = []): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);

        foreach ($permissionSlugs as $slug) {
            $role->permissions()->attach(Permission::factory()->create(['slug' => $slug])->id);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function authToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_unauthenticated_users_cannot_access_role_endpoints(): void
    {
        $this->getJson('/api/v1/roles')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->actor('cadet', ['skills.view']);
        $this->withToken($this->authToken($user))->getJson('/api/v1/roles')->assertForbidden();
    }

    public function test_super_admin_can_bypass_permission_checks(): void
    {
        Role::factory()->create(['name' => 'Auditor', 'slug' => 'auditor']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, []));

        $this->withToken($token)->getJson('/api/v1/roles')->assertOk();
    }

    public function test_can_list_roles_with_permissions(): void
    {
        $role = Role::factory()->create(['name' => 'Analyst', 'slug' => 'analyst']);
        $role->permissions()->attach(Permission::factory()->create(['slug' => 'reports.view'])->id);

        $token = $this->authToken($this->actor('central-admin', ['roles.view']));

        $response = $this->withToken($token)->getJson('/api/v1/roles');
        $response->assertOk();
        $this->assertEquals('Analyst', $response->json('data.items.0.name'));
    }

    public function test_can_create_role_with_permissions(): void
    {
        $perm = Permission::factory()->create(['slug' => 'user.read']);
        $token = $this->authToken($this->actor('central-admin', ['roles.create']));

        $response = $this->withToken($token)->postJson('/api/v1/roles', [
            'name' => 'Field Officer',
            'description' => 'Read access to users',
            'permission_ids' => [$perm->id],
        ]);

        $response->assertCreated();
        $this->assertEquals('field-officer', $response->json('data.slug'));
        $this->assertCount(1, $response->json('data.permissions'));
    }

    public function test_cannot_create_role_without_name(): void
    {
        $token = $this->authToken($this->actor('central-admin', ['roles.create']));

        $this->withToken($token)->postJson('/api/v1/roles', [])->assertStatus(422);
    }

    public function test_can_show_a_role(): void
    {
        $role = Role::factory()->create(['slug' => 'supervisor']);
        $perm = Permission::factory()->create(['slug' => 'user.read']);
        $role->permissions()->attach($perm);
        $token = $this->authToken($this->actor('central-admin', ['roles.view']));

        $response = $this->withToken($token)->getJson("/api/v1/roles/{$role->id}");
        $response->assertOk();
        $this->assertEquals('supervisor', $response->json('data.slug'));
        $this->assertEquals('user.read', $response->json('data.permissions.0.slug'));
    }

    public function test_can_update_a_role_and_sync_permissions(): void
    {
        $perm = Permission::factory()->create(['slug' => 'user.read']);
        $extra = Permission::factory()->create(['slug' => 'user.write']);
        $role = Role::factory()->create(['slug' => 'supervisor']);
        $role->permissions()->attach($perm);
        $token = $this->authToken($this->actor('central-admin', ['roles.update']));

        $response = $this->withToken($token)->patchJson("/api/v1/roles/{$role->id}", [
            'name' => 'Senior Supervisor',
            'permission_ids' => [$extra->id],
        ]);

        $response->assertOk();
        $this->assertEquals('Senior Supervisor', $response->json('data.name'));
        $this->assertCount(1, $response->json('data.permissions'));
        $this->assertEquals('user.write', $response->json('data.permissions.0.slug'));
    }

    public function test_can_delete_a_role_without_users(): void
    {
        $role = Role::factory()->create(['slug' => 'temp-role']);
        $token = $this->authToken($this->actor('central-admin', ['roles.delete']));

        $this->withToken($token)->deleteJson("/api/v1/roles/{$role->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_a_role_with_assigned_users(): void
    {
        $role = Role::factory()->create(['slug' => 'occupied']);
        $user = $this->actor('central-admin', ['roles.delete', 'roles.view']);
        $user->roles()->attach($role);
        $token = $this->authToken($user);

        $this->withToken($token)->deleteJson("/api/v1/roles/{$role->id}")->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
