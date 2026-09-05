<?php

namespace Tests\Feature;

use App\Mail\JobApplicationReceivedMailable;
use App\Models\District;
use App\Models\JobCategory;
use App\Models\NccTrainingCenter;
use App\Models\Province;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Support\BikramSambat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobPortalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function validPayload(JobCategory $category, Province $province, District $district): array
    {
        $center = NccTrainingCenter::factory()->create();

        return [
            'category_slug' => $category->slug,
            'full_name' => 'Anisha Gurung',
            'cadet_number' => 'ncc-0042',
            'phone' => '9800000042',
            'email' => 'anisha@example.test',
            'ncc_batch' => '3',
            'ncc_year' => (string) BikramSambat::year(),
            'division' => 'junior',
            'ncc_training_center_id' => $center->id,
            'school' => 'NCC Model School',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'address' => 'Gyaneswor, Kathmandu 12',
            'citizenship_number' => '1234-5678',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'uniform_photo_confirmed' => '1',
            'cv' => UploadedFile::fake()->create('cv.pdf', 30, 'application/pdf'),
            'proof_of_work_file' => UploadedFile::fake()->create('proof.pdf', 40, 'application/pdf'),
            'skill_data' => ['years_experience' => '1-3'],
        ];
    }

    /** @return array{JobCategory, Province, District} */
    private function createScene(): array
    {
        $category = JobCategory::factory()->create([
            'slug' => 'graphic-designer',
            'field_schema' => [
                'fields' => [
                    [
                        'key' => 'years_experience',
                        'label' => 'Years of experience',
                        'type' => 'select',
                        'required' => true,
                        'options' => ['0-1', '1-3', '3-5', '5+'],
                    ],
                ],
            ],
        ]);
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);

        return [$category, $province, $district];
    }

    public function test_categories_endpoint_lists_only_active_categories_with_meta(): void
    {
        JobCategory::factory()->create(['name' => 'Visible Role', 'slug' => 'visible-role']);
        JobCategory::factory()->inactive()->create(['name' => 'Hidden Role', 'slug' => 'hidden-role']);

        $response = $this->getJson('/api/v1/job-categories');

        $response->assertOk()
            ->assertJsonPath('data.meta.divisions', ['junior', 'senior'])
            ->assertJsonPath('data.meta.batch_ranges.junior', 51)
            ->assertJsonPath('data.meta.batch_ranges.senior', 20)
            ->assertJsonPath('data.meta.years.0', (string) BikramSambat::year())
            ->assertJsonCount(1, 'data.items');
    }

    public function test_category_endpoint_returns_skill_fields_for_slug(): void
    {
        [$category] = $this->createScene();

        $this->getJson("/api/v1/job-categories/{$category->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $category->slug)
            ->assertJsonPath('data.fields.0.key', 'years_experience');

        $this->getJson('/api/v1/job-categories/nope')
            ->assertStatus(422);
    }

    public function test_public_portal_submits_an_application_into_the_pipeline(): void
    {
        Mail::fake();
        Storage::fake('public');
        [$category, $province, $district] = $this->createScene();

        $response = $this->postJson('/api/v1/job-applications', $this->validPayload($category, $province, $district));

        $response->assertStatus(201)->assertJsonPath('data.category', $category->name);

        $candidate = RecruitmentCandidate::where('email', 'anisha@example.test')->firstOrFail();
        $this->assertSame('NCC-0042', $candidate->cadet_number);
        $this->assertSame('9800000042', $candidate->contact_number);
        $this->assertSame(RecruitmentCandidate::STATUS_APPLIED, $candidate->recruitment_status);
        $this->assertSame('portal', $candidate->source);
        $this->assertTrue($candidate->applicant_confirmed_uniform_photo);
        $this->assertSame('3', $candidate->ncc_batch);
        $this->assertSame((string) BikramSambat::year(), $candidate->ncc_year);
        $this->assertSame('junior', $candidate->division);
        $this->assertDatabaseHas('ncc_training_centers', ['id' => $candidate->ncc_training_center_id]);

        Storage::disk('public')->assertExists($candidate->photo_path);
        Storage::disk('public')->assertExists($candidate->cv_path);
        Storage::disk('public')->assertExists($candidate->portfolio_path_or_url);

        $this->assertDatabaseHas('recruitment_activities', [
            'recruitment_candidate_id' => $candidate->id,
            'activity_type' => 'portal_application',
        ]);
        Mail::assertSent(JobApplicationReceivedMailable::class);
    }

    public function test_citizenship_number_must_match_loose_format(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['citizenship_number'] = 'x';

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('citizenship_number');
    }

    public function test_district_must_belong_to_the_selected_province(): void
    {
        [$category, $province] = $this->createScene();
        $other = District::factory()->create();
        $payload = $this->validPayload($category, $province, $other);
        $payload['district_id'] = $other->id;

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('district_id');
    }

    public function test_proof_of_work_requires_a_file_or_a_url(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        unset($payload['proof_of_work_file']);

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof_of_work_url');
    }

    public function test_duplicate_email_is_rejected_strictly(): void
    {
        [$category, $province, $district] = $this->createScene();
        RecruitmentCandidate::factory()->create(['email' => 'anisha@example.test']);
        $payload = $this->validPayload($category, $province, $district);

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_duplicate_cadet_number_is_rejected_across_candidates_and_users(): void
    {
        [$category, $province, $district] = $this->createScene();
        User::factory()->create(['cadet_number' => 'NCC-0042']);
        $payload = $this->validPayload($category, $province, $district);

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cadet_number');
    }

    public function test_cadet_number_availability_check(): void
    {
        RecruitmentCandidate::factory()->create(['cadet_number' => 'NCC-0099']);

        $this->getJson('/api/v1/job-applications/check-cadet-number?cadet_number=NCC-0099')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/v1/job-applications/check-cadet-number?cadet_number=NCC-0100')
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    public function test_division_must_be_junior_or_senior(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['division'] = 'West Division';

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('division');
    }

    public function test_senior_division_rejects_a_batch_out_of_its_range(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['division'] = 'senior';
        $payload['ncc_batch'] = '35';

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('ncc_batch');
    }

    public function test_senior_division_accepts_its_maximum_batch(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['division'] = 'senior';
        $payload['ncc_batch'] = '20';

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(201);
    }

    public function test_junior_division_rejects_a_batch_above_51(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['ncc_batch'] = '52';

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('ncc_batch');
    }

    public function test_training_center_is_required_and_must_exist(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['ncc_training_center_id'] = null;

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('ncc_training_center_id');
    }

    public function test_training_centers_endpoint_matches_the_seeded_list_exactly(): void
    {
        $slugs = ['eastern_yangshila', 'mid_eastern_bardibas', 'mid_hattikhor', 'western_kohalpur'];
        foreach ($slugs as $slug) {
            NccTrainingCenter::factory()->create(['slug' => $slug, 'sort_order' => array_search($slug, $slugs, true)]);
        }

        $response = $this->getJson('/api/v1/ncc-training-centers')->assertOk();

        $this->assertSame($slugs, collect($response->json('data.items'))->pluck('slug')->all());
    }

    public function test_ncc_year_must_be_a_current_valid_bs_year(): void
    {
        [$category, $province, $district] = $this->createScene();
        $payload = $this->validPayload($category, $province, $district);
        $payload['ncc_year'] = '2024';

        $this->postJson('/api/v1/job-applications', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('ncc_year');
    }

    public function test_bikram_sambat_year_helper_matches_expected_ranges(): void
    {
        $this->assertCount(20, BikramSambat::yearRange());
        $this->assertSame((string) BikramSambat::year(), BikramSambat::yearRange()[0]);
        $this->assertSame(2083, BikramSambat::year('2026-09-01'));
    }
}
