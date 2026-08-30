<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'permission:cadets.create'])
            ->get('/api/v1/_test/authorize', fn () => response()->json(['ok' => true]));
    }

    public function test_user_with_permission_is_allowed(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::factory()->create(['slug' => 'cadets.create'])->id);
        $user->roles()->attach($role);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/_test/authorize')->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/_test/authorize')->assertForbidden();
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $this->getJson('/api/v1/_test/authorize')->assertStatus(401);
    }
}
