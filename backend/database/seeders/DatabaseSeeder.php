<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RankSeeder::class,
            GeographySeeder::class,
        ]);

        $this->createSuperAdmin();
    }

    /**
     * Create the initial super admin account.
     */
    private function createSuperAdmin(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@nccaa.gov.np'],
            [
                'name' => 'Super Admin',
                'username' => 'admin',
                'phone' => '9800000000',
                'password' => 'password',
                'status' => 'active',
            ],
        );

        $superAdminRole = Role::where('slug', Role::SUPER_ADMIN)->first();

        if ($superAdminRole !== null) {
            $admin->roles()->syncWithoutDetaching($superAdminRole->id);
        }
    }
}
