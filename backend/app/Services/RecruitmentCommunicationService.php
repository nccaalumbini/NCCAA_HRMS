<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Mail\CampaignMailable;
use App\Models\RecruitmentActivity;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RecruitmentCommunicationService
{
    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
        private readonly SmtpService $smtpService,
    ) {}

    /**
     * Send a single email through the configured SMTP mailer or WhatsApp text through Meta Cloud API.
     *
     * @param  array{channel: string, subject?: string|null, message: string}  $data
     * @return array{channel: string, status: string, provider: string}
     */
    public function send(User $actor, RecruitmentCandidate $candidate, array $data): array
    {
        $this->assertCanManage($actor, $candidate);
        $channel = $data['channel'];
        $message = trim($data['message']);

        if ($channel === 'email') {
            return $this->sendEmail($actor, $candidate, $data['subject'] ?? '', $message);
        }

        return $this->sendWhatsApp($actor, $candidate, $message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(User $actor, RecruitmentCandidate $candidate): array
    {
        $this->assertCanManage($actor, $candidate);

        return $candidate->activities()
            ->whereIn('activity_type', ['email_sent', 'whatsapp_sent', 'email_failed', 'whatsapp_failed'])
            ->with('performedByUser:id,name')
            ->latest('performed_at')
            ->get()
            ->map(fn (RecruitmentActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->activity_type,
                'description' => $activity->description,
                'performed_by' => $activity->performedByUser?->only(['id', 'name']),
                'performed_at' => $activity->performed_at,
                'metadata' => $activity->metadata ?? [],
            ])->all();
    }

    /** @return array{channel: string, status: string, provider: string} */
    private function sendEmail(User $actor, RecruitmentCandidate $candidate, string $subject, string $message): array
    {
        if (! $candidate->email || ! filter_var($candidate->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['candidate' => ['This candidate does not have a valid email address.']]);
        }

        $this->smtpService->applyConfig();
        $provider = config('mail.default', 'log');

        if (! app()->environment('testing') && in_array($provider, ['log', 'array'], true)) {
            throw ValidationException::withMessages(['message' => ['Email delivery is not configured. Configure the SMTP settings before sending emails.']]);
        }

        try {
            Mail::to($candidate->email)->send(new CampaignMailable($subject, nl2br(e($message))));
            $this->record($actor, $candidate, 'email_sent', "Email sent to {$candidate->email}.", [
                'subject' => $subject,
                'channel' => 'email',
                'provider' => $provider,
            ]);

            return ['channel' => 'email', 'status' => 'sent', 'provider' => $provider];
        } catch (\Throwable $exception) {
            $error = $this->safeError($exception->getMessage());
            $this->record($actor, $candidate, 'email_failed', 'Email delivery failed.', [
                'channel' => 'email',
                'provider' => $provider,
                'error' => $error,
            ]);

            Log::error('Recruitment candidate email delivery failed.', [
                'candidate_id' => $candidate->id,
                'performed_by_user_id' => $actor->id,
                'channel' => 'email',
                'provider' => $provider,
                'subject' => $subject,
                'error' => $error,
            ]);

            throw ValidationException::withMessages(['message' => ['Email could not be sent. Check the SMTP settings.']]);
        }
    }

    /** @return array{channel: string, status: string, provider: string} */
    private function sendWhatsApp(User $actor, RecruitmentCandidate $candidate, string $message): array
    {
        $phone = $this->normalizePhone($candidate->contact_number);
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if ($phone === '') {
            throw ValidationException::withMessages(['candidate' => ['This candidate does not have a valid phone number.']]);
        }

        if (! $token || ! $phoneNumberId) {
            throw ValidationException::withMessages(['channel' => ['WhatsApp API is not configured. Use the native Message action or configure Meta credentials.']]);
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post('https://graph.facebook.com/'.config('services.whatsapp.api_version')."/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $message],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('WhatsApp provider returned HTTP '.$response->status());
            }

            $this->record($actor, $candidate, 'whatsapp_sent', 'WhatsApp message sent.', ['message_id' => $response->json('messages.0.id')]);

            return ['channel' => 'whatsapp', 'status' => 'sent', 'provider' => 'meta_cloud_api'];
        } catch (\Throwable $exception) {
            $this->record($actor, $candidate, 'whatsapp_failed', 'WhatsApp delivery failed.', ['error' => $this->safeError($exception->getMessage())]);
            throw ValidationException::withMessages(['message' => ['WhatsApp message could not be sent.']]);
        }
    }

    private function normalizePhone(?string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($normalized, '00')) {
            return substr($normalized, 2);
        }
        if (str_starts_with($normalized, '0')) {
            return '977'.substr($normalized, 1);
        }
        if (str_starts_with($normalized, '977')) {
            return $normalized;
        }

        return $normalized !== '' ? '977'.$normalized : '';
    }

    private function assertCanManage(User $actor, RecruitmentCandidate $candidate): void
    {
        $scope = $this->accessScope->resolve($actor);
        if ($scope === AccessScopeType::All || ($scope === AccessScopeType::Province && $candidate->province_id === $actor->province_id) || ($scope === AccessScopeType::District && $candidate->district_id === $actor->district_id)) {
            return;
        }

        throw ValidationException::withMessages(['candidate' => ['You do not have permission to communicate with this candidate.']]);
    }

    /** @param array<string, mixed> $metadata */
    private function record(User $actor, RecruitmentCandidate $candidate, string $type, string $description, array $metadata): void
    {
        RecruitmentActivity::create([
            'recruitment_candidate_id' => $candidate->id,
            'activity_type' => $type,
            'description' => $description,
            'performed_by_user_id' => $actor->id,
            'metadata' => $metadata,
            'performed_at' => now(),
        ]);
    }

    private function safeError(string $message): string
    {
        return preg_replace('/(token|password|authorization)[^,; ]*/i', '[redacted]', $message) ?? 'provider error';
    }
}
