<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * The roles and the permissions granted to each role.
     *
     * @var array<string, array<int, string>>
     */
    private array $rolePermissions = [
        Role::SUPER_ADMIN => ['*'],
        'central-admin' => [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.assign-role', 'users.reset-password',
            'cadets.view', 'cadets.create', 'cadets.update', 'cadets.delete', 'cadets.import', 'cadets.export',
            'skills.view', 'skills.create', 'skills.update', 'skills.delete',
            'recruitments.view', 'recruitments.create', 'recruitments.update', 'recruitments.delete', 'recruitments.publish',
            'applications.view', 'applications.review', 'applications.shortlist', 'applications.select',
            'notifications.view', 'notifications.create', 'notifications.send',
            'reports.view', 'reports.export',
            'geography.view', 'geography.manage',
            'roles.view', 'permissions.view',
        ],
        'province-admin' => [
            'users.view', 'users.create', 'users.update', 'users.assign-role', 'users.reset-password',
            'cadets.view', 'cadets.create', 'cadets.update', 'cadets.import', 'cadets.export',
            'skills.view', 'skills.create', 'skills.update',
            'recruitments.view', 'recruitments.create', 'recruitments.update', 'recruitments.publish',
            'applications.view', 'applications.review', 'applications.shortlist', 'applications.select',
            'notifications.view', 'notifications.create', 'notifications.send',
            'reports.view', 'reports.export',
            'geography.view',
        ],
        'district-admin' => [
            'users.view', 'users.create', 'users.update',
            'cadets.view', 'cadets.create', 'cadets.update', 'cadets.import', 'cadets.export',
            'skills.view', 'skills.create', 'skills.update',
            'recruitments.view', 'recruitments.create', 'recruitments.update', 'recruitments.publish',
            'applications.view', 'applications.review', 'applications.shortlist',
            'notifications.view', 'notifications.create', 'notifications.send',
            'reports.view',
            'geography.view',
        ],
        'recruitment-manager' => [
            'users.view',
            'cadets.view', 'cadets.export',
            'skills.view',
            'recruitments.view', 'recruitments.create', 'recruitments.update', 'recruitments.delete', 'recruitments.publish',
            'applications.view', 'applications.review', 'applications.shortlist', 'applications.select',
            'notifications.view', 'notifications.create', 'notifications.send',
            'reports.view',
        ],
        'content-manager' => [
            'skills.view', 'skills.create', 'skills.update', 'skills.delete',
            'notifications.view', 'notifications.create', 'notifications.send',
        ],
        'report-manager' => [
            'users.view',
            'cadets.view',
            'recruitments.view',
            'reports.view', 'reports.export',
        ],
        'cadet' => [
            'skills.view',
            'recruitments.view',
            'applications.view',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->rolePermissions as $slug => $permissions) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $this->nameFromSlug($slug),
                    'description' => $slug === Role::SUPER_ADMIN
                        ? 'Unrestricted access to all NCCAA HRMS resources.'
                        : null,
                ],
            );

            if ($permissions === ['*']) {
                $role->permissions()->sync(
                    Permission::pluck('id'),
                );
            } else {
                $role->permissions()->sync(
                    Permission::whereIn('slug', $permissions)->pluck('id'),
                );
            }
        }
    }

    /**
     * Convert a role slug into a readable name.
     */
    private function nameFromSlug(string $slug): string
    {
        if ($slug === Role::SUPER_ADMIN) {
            return 'Super Admin';
        }

        return str($slug)->replace('-', ' ')->title()->toString();
    }
}
