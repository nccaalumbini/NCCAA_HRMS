<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmtpManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $roleSlug, array $permissionSlugs = []): User
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

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_super_admin_can_view_and_save_smtp_settings_with_masked_secrets(): void
    {
        $admin = $this->actor(Role::SUPER_ADMIN, ['email.settings.view', 'email.settings.manage']);
        $token = $admin->createToken('test')->plainTextToken;

        $saveResponse = $this->withToken($token)->putJson('/api/v1/email/settings', [
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => 'testuser123',
            'password' => 'superSecretPassword99',
            'from_email' => 'noreply@nccaa.org.np',
            'from_name' => 'NCCAA HRMS System',
        ]);

        $saveResponse->assertOk()
            ->assertJsonPath('data.host', 'smtp.mailtrap.io')
            ->assertJsonPath('data.port', 2525)
            ->assertJsonPath('data.password_masked', '••••••••••••')
            ->assertJsonMissing(['password' => 'superSecretPassword99']);

        // Verify encrypted in database
        $rawSetting = SystemSetting::where('key', 'smtp')->first();
        $this->assertNotNull($rawSetting);
        $this->assertStringNotContainsString('superSecretPassword99', $rawSetting->value);

        // Fetch settings via GET
        $getResponse = $this->withToken($token)->getJson('/api/v1/email/settings');
        $getResponse->assertOk()
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.password_masked', '••••••••••••')
            ->assertJsonMissing(['password' => 'superSecretPassword99']);
    }

    public function test_unauthorized_user_cannot_access_or_manage_smtp(): void
    {
        $unauthorized = $this->actor(Role::CADET, []);
        $token = $unauthorized->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/email/settings')
            ->assertStatus(403);

        $this->withToken($token)->putJson('/api/v1/email/settings', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'from_email' => 'test@example.com',
        ])->assertStatus(403);
    }

    public function test_smtp_test_connection_sends_email(): void
    {
        Mail::fake();

        $admin = $this->actor(Role::SUPER_ADMIN, ['email.smtp.test', 'email.settings.manage']);
        $token = $admin->createToken('test')->plainTextToken;

        SystemSetting::set('smtp', [
            'host' => 'smtp.test.local',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'testuser',
            'password' => 'secret',
            'from_email' => 'noreply@nccaa.org.np',
            'from_name' => 'NCCAA HRMS',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/email/smtp/test', [
            'recipient' => 'officer@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
