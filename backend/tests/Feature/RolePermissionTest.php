<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_seeder_creates_all_standard_roles(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->assertDatabaseCount('roles', 8);

        foreach ([
            Role::SUPER_ADMIN,
            Role::CENTRAL_ADMIN,
            Role::PROVINCE_ADMIN,
            Role::DISTRICT_ADMIN,
            Role::RECRUITMENT_MANAGER,
            Role::CONTENT_MANAGER,
            Role::REPORT_MANAGER,
            Role::CADET,
        ] as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug]);
        }
    }

    public function test_super_admin_holds_every_permission(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $superAdmin = Role::where('slug', Role::SUPER_ADMIN)->first();

        $this->assertCount(54, $superAdmin->permissions);
    }

    public function test_permission_seeder_creates_granular_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseCount('permissions', 54);
        $this->assertDatabaseHas('permissions', ['slug' => 'cadets.import']);
        $this->assertDatabaseHas('permissions', ['slug' => 'applications.shortlist']);
        $this->assertDatabaseHas('permissions', ['slug' => 'users.assign-role']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.create']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.update']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.delete']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.import']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.promote-to-cadet']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.communication.send']);
        $this->assertDatabaseHas('permissions', ['slug' => 'recruitment.communication.view']);
        $this->assertDatabaseHas('permissions', ['slug' => 'email.campaigns.create']);
        $this->assertDatabaseHas('permissions', ['slug' => 'email.settings.manage']);
    }

    public function test_role_can_be_assigned_permissions(): void
    {
        $role = Role::factory()->create();

        $role->permissions()->sync([
            Permission::factory()->create(['slug' => 'cadets.view'])->id,
            Permission::factory()->create(['slug' => 'cadets.create'])->id,
        ]);

        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains('slug', 'cadets.view'));
    }
}
