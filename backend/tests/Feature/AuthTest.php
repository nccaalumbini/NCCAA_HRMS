<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@example.com',
            'username' => 'adminuser',
            'password' => 'Password#12345',
            'status' => 'active',
        ], $overrides));
    }

    public function test_user_can_login_with_email(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@example.com',
            'password' => 'Password#12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['user', 'token', 'expires_at'],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_user_can_login_with_username(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'adminuser',
            'password' => 'Password#12345',
        ]);

        $response->assertOk()->assertJsonPath('data.user.username', 'adminuser');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(['status' => 'disabled']);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@example.com',
            'password' => 'Password#12345',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_me_returns_user_with_roles_and_permissions(): void
    {
        $role = Role::factory()->create(['slug' => 'cadet']);
        $role->permissions()->attach(
            Permission::factory()->create(['slug' => 'skills.view'])->id,
        );

        $user = $this->makeUser();
        $user->roles()->attach($role);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com')
            ->assertJsonPath('data.roles', ['cadet'])
            ->assertJsonPath('data.permissions', ['skills.view']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_refresh_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()->assertJsonPath('data', null);
    }
}
