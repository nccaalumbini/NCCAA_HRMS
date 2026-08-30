<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\District;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user bound to a role with the given permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function actor(string $roleSlug = 'central-admin', array $permissionSlugs = [], array $attrs = []): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);

        foreach ($permissionSlugs as $slug) {
            $role->permissions()->attach(Permission::factory()->create(['slug' => $slug])->id);
        }

        $user = User::factory()->create($attrs);
        $user->roles()->attach($role);

        return $user;
    }

    private function authToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_unauthenticated_users_cannot_access_user_endpoints(): void
    {
        $this->getJson('/api/v1/users')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->actor('cadet', ['cadets.view']);
        $token = $this->authToken($user);

        $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_super_admin_can_list_users(): void
    {
        User::factory()->create();
        User::factory()->create();

        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.view']));

        $response = $this->withToken($token)->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'meta' => ['total']]])
            ->assertJsonPath('data.meta.total', 3);
    }

    public function test_super_admin_can_create_user(): void
    {
        $role = Role::factory()->create(['slug' => 'cadet']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.create']));

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'username' => 'jane.doe',
            'email' => 'jane@example.com',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
            'role_ids' => [$role->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'jane@example.com');

        $this->assertDatabaseHas('users', ['username' => 'jane.doe', 'status' => 'active']);
        $this->assertDatabaseHas('user_roles', ['user_id' => User::where('username', 'jane.doe')->first()->id, 'role_id' => $role->id]);
    }

    public function test_creating_user_with_duplicate_email_fails(): void
    {
        $existing = User::factory()->create(['email' => 'dup@example.com']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.create']));

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'username' => 'jane.doe',
            'email' => $existing->email,
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_super_admin_can_view_user(): void
    {
        $target = User::factory()->create(['name' => 'View Me']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.view']));

        $this->withToken($token)->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'View Me');
    }

    public function test_super_admin_can_update_user(): void
    {
        $target = User::factory()->create(['name' => 'Old Name']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.update']));

        $this->withToken($token)->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'New Name']);
    }

    public function test_super_admin_can_disable_and_activate_user(): void
    {
        $target = User::factory()->create(['status' => 'active']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.update']));

        $this->withToken($token)->postJson("/api/v1/users/{$target->id}/disable")
            ->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'disabled']);

        $this->withToken($token)->postJson("/api/v1/users/{$target->id}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
    }

    public function test_super_admin_can_reset_password(): void
    {
        $target = User::factory()->create(['password' => Hash::make('OldPassword#123')]);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.reset-password']));

        $this->withToken($token)->postJson("/api/v1/users/{$target->id}/reset-password", [
            'password' => 'NewPassword#123',
            'password_confirmation' => 'NewPassword#123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword#123', $target->fresh()->password));
    }

    public function test_super_admin_can_delete_user(): void
    {
        $target = User::factory()->create(['name' => 'Delete Me']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.delete']));

        $this->withToken($token)->deleteJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = $this->actor(Role::SUPER_ADMIN, ['users.view'], ['name' => 'Logged In User', 'email' => 'me@example.com']);
        $token = $this->authToken($user);

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_super_admin_can_assign_roles(): void
    {
        $target = User::factory()->create();
        $role = Role::factory()->create(['slug' => 'cadet']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.assign-role']));

        $this->withToken($token)->putJson("/api/v1/users/{$target->id}/roles", [
            'role_ids' => [$role->id],
        ])->assertOk()
            ->assertJsonPath('data.roles.0.slug', 'cadet');

        $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $role->id]);
    }

    public function test_super_admin_can_assign_geography(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);
        $target = User::factory()->create();
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['users.assign-role']));

        $this->withToken($token)->putJson("/api/v1/users/{$target->id}/geography", [
            'province_id' => $province->id,
            'district_id' => $district->id,
        ])->assertOk()
            ->assertJsonPath('data.province_id', $province->id)
            ->assertJsonPath('data.district_id', $district->id);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
    }

    public function test_province_admin_sees_only_users_in_their_province(): void
    {
        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();

        User::factory()->create(['province_id' => $provinceA->id, 'name' => 'In Scope']);
        User::factory()->create(['province_id' => $provinceB->id, 'name' => 'Out of Scope']);

        $actor = $this->actor(Role::PROVINCE_ADMIN, ['users.view'], ['province_id' => $provinceA->id]);
        $token = $this->authToken($actor);

        $response = $this->withToken($token)->getJson('/api/v1/users');

        $response->assertOk()->assertJsonPath('data.meta.total', 2); // actor + in-scope user
        $names = collect($response->json('data.items'))->pluck('name');
        $this->assertTrue($names->contains('In Scope'));
        $this->assertFalse($names->contains('Out of Scope'));
    }

    public function test_province_admin_cannot_manage_user_outside_scope(): void
    {
        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();
        $districtB = District::factory()->create(['province_id' => $provinceB->id]);

        $actor = $this->actor(Role::PROVINCE_ADMIN, ['users.update'], ['province_id' => $provinceA->id]);
        $token = $this->authToken($actor);

        $outside = User::factory()->create(['province_id' => $provinceB->id, 'district_id' => $districtB->id]);

        $this->withToken($token)->patchJson("/api/v1/users/{$outside->id}", ['name' => 'Hacked'])
            ->assertStatus(422);
    }

    public function test_province_admin_cannot_assign_out_of_scope_geography(): void
    {
        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();
        $districtB = District::factory()->create(['province_id' => $provinceB->id]);

        $actor = $this->actor(Role::PROVINCE_ADMIN, ['users.assign-role'], ['province_id' => $provinceA->id]);
        $token = $this->authToken($actor);

        $target = User::factory()->create(['province_id' => $provinceA->id]);

        $this->withToken($token)->putJson("/api/v1/users/{$target->id}/geography", [
            'province_id' => $provinceB->id,
            'district_id' => $districtB->id,
        ])->assertStatus(422);
    }

    public function test_user_creation_is_audited(): void
    {
        $actor = $this->actor(Role::SUPER_ADMIN, ['users.create']);
        $token = $this->authToken($actor);

        $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Audited User',
            'username' => 'audited',
            'email' => 'audited@example.com',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
        ])->assertCreated();

        $user = User::where('username', 'audited')->first();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'created',
            'entity_type' => User::class,
            'entity_id' => (string) $user->id,
        ]);

        $log = AuditLog::where('action', 'created')->first();
        $this->assertSame($user->name, $log->new_values['name']);
    }
}
