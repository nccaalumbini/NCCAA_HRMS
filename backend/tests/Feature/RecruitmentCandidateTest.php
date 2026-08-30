<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Rank;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
