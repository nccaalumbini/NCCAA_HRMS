<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\LocalLevel;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographicHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $permissionSlugs = []): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super Admin']);

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

    private function authToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_creating_user_with_district_outside_province_fails(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();
        $districtFromProvince2 = District::factory()->create(['province_id' => $province2->id]);

        $admin = $this->actor(['users.create']);
        $token = $this->authToken($admin);

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Geo Test User',
            'username' => 'geo.test.user',
            'email' => 'geouser@example.com',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
            'province_id' => $province1->id,
            'district_id' => $districtFromProvince2->id, // Mismatch!
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['district_id']);
    }

    public function test_creating_cadet_with_mismatched_hierarchy_fails(): void
    {
        $province = Province::factory()->create();
        $district1 = District::factory()->create(['province_id' => $province->id]);
        $district2 = District::factory()->create(['province_id' => $province->id]);

        $localLevelFromDistrict2 = LocalLevel::factory()->create(['district_id' => $district2->id]);
        $ward = Ward::factory()->create(['local_level_id' => $localLevelFromDistrict2->id]);

        $admin = $this->actor(['cadets.create']);
        $token = $this->authToken($admin);

        // Mismatched local level for district1
        $response = $this->withToken($token)->postJson('/api/v1/cadets', [
            'cadet_number' => 'NCC-GEO-001',
            'name' => 'Geo Cadet',
            'province_id' => $province->id,
            'district_id' => $district1->id,
            'local_level_id' => $localLevelFromDistrict2->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['local_level_id']);

        // Mismatched ward
        $otherLocalLevel = LocalLevel::factory()->create(['district_id' => $district1->id]);
        $response2 = $this->withToken($token)->postJson('/api/v1/cadets', [
            'cadet_number' => 'NCC-GEO-002',
            'name' => 'Geo Cadet 2',
            'province_id' => $province->id,
            'district_id' => $district1->id,
            'local_level_id' => $otherLocalLevel->id,
            'ward_id' => $ward->id, // belongs to localLevelFromDistrict2, not otherLocalLevel!
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['ward_id']);
    }

    public function test_creating_cadet_with_valid_hierarchy_succeeds(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);
        $localLevel = LocalLevel::factory()->create(['district_id' => $district->id]);
        $ward = Ward::factory()->create(['local_level_id' => $localLevel->id]);

        $admin = $this->actor(['cadets.create']);
        $token = $this->authToken($admin);

        $response = $this->withToken($token)->postJson('/api/v1/cadets', [
            'cadet_number' => 'NCC-GEO-003',
            'name' => 'Valid Geo Cadet',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'local_level_id' => $localLevel->id,
            'ward_id' => $ward->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cadet_number', 'NCC-GEO-003');
    }
}
