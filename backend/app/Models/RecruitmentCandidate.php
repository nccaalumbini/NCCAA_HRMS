<?php

namespace App\Models;

use Database\Factories\RecruitmentCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecruitmentCandidate extends Model
{
    /** @use HasFactory<RecruitmentCandidateFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_OUTREACH_SENT = 'outreach_sent';

    public const STATUS_CV_REQUESTED = 'cv_requested';

    public const STATUS_CV_SUBMITTED = 'cv_submitted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONVERTED = 'converted_to_cadet';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name', 'gender', 'province_id', 'district_id', 'local_level', 'ward_number',
        'contact_number', 'email', 'skills', 'recruitment_status', 'notes', 'outreach_sent_at',
        'converted_user_id', 'converted_at', 'source', 'priority_score', 'tags', 'assigned_recruiter_id',
        'recruitment_import_batch_id', 'last_contacted_at',
    ];

    /**
     * Get the candidate's province.
     *
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * Get the candidate's district.
     *
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Get the user created from this candidate.
     *
     * @return BelongsTo<User, $this>
     */
    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function assignedRecruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_recruiter_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(RecruitmentImportBatch::class, 'recruitment_import_batch_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RecruitmentActivity::class, 'recruitment_candidate_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'tags' => 'array',
            'ward_number' => 'integer',
            'priority_score' => 'integer',
            'outreach_sent_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }
}
