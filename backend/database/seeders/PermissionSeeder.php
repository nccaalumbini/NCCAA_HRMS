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
            'recruitment' => [
                'recruitment.create',
                'recruitment.view',
                'recruitment.update',
                'recruitment.delete',
                'recruitment.import',
                'recruitment.action',
                'recruitment.promote-to-cadet',
                'recruitment.communication.send',
                'recruitment.communication.view',
            ],
            'applications' => [
                'applications.view',
                'applications.review',
                'applications.shortlist',
                'applications.select',
                'applications.manage',
                'applications.notes.add',
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
            'email' => [
                'email.settings.view',
                'email.settings.manage',
                'email.smtp.test',
                'email.campaigns.view',
                'email.campaigns.create',
                'email.campaigns.send',
                'email.campaigns.cancel',
                'email.delivery.view',
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
        $parts = explode('.', $slug);
        $group = array_shift($parts);
        $action = implode(' ', $parts);

        return ucwords($action.' '.$group);
    }
}
