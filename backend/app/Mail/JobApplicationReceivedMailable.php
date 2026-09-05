<?php

namespace App\Mail;

use App\Models\JobCategory;
use App\Models\RecruitmentCandidate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobApplicationReceivedMailable extends Mailable
{
    use SerializesModels;

    public function __construct(
        public RecruitmentCandidate $candidate,
        public JobCategory $category,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NCC — Application Received: '.$this->category->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: sprintf(
                '<p>Dear <strong>%s</strong>,</p>'.
                '<p>Thank you for applying for the <strong>%s</strong> position with the National Cadet Corps Alumni Association of Nepal.</p>'.
                '<p>Your application has been received and will be reviewed by our recruitment team. We will contact you if your profile matches our current needs.</p>'.
                '<p>Reference: <strong>%s</strong></p>'.
                '<p style="color:#64748b;font-size:12px">This is an automated acknowledgment. Please do not reply to this email.</p>',
                e($this->candidate->full_name),
                e($this->category->name),
                e($this->candidate->getAttribute('reference_number') ?? '#'.$this->candidate->id),
            ),
        );
    }
}
