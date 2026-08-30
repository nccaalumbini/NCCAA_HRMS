<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveSmtpSettingsRequest;
use App\Http\Requests\Api\V1\StoreEmailCampaignRequest;
use App\Http\Requests\Api\V1\TestSmtpRequest;
use App\Models\EmailCampaign;
use App\Services\EmailCampaignService;
use App\Services\SmtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function __construct(
        private readonly SmtpService $smtpService,
        private readonly EmailCampaignService $campaignService,
    ) {}

    /**
     * Get current SMTP settings.
     */
    public function getSettings(Request $request): JsonResponse
    {
        return ApiResponse::success($this->smtpService->getSettings());
    }

    /**
     * Save SMTP settings securely.
     */
    public function saveSettings(SaveSmtpSettingsRequest $request): JsonResponse
    {
        $settings = $this->smtpService->saveSettings($request->user(), $request->validated());

        return ApiResponse::success($settings, 'SMTP configuration saved successfully.');
    }

    /**
     * Test SMTP connection.
     */
    public function testSmtp(TestSmtpRequest $request): JsonResponse
    {
        $result = $this->smtpService->testConnection(
            $request->user(),
            $request->validated('recipient'),
            $request->validated('settings'),
        );

        return ApiResponse::success($result, $result['message']);
    }

    /**
     * List eligible recipients for composing bulk emails.
     */
    public function getRecipients(Request $request): JsonResponse
    {
        $recipients = $this->campaignService->getAvailableRecipients(
            $request->user(),
            $request->only(['search', 'province_id', 'district_id', 'rank_id', 'status']),
        );

        return ApiResponse::success([
            'items' => collect($recipients->items())->map(fn ($cadet) => [
                'id' => $cadet->id,
                'cadet_number' => $cadet->cadet_number,
                'name' => $cadet->name,
                'email' => $cadet->email,
                'phone' => $cadet->phone,
                'rank' => $cadet->rank?->only(['id', 'name_en', 'short_code']),
                'province' => $cadet->province?->only(['id', 'name_en']),
                'district' => $cadet->district?->only(['id', 'name_en']),
                'status' => $cadet->status,
            ]),
            'meta' => [
                'current_page' => $recipients->currentPage(),
                'per_page' => $recipients->perPage(),
                'total' => $recipients->total(),
                'last_page' => $recipients->lastPage(),
            ],
        ]);
    }

    /**
     * List email campaigns.
     */
    public function indexCampaigns(Request $request): JsonResponse
    {
        $campaigns = $this->campaignService->paginate(
            $request->user(),
            $request->only(['search', 'status']),
        );

        return ApiResponse::success([
            'items' => collect($campaigns->items())->map(fn (EmailCampaign $c) => $this->campaignArray($c)),
            'meta' => [
                'current_page' => $campaigns->currentPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
                'last_page' => $campaigns->lastPage(),
            ],
        ]);
    }

    /**
     * Show campaign details.
     */
    public function showCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->show($request->user(), $campaign);

        return ApiResponse::success($this->campaignArray($campaign));
    }

    /**
     * Show paginated recipient logs for campaign.
     */
    public function recipients(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $recipients = $this->campaignService->paginateRecipients(
            $request->user(),
            $campaign,
            $request->only(['status', 'search']),
        );

        return ApiResponse::success([
            'items' => collect($recipients->items())->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->email,
                'name' => $r->name,
                'status' => $r->status,
                'attempts' => $r->attempts,
                'error_message' => $r->error_message,
                'sent_at' => $r->sent_at,
                'cadet' => $r->cadet ? [
                    'id' => $r->cadet->id,
                    'cadet_number' => $r->cadet->cadet_number,
                    'rank' => $r->cadet->rank?->only(['name_en', 'short_code']),
                ] : null,
            ]),
            'meta' => [
                'current_page' => $recipients->currentPage(),
                'per_page' => $recipients->perPage(),
                'total' => $recipients->total(),
                'last_page' => $recipients->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new email campaign.
     */
    public function createCampaign(StoreEmailCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaignService->create($request->user(), $request->validated());

        return ApiResponse::success($this->campaignArray($campaign), 'Email campaign created successfully.', 201);
    }

    /**
     * Queue and send an email campaign.
     */
    public function sendCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->send($request->user(), $campaign);

        return ApiResponse::success($this->campaignArray($campaign), 'Email campaign queued for delivery.');
    }

    /**
     * Cancel an email campaign.
     */
    public function cancelCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->cancel($request->user(), $campaign);

        return ApiResponse::success($this->campaignArray($campaign), 'Email campaign cancelled.');
    }

    /**
     * Format email campaign array response.
     *
     * @return array<string, mixed>
     */
    private function campaignArray(EmailCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'uuid' => $campaign->uuid,
            'title' => $campaign->title,
            'subject' => $campaign->subject,
            'body_html' => $campaign->body_html,
            'sender_name' => $campaign->sender_name,
            'sender_email' => $campaign->sender_email,
            'reply_to' => $campaign->reply_to,
            'status' => $campaign->status,
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count,
            'province' => $campaign->province?->only(['id', 'name_en']),
            'district' => $campaign->district?->only(['id', 'name_en']),
            'created_by' => $campaign->createdByUser ? [
                'id' => $campaign->createdByUser->id,
                'name' => $campaign->createdByUser->name,
                'email' => $campaign->createdByUser->email,
            ] : null,
            'queued_at' => $campaign->queued_at,
            'started_at' => $campaign->started_at,
            'completed_at' => $campaign->completed_at,
            'created_at' => $campaign->created_at,
        ];
    }
}
