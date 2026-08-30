<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Cadet;
use App\Models\EmailCampaign;
use App\Models\EmailRecipient;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use App\Services\SmtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailCampaignTest extends TestCase
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

    public function test_authorized_admin_can_query_recipients_within_scope(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();

        $cadet1 = Cadet::factory()->create([
            'province_id' => $province1->id,
            'name' => 'Cadet In Scope',
            'email' => 'cadet1@example.com',
        ]);
        $cadet2 = Cadet::factory()->create([
            'province_id' => $province2->id,
            'name' => 'Cadet Out of Scope',
            'email' => 'cadet2@example.com',
        ]);

        $provinceAdmin = $this->actor(Role::PROVINCE_ADMIN, ['email.campaigns.create'], [
            'province_id' => $province1->id,
        ]);
        $token = $provinceAdmin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/email/recipients');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.email', 'cadet1@example.com');
    }

    public function test_creating_campaign_with_out_of_scope_recipient_fails(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();

        $cadet2 = Cadet::factory()->create([
            'province_id' => $province2->id,
            'name' => 'Cadet In Province 2',
            'email' => 'cadet2@example.com',
        ]);

        $provinceAdmin1 = $this->actor(Role::PROVINCE_ADMIN, ['email.campaigns.create'], [
            'province_id' => $province1->id,
        ]);
        $token = $provinceAdmin1->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/email/campaigns', [
            'title' => 'Cross Province Campaign Attempt',
            'subject' => 'Notice',
            'body_html' => '<p>Hello Cadet</p>',
            'cadet_ids' => [$cadet2->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cadet_ids']);
    }

    public function test_create_and_send_campaign_dispatches_jobs_and_sanitizes_html(): void
    {
        Queue::fake();

        $province = Province::factory()->create();
        $admin = $this->actor(Role::SUPER_ADMIN, ['email.campaigns.create', 'email.campaigns.send']);
        $token = $admin->createToken('test')->plainTextToken;

        $cadet = Cadet::factory()->create([
            'province_id' => $province->id,
            'name' => 'Recipient Cadet',
            'email' => 'recipient@example.com',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/email/campaigns', [
            'title' => 'Annual Camp Notice',
            'subject' => 'Important: Annual Camp',
            'body_html' => '<p>Hello {{name}}</p><script>alert("hacked")</script>',
            'cadet_ids' => [$cadet->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_recipients', 1);

        $campaignId = $response->json('data.id');
        $createdCampaign = EmailCampaign::find($campaignId);
        $this->assertStringNotContainsString('<script>', $createdCampaign->body_html);

        // Send campaign
        $sendResponse = $this->withToken($token)->postJson("/api/v1/email/campaigns/{$campaignId}/send");
        $sendResponse->assertOk()
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(SendCampaignEmailJob::class, 1);
    }

    public function test_send_campaign_job_executes_and_updates_delivery_status(): void
    {
        Mail::fake();

        $campaign = EmailCampaign::create([
            'title' => 'Camp Briefing',
            'subject' => 'Camp Briefing for {{name}}',
            'body_html' => '<p>Welcome {{name}} ({{email}})</p>',
            'total_recipients' => 1,
            'status' => EmailCampaign::STATUS_QUEUED,
        ]);

        $recipient = EmailRecipient::create([
            'email_campaign_id' => $campaign->id,
            'email' => 'cadet.unit@example.com',
            'name' => 'Unit Cadet',
            'status' => EmailRecipient::STATUS_PENDING,
        ]);

        $job = new SendCampaignEmailJob($campaign->id, $recipient->id);
        $job->handle(app(SmtpService::class));

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals(EmailRecipient::STATUS_SENT, $recipient->status);
        $this->assertEquals(1, $campaign->sent_count);
        $this->assertEquals(EmailCampaign::STATUS_COMPLETED, $campaign->status);

        Mail::assertSentCount(1);
    }

    public function test_campaign_can_be_cancelled(): void
    {
        $admin = $this->actor(Role::SUPER_ADMIN, ['email.campaigns.cancel', 'email.campaigns.view']);
        $token = $admin->createToken('test')->plainTextToken;

        $campaign = EmailCampaign::create([
            'title' => 'Test Campaign To Cancel',
            'subject' => 'Notice',
            'body_html' => '<p>Notice</p>',
            'status' => EmailCampaign::STATUS_QUEUED,
            'total_recipients' => 2,
        ]);

        $recipient1 = EmailRecipient::create([
            'email_campaign_id' => $campaign->id,
            'email' => 'c1@example.com',
            'status' => EmailRecipient::STATUS_PENDING,
        ]);

        $response = $this->withToken($token)->postJson("/api/v1/email/campaigns/{$campaign->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals(EmailRecipient::STATUS_CANCELLED, $recipient1->fresh()->status);
    }

    public function test_unauthorized_user_cannot_create_or_manage_campaigns(): void
    {
        $unauthorized = $this->actor(Role::CADET, []);
        $token = $unauthorized->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/email/campaigns')
            ->assertStatus(403);

        $this->withToken($token)->postJson('/api/v1/email/campaigns', [
            'title' => 'Unauthorized Campaign',
            'subject' => 'Subject',
            'body_html' => '<p>Hello</p>',
        ])->assertStatus(403);
    }

    public function test_province_admin_cannot_manage_campaign_of_other_province(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();

        $admin1 = $this->actor(Role::PROVINCE_ADMIN, ['email.campaigns.view', 'email.campaigns.send'], [
            'province_id' => $province1->id,
        ]);
        $token1 = $admin1->createToken('test')->plainTextToken;

        $otherCampaign = EmailCampaign::create([
            'title' => 'Other Province Campaign',
            'subject' => 'Notice',
            'body_html' => '<p>Body</p>',
            'province_id' => $province2->id,
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);

        $response = $this->withToken($token1)->postJson("/api/v1/email/campaigns/{$otherCampaign->id}/send");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['campaign']);
    }

    public function test_campaign_with_failed_delivery_updates_status_to_partial(): void
    {
        $campaign = EmailCampaign::create([
            'title' => 'Failing Campaign',
            'subject' => 'Notice',
            'body_html' => '<p>Body</p>',
            'total_recipients' => 1,
            'status' => EmailCampaign::STATUS_QUEUED,
        ]);

        $recipient = EmailRecipient::create([
            'email_campaign_id' => $campaign->id,
            'email' => 'failing@example.com',
            'status' => EmailRecipient::STATUS_PENDING,
        ]);

        // Mock a failure on attempt 3
        $job = new SendCampaignEmailJob($campaign->id, $recipient->id);
        $job->tries = 1; // set max tries to 1 so first error marks it failed

        $mockSmtp = \Mockery::mock(SmtpService::class);
        $mockSmtp->shouldReceive('applyConfig')->andReturnNull();

        Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP Connection timeout'));

        try {
            $job->handle($mockSmtp);
        } catch (\Throwable) {
            // caught
        }

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals(EmailRecipient::STATUS_FAILED, $recipient->status);
        $this->assertEquals(1, $campaign->failed_count);
        $this->assertEquals(EmailCampaign::STATUS_FAILED, $campaign->status);
    }
}
