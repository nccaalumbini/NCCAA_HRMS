<?php

namespace Tests\Feature;

use App\Mail\CampaignMailable;
use App\Models\District;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Rank;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecruitmentCandidateTest extends TestCase
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

    public function test_csv_import_skips_existing_user_phone_numbers(): void
    {
        $actor = $this->actor(['recruitment.import']);
        User::factory()->create(['phone' => '9800000001']);
        $file = UploadedFile::fake()->createWithContent('candidates.csv', "Full Name,Contact Number,E-Mail,Skills\nAsha Rai,9800000001,asha@example.test,IT\nBikash Lama,9800000002,bikash@example.test,Design");

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->post('/api/v1/recruitment-candidates/import', ['file' => $file]);

        $response->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.duplicates', 1);
        $this->assertDatabaseHas('recruitment_candidates', ['full_name' => 'Bikash Lama', 'contact_number' => '9800000002']);
    }

    public function test_authorized_admin_can_promote_candidate_to_linked_user_and_cadet(): void
    {
        $actor = $this->actor(['recruitment.promote-to-cadet']);
        $rank = Rank::factory()->create();
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000003', 'email' => 'candidate@example.test']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/recruitment-candidates/{$candidate->id}/promote", [
                'cadet_number' => 'NCC-00001',
                'rank_id' => $rank->id,
                'username' => 'candidate.one',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
            ]);

        $response->assertOk()->assertJsonPath('data.recruitment_status', RecruitmentCandidate::STATUS_CONVERTED);
        $this->assertDatabaseHas('users', ['cadet_number' => 'NCC-00001', 'phone' => '9800000003']);
        $this->assertDatabaseHas('cadets', ['cadet_number' => 'NCC-00001', 'phone' => '9800000003']);
    }

    public function test_can_send_email_communication_to_candidate_with_email(): void
    {
        Mail::fake();

        $actor = $this->actor(['recruitment.communication.send']);
        $candidate = RecruitmentCandidate::factory()->create([
            'email' => 'outreach@example.com',
            'contact_number' => '9800000004',
        ]);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/recruitment-candidates/{$candidate->id}/communication", [
                'channel' => 'email',
                'subject' => 'Welcome to NCCAA',
                'message' => 'You have been shortlisted for an interview.',
            ]);

        $response->assertOk()->assertJsonPath('data.channel', 'email')->assertJsonPath('data.status', 'sent');

        Mail::assertSent(CampaignMailable::class);
        $this->assertDatabaseHas('recruitment_activities', [
            'recruitment_candidate_id' => $candidate->id,
            'activity_type' => 'email_sent',
            'performed_by_user_id' => $actor->id,
        ]);
    }

    public function test_cannot_send_email_communication_to_candidate_without_email(): void
    {
        Mail::fake();

        $actor = $this->actor(['recruitment.communication.send']);
        $candidate = RecruitmentCandidate::factory()->create([
            'email' => null,
            'contact_number' => '9800000005',
        ]);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/recruitment-candidates/{$candidate->id}/communication", [
                'channel' => 'email',
                'subject' => 'Welcome to NCCAA',
                'message' => 'Hello there.',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['candidate']);
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('recruitment_activities', [
            'recruitment_candidate_id' => $candidate->id,
            'activity_type' => 'email_sent',
        ]);
    }

    public function test_email_transport_failure_is_surfaced_as_error_response(): void
    {
        Mail::spy();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection to smtp rejected'));

        $actor = $this->actor(['recruitment.communication.send']);
        $candidate = RecruitmentCandidate::factory()->create([
            'email' => 'fails@example.com',
            'contact_number' => '9800000006',
        ]);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson("/api/v1/recruitment-candidates/{$candidate->id}/communication", [
                'channel' => 'email',
                'subject' => 'Welcome to NCCAA',
                'message' => 'Hello there.',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['message']);
        $this->assertDatabaseHas('recruitment_activities', [
            'recruitment_candidate_id' => $candidate->id,
            'activity_type' => 'email_failed',
            'performed_by_user_id' => $actor->id,
        ]);
    }

    public function test_authorized_admin_can_create_recruitment_candidate(): void
    {
        $actor = $this->actor(['recruitment.create']);
        $province = Province::factory()->create();
        $district = District::factory()->for($province)->create();

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recruitment-candidates', [
                'full_name' => 'Sita Gurung',
                'gender' => 'female',
                'province_id' => $province->id,
                'district_id' => $district->id,
                'local_level' => 'Pokhara Metropolitan City',
                'ward_number' => 12,
                'contact_number' => '9800000010',
                'email' => 'sita@example.test',
                'skills' => ['First Aid', 'Leadership'],
                'priority_score' => 85,
                'notes' => 'Strong referral from brigade.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Sita Gurung')
            ->assertJsonPath('data.recruitment_status', RecruitmentCandidate::STATUS_IMPORTED)
            ->assertJsonPath('data.priority_score', 85);

        $this->assertDatabaseHas('recruitment_candidates', [
            'full_name' => 'Sita Gurung',
            'contact_number' => '9800000010',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'created_candidate']);
    }

    public function test_create_candidate_requires_recruitment_create_permission(): void
    {
        $actor = $this->actor(['recruitment.view']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recruitment-candidates', [
                'full_name' => 'No Permission',
                'contact_number' => '9800000011',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('recruitment_candidates', ['full_name' => 'No Permission']);
    }

    public function test_create_rejects_duplicate_contact_number(): void
    {
        $actor = $this->actor(['recruitment.create']);
        RecruitmentCandidate::factory()->create(['contact_number' => '9800000012']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recruitment-candidates', [
                'full_name' => 'Duplicate Phone',
                'contact_number' => '9800000012',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['contact_number']);
    }

    public function test_authorized_admin_can_view_recruitment_candidate(): void
    {
        $actor = $this->actor(['recruitment.view']);
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000013']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->getJson("/api/v1/recruitment-candidates/{$candidate->id}");

        $response->assertOk()->assertJsonPath('data.id', $candidate->id)->assertJsonPath('data.full_name', $candidate->full_name);
    }

    public function test_authorized_admin_can_update_candidate_details(): void
    {
        $actor = $this->actor(['recruitment.update']);
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000014', 'email' => 'old@example.test']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/recruitment-candidates/{$candidate->id}", [
                'full_name' => 'Renamed Candidate',
                'email' => 'renamed@example.test',
                'skills' => ['Leadership', 'Mountaineering'],
                'priority_score' => 90,
                'notes' => 'Interview scheduled.',
            ]);

        $response->assertOk()->assertJsonPath('data.full_name', 'Renamed Candidate')->assertJsonPath('data.notes', 'Interview scheduled.');
        $this->assertDatabaseHas('recruitment_candidates', [
            'id' => $candidate->id, 'full_name' => 'Renamed Candidate',
            'email' => 'renamed@example.test', 'priority_score' => 90,
        ]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'updated_candidate']);
    }

    public function test_update_candidate_to_outreach_sent_records_outreach_timestamp(): void
    {
        $actor = $this->actor(['recruitment.update']);
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000015']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/recruitment-candidates/{$candidate->id}", ['recruitment_status' => RecruitmentCandidate::STATUS_OUTREACH_SENT]);

        $response->assertOk();
        $this->assertNotNull($candidate->fresh()->outreach_sent_at);
    }

    public function test_authorized_admin_can_delete_candidate(): void
    {
        $actor = $this->actor(['recruitment.delete']);
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000016']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->deleteJson("/api/v1/recruitment-candidates/{$candidate->id}");

        $response->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('recruitment_candidates', ['id' => $candidate->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'deleted_candidate']);
    }

    public function test_delete_candidate_requires_recruitment_delete_permission(): void
    {
        $actor = $this->actor(['recruitment.view']);
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000017']);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->deleteJson("/api/v1/recruitment-candidates/{$candidate->id}");

        $response->assertForbidden();
    }

    public function test_district_admin_cannot_create_candidate_outside_district_scope(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->for($province)->create();
        $otherProvince = Province::factory()->create();
        $otherDistrict = District::factory()->for($otherProvince)->create();

        $actor = $this->actor(['recruitment.create'], Role::DISTRICT_ADMIN);
        $actor->update(['province_id' => $province->id, 'district_id' => $district->id]);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recruitment-candidates', [
                'full_name' => 'Out of Scope',
                'contact_number' => '9800000018',
                'province_id' => $otherProvince->id,
                'district_id' => $otherDistrict->id,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['province_id']);
        $this->assertDatabaseMissing('recruitment_candidates', ['full_name' => 'Out of Scope']);
    }

    public function test_district_admin_cannot_delete_candidate_outside_district_scope(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->for($province)->create();
        $otherProvince = Province::factory()->create();
        $otherDistrict = District::factory()->for($otherProvince)->create();

        $actor = $this->actor(['recruitment.delete'], Role::DISTRICT_ADMIN);
        $actor->update(['province_id' => $province->id, 'district_id' => $district->id]);

        $candidate = RecruitmentCandidate::factory()->create([
            'province_id' => $otherProvince->id, 'district_id' => $otherDistrict->id, 'contact_number' => '9800000019',
        ]);

        $response = $this->withToken($actor->createToken('test')->plainTextToken)
            ->deleteJson("/api/v1/recruitment-candidates/{$candidate->id}");

        $response->assertStatus(422)->assertJsonValidationErrors(['candidate']);
        $this->assertNotSoftDeleted('recruitment_candidates', ['id' => $candidate->id]);
    }

    public function test_check_contact_reports_duplicate_across_users_and_candidates(): void
    {
        $actor = $this->actor(['recruitment.create']);
        User::factory()->create(['phone' => '9800000020']);
        $candidate = RecruitmentCandidate::factory()->create(['contact_number' => '9800000021']);

        $token = $actor->createToken('test')->plainTextToken;
        $usedByUser = $this->withToken($token)->getJson('/api/v1/recruitment-candidates/check-contact?contact_number=9800000020')->assertOk()->json('data.taken');
        $usedByCandidate = $this->withToken($token)->getJson('/api/v1/recruitment-candidates/check-contact?contact_number=9800000021')->assertOk()->json('data.taken');
        $available = $this->withToken($token)->getJson('/api/v1/recruitment-candidates/check-contact?contact_number=9800000022')->assertOk()->json('data.taken');

        $this->assertTrue($usedByUser);
        $this->assertTrue($usedByCandidate);
        $this->assertFalse($available);
    }
}
