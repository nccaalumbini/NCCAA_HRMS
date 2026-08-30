<?php

namespace Tests\Feature;

use App\Models\Cadet;
use App\Models\CadetProfile;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Rank;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadetManagementTest extends TestCase
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

    public function test_unauthenticated_users_cannot_access_cadet_endpoints(): void
    {
        $this->getJson('/api/v1/cadets')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->actor('cadet', ['skills.view']);
        $this->withToken($this->authToken($user))->getJson('/api/v1/cadets')->assertForbidden();
    }

    public function test_super_admin_can_list_cadets(): void
    {
        Cadet::factory()->create();
        Cadet::factory()->create();
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.view']));

        $response = $this->withToken($token)->getJson('/api/v1/cadets');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'meta' => ['total']]])
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.cadet_number', Cadet::orderByDesc('created_at')->first()->cadet_number);
    }

    public function test_super_admin_can_create_cadet(): void
    {
        $rank = Rank::factory()->create();
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.create']));

        $response = $this->withToken($token)->postJson('/api/v1/cadets', [
            'cadet_number' => 'NCC-000111',
            'name' => 'Ramesh Shrestha',
            'rank_id' => $rank->id,
            'email' => 'cadet@example.com',
            'phone' => '9812345678',
            'profile' => [
                'gender' => 'male',
                'blood_group' => 'A+',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cadet_number', 'NCC-000111')
            ->assertJsonPath('data.rank.short_code', $rank->short_code)
            ->assertJsonPath('data.profile.gender', 'male');

        $this->assertDatabaseHas('cadets', ['cadet_number' => 'NCC-000111']);
        $cadet = Cadet::where('cadet_number', 'NCC-000111')->first();
        $this->assertDatabaseHas('cadet_profiles', ['cadet_id' => $cadet->id, 'blood_group' => 'A+']);
    }

    public function test_creating_cadet_with_duplicate_number_fails(): void
    {
        Cadet::factory()->create(['cadet_number' => 'NCC-000222']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.create']));

        $this->withToken($token)->postJson('/api/v1/cadets', [
            'cadet_number' => 'NCC-000222',
            'name' => 'Duplicate',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_super_admin_can_view_cadet(): void
    {
        $cadet = Cadet::factory()->create(['name' => 'View Me']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.view']));

        $this->withToken($token)->getJson("/api/v1/cadets/{$cadet->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'View Me');
    }

    public function test_super_admin_can_update_cadet_and_profile(): void
    {
        $cadet = Cadet::factory()->create(['name' => 'Old Name']);
        CadetProfile::factory()->create(['cadet_id' => $cadet->id, 'gender' => 'male']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.update']));

        $this->withToken($token)->patchJson("/api/v1/cadets/{$cadet->id}", [
            'name' => 'New Name',
            'profile' => ['gender' => 'female', 'blood_group' => 'O+'],
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.profile.gender', 'female')
            ->assertJsonPath('data.profile.blood_group', 'O+');

        $this->assertDatabaseHas('cadets', ['id' => $cadet->id, 'name' => 'New Name']);
        $this->assertDatabaseHas('cadet_profiles', ['cadet_id' => $cadet->id, 'gender' => 'female']);
    }

    public function test_super_admin_can_delete_cadet(): void
    {
        $cadet = Cadet::factory()->create();
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.delete']));

        $this->withToken($token)->deleteJson("/api/v1/cadets/{$cadet->id}")->assertOk();

        $this->assertSoftDeleted('cadets', ['id' => $cadet->id]);
    }

    public function test_search_filters_by_name(): void
    {
        Cadet::factory()->create(['name' => 'Alpha Target']);
        Cadet::factory()->create(['name' => 'Beta Other']);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.view']));

        $response = $this->withToken($token)->getJson('/api/v1/cadets?search=Alpha');

        $response->assertOk()->assertJsonPath('data.meta.total', 1);
        $names = collect($response->json('data.items'))->pluck('name');
        $this->assertTrue($names->contains('Alpha Target'));
        $this->assertFalse($names->contains('Beta Other'));
    }

    public function test_filter_by_rank(): void
    {
        $rankA = Rank::factory()->create();
        $rankB = Rank::factory()->create();
        Cadet::factory()->create(['rank_id' => $rankA->id]);
        Cadet::factory()->create(['rank_id' => $rankA->id]);
        Cadet::factory()->create(['rank_id' => $rankB->id]);
        $token = $this->authToken($this->actor(Role::SUPER_ADMIN, ['cadets.view']));

        $this->withToken($token)->getJson("/api/v1/cadets?rank_id={$rankA->id}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_province_admin_sees_only_cadets_in_their_province(): void
    {
        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();
        Cadet::factory()->create(['province_id' => $provinceA->id, 'name' => 'In Scope']);
        Cadet::factory()->create(['province_id' => $provinceB->id, 'name' => 'Out of Scope']);

        $actor = $this->actor(Role::PROVINCE_ADMIN, ['cadets.view'], ['province_id' => $provinceA->id]);
        $token = $this->authToken($actor);

        $response = $this->withToken($token)->getJson('/api/v1/cadets');

        $response->assertOk()->assertJsonPath('data.meta.total', 1);
        $names = collect($response->json('data.items'))->pluck('name');
        $this->assertTrue($names->contains('In Scope'));
        $this->assertFalse($names->contains('Out of Scope'));
    }

    public function test_province_admin_cannot_manage_cadet_outside_scope(): void
    {
        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();
        $actor = $this->actor(Role::PROVINCE_ADMIN, ['cadets.update'], ['province_id' => $provinceA->id]);
        $token = $this->authToken($actor);

        $outside = Cadet::factory()->create(['province_id' => $provinceB->id]);

        $this->withToken($token)->patchJson("/api/v1/cadets/{$outside->id}", ['name' => 'Hacked'])
            ->assertStatus(422);
    }

    public function test_cadet_creation_is_audited(): void
    {
        $actor = $this->actor(Role::SUPER_ADMIN, ['cadets.create']);
        $token = $this->authToken($actor);

        $this->withToken($token)->postJson('/api/v1/cadets', [
            'cadet_number' => 'NCC-000333',
            'name' => 'Audited Cadet',
        ])->assertCreated();

        $cadet = Cadet::where('cadet_number', 'NCC-000333')->first();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'created',
            'entity_type' => Cadet::class,
            'entity_id' => (string) $cadet->id,
        ]);
    }
}
