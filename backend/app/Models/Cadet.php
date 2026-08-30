<?php

namespace App\Models;

use Database\Factories\CadetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'cadet_number',
    'name',
    'rank_id',
    'province_id',
    'district_id',
    'local_level_id',
    'ward_id',
    'email',
    'phone',
    'status',
    'user_id',
])]
class Cadet extends Model
{
    /** @use HasFactory<CadetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Cadet $cadet) {
            if ($cadet->uuid === null) {
                $cadet->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the rank assigned to the cadet.
     *
     * @return BelongsTo<Rank, $this>
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    /**
     * Get the province the cadet belongs to.
     *
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * Get the district the cadet belongs to.
     *
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Get the local level the cadet belongs to.
     *
     * @return BelongsTo<LocalLevel, $this>
     */
    public function localLevel(): BelongsTo
    {
        return $this->belongsTo(LocalLevel::class);
    }

    /**
     * Get the ward the cadet belongs to.
     *
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get the optional linked user account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the cadet's extended profile.
     *
     * @return HasOne<CadetProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(CadetProfile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank_id' => 'integer',
            'province_id' => 'integer',
            'district_id' => 'integer',
            'local_level_id' => 'integer',
            'ward_id' => 'integer',
            'user_id' => 'integer',
        ];
    }
}
