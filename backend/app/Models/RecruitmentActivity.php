<?php

namespace App\Models;

use Database\Factories\RecruitmentActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentActivity extends Model
{
    /** @use HasFactory<RecruitmentActivityFactory> */
    use HasFactory;

    protected $fillable = ['recruitment_candidate_id', 'activity_type', 'description', 'performed_by_user_id', 'metadata', 'performed_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'performed_at' => 'datetime'];
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
