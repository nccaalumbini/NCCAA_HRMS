<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'users' => [
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.assign-role',
                'users.reset-password',
            ],
            'cadets' => [
                'cadets.view',
                'cadets.create',
                'cadets.update',
                'cadets.delete',
                'cadets.import',
                'cadets.export',
            ],
            'skills' => [
                'skills.view',
                'skills.create',
                'skills.update',
                'skills.delete',
            ],
            'recruitments' => [
                'recruitments.view',
                'recruitments.create',
                'recruitments.update',
                'recruitments.delete',
                'recruitments.publish',
            ],
            'applications' => [
                'applications.view',
                'applications.review',
                'applications.shortlist',
                'applications.select',
            ],
            'notifications' => [
                'notifications.view',
                'notifications.create',
                'notifications.send',
            ],
            'reports' => [
                'reports.view',
                'reports.export',
            ],
            'geography' => [
                'geography.view',
                'geography.manage',
            ],
            'roles' => [
                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',
            ],
            'permissions' => [
                'permissions.view',
            ],
        ];

        foreach ($permissions as $group => $slugs) {
            foreach ($slugs as $slug) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $this->titleFromSlug($slug),
                        'group' => $group,
                    ],
                );
            }
        }
    }

    /**
     * Convert a dotted slug into a human-friendly title.
     */
    private function titleFromSlug(string $slug): string
    {
        [$group, $action] = explode('.', $slug);

        return ucwords($action.' '.$group);
    }
}
