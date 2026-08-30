<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Rank;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $roleSlug, array $permissionSlugs = [], array $attrs = []): User
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

        $user = User::factory()->create($attrs);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function authToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_province_admin_only_sees_candidates_within_their_province(): void
    {
        $provinceA = Province::factory()->create();
        $provinceB = Province::factory()->create();

        RecruitmentCandidate::factory()->create([
            'province_id' => $provinceA->id,
            'full_name' => 'Candidate in Province A',
            'contact_number' => '9800000010',
        ]);

        RecruitmentCandidate::factory()->create([
            'province_id' => $provinceB->id,
            'full_name' => 'Candidate in Province B',
            'contact_number' => '9800000011',
        ]);

        $provinceAdmin = $this->actor(Role::PROVINCE_ADMIN, ['recruitment.view'], [
            'province_id' => $provinceA->id,
        ]);

        $token = $this->authToken($provinceAdmin);

        $response = $this->withToken($token)->getJson('/api/v1/recruitment-candidates');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.full_name', 'Candidate in Province A');
    }

    public function test_district_admin_cannot_update_candidate_in_another_district(): void
    {
        $province = Province::factory()->create();
        $districtA = District::factory()->create(['province_id' => $province->id]);
        $districtB = District::factory()->create(['province_id' => $province->id]);

        $candidateB = RecruitmentCandidate::factory()->create([
            'province_id' => $province->id,
            'district_id' => $districtB->id,
            'contact_number' => '9800000020',
        ]);

        $districtAdminA = $this->actor(Role::DISTRICT_ADMIN, ['recruitment.action'], [
            'province_id' => $province->id,
            'district_id' => $districtA->id,
        ]);

        $token = $this->authToken($districtAdminA);

        $response = $this->withToken($token)->patchJson("/api/v1/recruitment-candidates/{$candidateB->id}", [
            'recruitment_status' => RecruitmentCandidate::STATUS_CV_REQUESTED,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['candidate']);
    }

    public function test_cannot_promote_already_converted_candidate(): void
    {
        $rank = Rank::factory()->create();
        $admin = $this->actor(Role::SUPER_ADMIN, ['recruitment.promote-to-cadet']);
        $token = $this->authToken($admin);

        $candidate = RecruitmentCandidate::factory()->create([
            'recruitment_status' => RecruitmentCandidate::STATUS_CONVERTED,
            'contact_number' => '9800000030',
        ]);

        $response = $this->withToken($token)->postJson("/api/v1/recruitment-candidates/{$candidate->id}/promote", [
            'cadet_number' => 'NCC-DUP-PROMOTE',
            'rank_id' => $rank->id,
            'username' => 'candidate.converted',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['candidate']);
    }

    public function test_promotion_assigns_cadet_role_and_creates_cadet_record(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);
        $rank = Rank::factory()->create();
        Role::firstOrCreate(['slug' => Role::CADET], ['name' => 'Cadet']);

        $admin = $this->actor(Role::SUPER_ADMIN, ['recruitment.promote-to-cadet']);
        $token = $this->authToken($admin);

        $candidate = RecruitmentCandidate::factory()->create([
            'full_name' => 'Promotion Test User',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'contact_number' => '9800000040',
            'email' => 'promote.me@example.com',
            'recruitment_status' => RecruitmentCandidate::STATUS_IMPORTED,
        ]);

        $response = $this->withToken($token)->postJson("/api/v1/recruitment-candidates/{$candidate->id}/promote", [
            'cadet_number' => 'NCC-PROMOTED-001',
            'rank_id' => $rank->id,
            'username' => 'promoted.user',
            'password' => 'Password#123456',
            'password_confirmation' => 'Password#123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recruitment_status', RecruitmentCandidate::STATUS_CONVERTED);

        $createdUser = User::where('cadet_number', 'NCC-PROMOTED-001')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole(Role::CADET));

        $this->assertDatabaseHas('cadets', [
            'cadet_number' => 'NCC-PROMOTED-001',
            'user_id' => $createdUser->id,
            'rank_id' => $rank->id,
        ]);
    }
}
