<?php

namespace App\Services;

use App\Enums\AccessScopeType;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Cadet;
use App\Models\EmailCampaign;
use App\Models\EmailRecipient;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailCampaignService
{
    public function __construct(
        private readonly AccessScope $accessScope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Paginate campaigns within actor's scope.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = EmailCampaign::query()
            ->with(['createdByUser', 'province', 'district']);

        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::Province && $actor->province_id) {
            $query->where('province_id', $actor->province_id);
        } elseif ($scope === AccessScopeType::District && $actor->district_id) {
            $query->where('district_id', $actor->district_id);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('subject', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    /**
     * Show a campaign along with recipient statistics.
     */
    public function show(User $actor, EmailCampaign $campaign): EmailCampaign
    {
        $this->assertCanManage($actor, $campaign);

        return $campaign->load(['createdByUser', 'province', 'district']);
    }

    /**
     * Get paginated recipients log for a campaign.
     *
     * @param  array{status?: string|null, search?: string|null}  $filters
     */
    public function paginateRecipients(User $actor, EmailCampaign $campaign, array $filters = []): LengthAwarePaginator
    {
        $this->assertCanManage($actor, $campaign);

        $query = $campaign->recipients()->with(['cadet.rank']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('email', 'like', $search)
                    ->orWhere('name', 'like', $search);
            });
        }

        return $query->paginate(20);
    }

    /**
     * Get available email recipients (cadets & users) within the actor's scope.
     *
     * @param  array{search?: string|null, province_id?: int|null, district_id?: int|null, rank_id?: int|null, status?: string|null}  $filters
     */
    public function getAvailableRecipients(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Cadet::query()
            ->with(['rank', 'province', 'district'])
            ->whereNotNull('email')
            ->where('email', '!=', '');

        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::Province && $actor->province_id) {
            $query->where('province_id', $actor->province_id);
        } elseif ($scope === AccessScopeType::District && $actor->district_id) {
            $query->where('district_id', $actor->district_id);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('cadet_number', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if (! empty($filters['province_id'])) {
            $query->where('province_id', $filters['province_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }

        if (! empty($filters['rank_id'])) {
            $query->where('rank_id', $filters['rank_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->paginate(50);
    }

    /**
     * Create an email campaign with recipients.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): EmailCampaign
    {
        $recipientCadetIds = $data['cadet_ids'] ?? [];
        $recipientEmails = $data['manual_emails'] ?? [];

        if (empty($recipientCadetIds) && empty($recipientEmails)) {
            throw ValidationException::withMessages([
                'cadet_ids' => ['Please select at least one recipient for the campaign.'],
            ]);
        }

        // Validate that all requested cadet recipients are within actor's scope
        $scope = $this->accessScope->resolve($actor);
        $scopedGeography = $this->accessScope->scopedGeography($actor);

        $cadets = Cadet::whereIn('id', $recipientCadetIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($scope === AccessScopeType::Province) {
            foreach ($cadets as $cadet) {
                if ($cadet->province_id !== $actor->province_id) {
                    throw ValidationException::withMessages([
                        'cadet_ids' => ["Cadet {$cadet->cadet_number} is outside your province scope."],
                    ]);
                }
            }
        } elseif ($scope === AccessScopeType::District) {
            foreach ($cadets as $cadet) {
                if ($cadet->district_id !== $actor->district_id) {
                    throw ValidationException::withMessages([
                        'cadet_ids' => ["Cadet {$cadet->cadet_number} is outside your district scope."],
                    ]);
                }
            }
        }

        $sanitizedHtml = $this->sanitizeHtml($data['body_html']);

        return DB::transaction(function () use ($actor, $data, $cadets, $recipientEmails, $sanitizedHtml): EmailCampaign {
            $campaign = EmailCampaign::create([
                'title' => $data['title'],
                'subject' => $data['subject'],
                'body_html' => $sanitizedHtml,
                'body_text' => strip_tags($sanitizedHtml),
                'sender_name' => $data['sender_name'] ?? null,
                'sender_email' => $data['sender_email'] ?? null,
                'reply_to' => $data['reply_to'] ?? null,
                'status' => EmailCampaign::STATUS_DRAFT,
                'created_by_user_id' => $actor->id,
                'province_id' => $actor->province_id,
                'district_id' => $actor->district_id,
            ]);

            $recipients = [];
            $seenEmails = [];

            foreach ($cadets as $cadet) {
                $email = strtolower(trim($cadet->email));
                if (! isset($seenEmails[$email])) {
                    $seenEmails[$email] = true;
                    $recipients[] = [
                        'email_campaign_id' => $campaign->id,
                        'cadet_id' => $cadet->id,
                        'user_id' => $cadet->user_id,
                        'email' => $email,
                        'name' => $cadet->name,
                        'status' => EmailRecipient::STATUS_PENDING,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach ($recipientEmails as $entry) {
                $email = is_array($entry) ? strtolower(trim($entry['email'] ?? '')) : strtolower(trim($entry));
                $name = is_array($entry) ? ($entry['name'] ?? null) : null;

                if (filter_var($email, FILTER_VALIDATE_EMAIL) && ! isset($seenEmails[$email])) {
                    $seenEmails[$email] = true;
                    $recipients[] = [
                        'email_campaign_id' => $campaign->id,
                        'cadet_id' => null,
                        'user_id' => null,
                        'email' => $email,
                        'name' => $name,
                        'status' => EmailRecipient::STATUS_PENDING,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($recipients)) {
                EmailRecipient::insert($recipients);
            }

            $campaign->update(['total_recipients' => count($recipients)]);

            $this->auditLogger->record($actor, 'created_email_campaign', $campaign, null, null, [
                'title' => $campaign->title,
                'total_recipients' => count($recipients),
            ]);

            return $campaign;
        });
    }

    /**
     * Dispatch email campaign jobs to queue.
     */
    public function send(User $actor, EmailCampaign $campaign): EmailCampaign
    {
        $this->assertCanManage($actor, $campaign);

        if ($campaign->status === EmailCampaign::STATUS_COMPLETED || $campaign->status === EmailCampaign::STATUS_PROCESSING) {
            throw ValidationException::withMessages([
                'campaign' => ['This campaign is already being processed or completed.'],
            ]);
        }

        $pendingRecipients = $campaign->recipients()
            ->where('status', EmailRecipient::STATUS_PENDING)
            ->get(['id']);

        if ($pendingRecipients->isEmpty()) {
            throw ValidationException::withMessages([
                'campaign' => ['No pending recipients found in this campaign.'],
            ]);
        }

        $campaign->update([
            'status' => EmailCampaign::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        foreach ($pendingRecipients as $recipient) {
            SendCampaignEmailJob::dispatch($campaign->id, $recipient->id);
        }

        $this->auditLogger->record($actor, 'sent_email_campaign', $campaign, null, null, [
            'total_queued' => $pendingRecipients->count(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Cancel an in-progress or queued campaign.
     */
    public function cancel(User $actor, EmailCampaign $campaign): EmailCampaign
    {
        $this->assertCanManage($actor, $campaign);

        if ($campaign->status === EmailCampaign::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'campaign' => ['Completed campaigns cannot be cancelled.'],
            ]);
        }

        $campaign->update(['status' => EmailCampaign::STATUS_CANCELLED]);
        $campaign->recipients()->whereIn('status', [EmailRecipient::STATUS_PENDING, EmailRecipient::STATUS_SENDING])
            ->update(['status' => EmailRecipient::STATUS_CANCELLED]);

        $this->auditLogger->record($actor, 'cancelled_email_campaign', $campaign);

        return $campaign->fresh();
    }

    /**
     * Ensure actor has scope permission to manage campaign.
     */
    protected function assertCanManage(User $actor, EmailCampaign $campaign): void
    {
        $scope = $this->accessScope->resolve($actor);

        if ($scope === AccessScopeType::All) {
            return;
        }

        if ($scope === AccessScopeType::Province && $campaign->province_id === $actor->province_id) {
            return;
        }

        if ($scope === AccessScopeType::District && $campaign->district_id === $actor->district_id) {
            return;
        }

        if ($campaign->created_by_user_id === $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => ['You do not have permission to manage this email campaign.'],
        ]);
    }

    private function sanitizeHtml(string $html): string
    {
        // Strip unsafe script tags, event handlers, and javascript: protocols
        $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? $html;
        $cleaned = preg_replace('/on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $cleaned) ?? $cleaned;

        return $cleaned;
    }
}
