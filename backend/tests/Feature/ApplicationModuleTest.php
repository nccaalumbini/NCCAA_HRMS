<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\JobCategory;
use App\Models\NccTrainingCenter;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Rank;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationModuleTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, string> $permissions */
    private function actor(array $permissions, string $roleSlug = Role::RECRUITMENT_MANAGER): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::factory()->create(['slug' => $permission]));
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    /** @return array{RecruitmentCandidate, JobCategory, Province, District} */
    private function makeApplication(array $overrides = [], ?JobCategory $category = null): array
    {
        $province = Province::factory()->create();
        $district = District::factory()->for($province)->create();
        $category ??= JobCategory::factory()->create();
        $application = RecruitmentCandidate::factory()->portal($category)->create(array_merge([
            'province_id' => $province->id,
            'district_id' => $district->id,
        ], $overrides));

        return [$application, $category, $province, $district];
    }

    public function test_application_list_returns_only_portal_sources(): void
    {
        $actor = $this->actor(['applications.view']);
        [$application] = $this->makeApplication(['email' => 'portal.one@example.test']);
        RecruitmentCandidate::factory()->create(['source' => 'manual', 'email' => 'manual@example.test']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications');

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $application->id)
            ->assertJsonPath('data.items.0.stage', RecruitmentCandidate::STATUS_APPLIED);
    }

    public function test_application_list_can_filter_and_search(): void
    {
        $actor = $this->actor(['applications.view']);
        $category = JobCategory::factory()->create(['name' => 'Graphic Design']);
        [$target] = $this->makeApplication([
            'email' => 'filter.one@example.test',
            'full_name' => 'Asha Rai',
            'ncc_batch' => '12',
            'division' => 'junior',
        ], $category);
        [$other] = $this->makeApplication(['email' => 'filter.two@example.test', 'division' => 'senior']);

        $byName = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications?search=Asha')
            ->json('data.items');
        $byCategory = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications?skill_category_id='.$category->id)
            ->json('data.items');
        $byDivision = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications?division=senior')
            ->json('data.items');
        $byCadetNumber = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications?search='.$target->cadet_number)
            ->json('data.items');

        $this->assertCount(1, $byName);
        $this->assertCount(1, $byCategory);
        $this->assertCount(1, $byDivision);
        $this->assertEquals($target->id, $byCadetNumber[0]['id']);
        $this->assertEquals($other->id, $byDivision[0]['id']);
    }

    public function test_application_list_respects_district_scope(): void
    {
        $actor = $this->actor(['applications.view'], Role::DISTRICT_ADMIN);
        [$inScope, , $province, $district] = $this->makeApplication([], JobCategory::factory()->create());
        $actor->update(['province_id' => $province->id, 'district_id' => $district->id]);

        $outOfScopeProvince = Province::factory()->create();
        $outOfScopeDistrict = District::factory()->for($outOfScopeProvince)->create();
        $this->makeApplication(['province_id' => $outOfScopeProvince->id, 'district_id' => $outOfScopeDistrict->id, 'email' => 'outside.scope@example.test'], JobCategory::factory()->create());

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications');

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $inScope->id);
    }

    public function test_stage_can_be_advanced_with_skipping(): void
    {
        $actor = $this->actor(['applications.manage']);
        [$application] = $this->makeApplication();

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'offer_extended']);

        $response->assertOk()->assertJsonPath('data.stage', 'offer_extended');
        $this->assertDatabaseHas('recruitment_candidates', ['id' => $application->id, 'stage_updated_by' => $actor->id]);
        $this->assertNotNull($application->fresh()->stage_updated_at);
        $this->assertDatabaseHas('recruitment_activities', [
            'recruitment_candidate_id' => $application->id,
            'activity_type' => 'stage_changed',
        ]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'changed_application_stage']);
    }

    public function test_stage_change_requires_applications_manage_permission(): void
    {
        $actor = $this->actor(['applications.view']);
        [$application] = $this->makeApplication();

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'under_review']);

        $response->assertForbidden();
        $this->assertDatabaseHas('recruitment_candidates', ['id' => $application->id, 'recruitment_status' => RecruitmentCandidate::STATUS_APPLIED]);
    }

    public function test_rejected_stage_requires_rejection_reason(): void
    {
        $actor = $this->actor(['applications.manage']);
        [$application] = $this->makeApplication();

        $missing = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'rejected']);

        $missing->assertStatus(422)->assertJsonValidationErrors(['rejection_reason']);

        $withReason = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'rejected', 'rejection_reason' => 'Not enough experience']);

        $withReason->assertOk()->assertJsonPath('data.stage', 'rejected')->assertJsonPath('data.rejection_reason', 'Not enough experience');
        $this->assertDatabaseHas('recruitment_candidates', ['id' => $application->id, 'rejection_reason' => 'Not enough experience']);
    }

    public function test_rejected_application_can_only_be_reopened_with_confirmation(): void
    {
        $actor = $this->actor(['applications.manage']);
        [$application] = $this->makeApplication(['recruitment_status' => 'rejected', 'rejection_reason' => 'Incomplete documents']);

        $unconfirmed = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'under_review']);

        $unconfirmed->assertStatus(422)->assertJsonValidationErrors(['reopen']);

        $confirmed = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'under_review', 'reopen' => true]);

        $confirmed->assertOk()->assertJsonPath('data.stage', 'under_review');
        $this->assertNull($application->fresh()->rejection_reason);
    }

    public function test_interview_scheduled_accepts_optional_details(): void
    {
        $actor = $this->actor(['applications.manage']);
        [$application] = $this->makeApplication(['recruitment_status' => 'shortlisted']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", [
                'stage' => 'interview_scheduled',
                'interview_scheduled_at' => '2026-09-10T14:30:00',
                'interview_location' => 'Kathmandu Office',
                'interview_link' => 'https://meet.example.com/nccaa',
            ]);

        $response->assertOk()->assertJsonPath('data.interview_location', 'Kathmandu Office');
        $this->assertDatabaseHas('recruitment_candidates', ['id' => $application->id, 'interview_location' => 'Kathmandu Office']);
    }

    public function test_promoted_application_cannot_be_reopened_through_stage_endpoint(): void
    {
        $actor = $this->actor(['applications.manage']);
        [$application] = $this->makeApplication(['recruitment_status' => RecruitmentCandidate::STATUS_CONVERTED]);

        $direct = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => 'under_review']);

        $direct->assertStatus(422)->assertJsonValidationErrors(['stage']);

        $promotionTarget = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/applications/{$application->id}/stage", ['stage' => RecruitmentCandidate::STATUS_CONVERTED]);

        $promotionTarget->assertStatus(422)->assertJsonValidationErrors(['stage']);
    }

    public function test_promote_flow_from_application_creates_user_and_cadet(): void
    {
        $actor = $this->actor(['recruitment.promote-to-cadet']);
        $rank = Rank::factory()->create();
        [$application] = $this->makeApplication(['email' => 'promote.app@example.test']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/recruitment-candidates/{$application->id}/promote", [
                'cadet_number' => 'NCC-77777',
                'rank_id' => $rank->id,
                'username' => 'promote.app',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
            ]);

        $response->assertOk()->assertJsonPath('data.recruitment_status', RecruitmentCandidate::STATUS_CONVERTED);
        $this->assertDatabaseHas('users', ['cadet_number' => 'NCC-77777', 'email' => 'promote.app@example.test']);
        $this->assertDatabaseHas('cadets', ['cadet_number' => 'NCC-77777']);
        $this->assertDatabaseHas('recruitment_candidates', ['id' => $application->id, 'recruitment_status' => RecruitmentCandidate::STATUS_CONVERTED]);
    }

    public function test_note_can_be_added_with_permission(): void
    {
        $actor = $this->actor(['applications.notes.add']);
        [$application] = $this->makeApplication();

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/applications/{$application->id}/notes", ['note' => 'Called the applicant, very interested in the role.']);

        $response->assertCreated()->assertJsonPath('data.note', 'Called the applicant, very interested in the role.');
        $this->assertDatabaseHas('application_notes', ['application_id' => $application->id, 'user_id' => $actor->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'added_application_note']);
    }

    public function test_note_creation_requires_permission(): void
    {
        $actor = $this->actor(['applications.view']);
        [$application] = $this->makeApplication();

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/applications/{$application->id}/notes", ['note' => 'Should not be stored.']);

        $response->assertForbidden();
        $this->assertDatabaseCount('application_notes', 0);
    }

    public function test_note_is_internal_and_included_in_detail(): void
    {
        $actor = $this->actor(['applications.view', 'applications.notes.add']);
        [$application] = $this->makeApplication();
        $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/applications/{$application->id}/notes", ['note' => 'Internal only.']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson("/api/v1/applications/{$application->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.notes')
            ->assertJsonPath('data.notes.0.note', 'Internal only.');
    }

    public function test_application_detail_includes_duplicate_banner_matches(): void
    {
        $actor = $this->actor(['applications.view']);
        [$application] = $this->makeApplication(['cadet_number' => 'NCC-DUP-1', 'email' => 'dup.shared@example.test']);
        RecruitmentCandidate::factory()->create(['cadet_number' => 'NCC-OTHER', 'email' => 'dup.shared@example.test', 'source' => 'portal']);
        User::factory()->create(['email' => 'solo.dup@example.test', 'cadet_number' => 'NCC-DUP-1']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson("/api/v1/applications/{$application->id}");

        $response->assertOk();
        $reasons = collect($response->json('data.duplicates'))->pluck('reason')->all();
        $this->assertContains('cadet_number', $reasons);
        $this->assertContains('email', $reasons);
        $this->assertSame(2, count($response->json('data.duplicates')));
    }

    public function test_stats_returns_totals_by_stage_and_category(): void
    {
        $actor = $this->actor(['applications.view']);
        $category = JobCategory::factory()->create(['name' => 'Web Design']);
        $this->makeApplication(['recruitment_status' => 'applied', 'skill_category_id' => $category->id], $category);
        $this->makeApplication(['recruitment_status' => 'rejected', 'rejection_reason' => 'Position filled', 'skill_category_id' => $category->id, 'stage_updated_at' => now()], $category);
        $this->makeApplication([], JobCategory::factory()->create(['name' => 'Engineering']));

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson('/api/v1/applications/stats');

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_stage.0.stage', 'applied')
            ->assertJsonPath('data.by_category.0.name', 'Web Design');
        $this->assertIsNumeric($response->json('data.avg_time_to_decision_days'));
    }

    public function test_training_center_and_skill_fields_are_included_in_detail(): void
    {
        $actor = $this->actor(['applications.view']);
        $trainingCenter = NccTrainingCenter::factory()->create();
        $category = JobCategory::factory()->create(['name' => 'Graphic Design']);
        [$application] = $this->makeApplication([
            'ncc_training_center_id' => $trainingCenter->id,
            'skill_specific_data' => ['years_experience' => '3-5'],
            'applicant_confirmed_uniform_photo' => true,
        ], $category);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson("/api/v1/applications/{$application->id}");

        $response->assertOk()
            ->assertJsonPath('data.training_center.id', $trainingCenter->id)
            ->assertJsonPath('data.skill_category.name', 'Graphic Design')
            ->assertJsonPath('data.skill_specific_data.years_experience', '3-5')
            ->assertJsonPath('data.applicant_confirmed_uniform_photo', true);
    }
}
