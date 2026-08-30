<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_assigned_a_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['slug' => 'cadet']);

        $user->roles()->attach($role);

        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->roles->contains('slug', 'cadet'));
    }

    public function test_has_permission_returns_true_when_role_bears_permission(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('slug', Role::CENTRAL_ADMIN)->first(),
        );

        $this->assertTrue($user->hasPermission('users.create'));
        $this->assertTrue($user->hasPermission('cadets.import'));
    }

    public function test_has_permission_returns_false_when_role_lacks_permission(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('slug', Role::CADET)->first(),
        );

        $this->assertFalse($user->hasPermission('users.create'));
        $this->assertFalse($user->hasPermission('cadets.delete'));
    }

    public function test_has_any_permission_returns_true_when_any_holds(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('slug', Role::CONTENT_MANAGER)->first(),
        );

        $this->assertTrue($user->hasAnyPermission(['users.create', 'skills.create']));
        $this->assertFalse($user->hasAnyPermission(['users.create', 'cadets.delete']));
    }
}
