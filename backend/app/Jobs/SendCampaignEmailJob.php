<?php

namespace App\Jobs;

use App\Mail\CampaignMailable;
use App\Models\EmailCampaign;
use App\Models\EmailRecipient;
use App\Services\SmtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 180];

    public function __construct(
        public int $campaignId,
        public int $recipientId,
    ) {}

    public function handle(SmtpService $smtpService): void
    {
        $campaign = EmailCampaign::find($this->campaignId);
        $recipient = EmailRecipient::find($this->recipientId);

        if (! $campaign || ! $recipient) {
            return;
        }

        if ($campaign->status === EmailCampaign::STATUS_CANCELLED || $recipient->status === EmailRecipient::STATUS_CANCELLED) {
            return;
        }

        if ($recipient->status === EmailRecipient::STATUS_SENT) {
            return; // Idempotent check
        }

        $recipient->increment('attempts');
        $recipient->update(['status' => EmailRecipient::STATUS_SENDING]);

        if ($campaign->status === EmailCampaign::STATUS_QUEUED || $campaign->status === EmailCampaign::STATUS_DRAFT) {
            $campaign->update([
                'status' => EmailCampaign::STATUS_PROCESSING,
                'started_at' => $campaign->started_at ?? now(),
            ]);
        }

        $smtpService->applyConfig();

        try {
            $fromEmail = $campaign->sender_email ?: config('mail.from.address') ?: 'noreply@nccaa.local';
            $fromName = $campaign->sender_name ?: config('mail.from.name') ?: 'NCCAA HRMS';
            $replyTo = $campaign->reply_to;

            // Personalize template placeholders
            $html = $this->personalizeContent($campaign->body_html, $recipient);
            $subject = $this->personalizeContent($campaign->subject, $recipient);

            Mail::to($recipient->email, $recipient->name)->send(
                new CampaignMailable($subject, $html, $fromEmail, $fromName, $replyTo)
            );

            $recipient->update([
                'status' => EmailRecipient::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $campaign->increment('sent_count');
        } catch (\Throwable $e) {
            $errorMessage = Str::limit($e->getMessage(), 500);

            if ($this->attempts() >= $this->tries) {
                $recipient->update([
                    'status' => EmailRecipient::STATUS_FAILED,
                    'error_message' => $errorMessage,
                ]);

                $campaign->increment('failed_count');
            } else {
                $recipient->update([
                    'status' => EmailRecipient::STATUS_PENDING,
                    'error_message' => 'Attempt '.$this->attempts().' failed: '.$errorMessage,
                ]);

                throw $e;
            }
        } finally {
            $this->checkCampaignCompletion($campaign);
        }
    }

    private function personalizeContent(string $content, EmailRecipient $recipient): string
    {
        $replacements = [
            '{{name}}' => htmlspecialchars($recipient->name ?? 'Cadet', ENT_QUOTES, 'UTF-8'),
            '{{email}}' => htmlspecialchars($recipient->email, ENT_QUOTES, 'UTF-8'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function checkCampaignCompletion(EmailCampaign $campaign): void
    {
        $hasPendingOrSending = $campaign->recipients()
            ->whereIn('status', [EmailRecipient::STATUS_PENDING, EmailRecipient::STATUS_SENDING])
            ->exists();

        if (! $hasPendingOrSending) {
            $hasFailed = $campaign->recipients()->where('status', EmailRecipient::STATUS_FAILED)->exists();
            $status = $hasFailed ? EmailCampaign::STATUS_PARTIAL : EmailCampaign::STATUS_COMPLETED;

            if ($campaign->sent_count === 0 && $hasFailed) {
                $status = EmailCampaign::STATUS_FAILED;
            }

            $campaign->update([
                'status' => $status,
                'completed_at' => now(),
            ]);
        }
    }
}
