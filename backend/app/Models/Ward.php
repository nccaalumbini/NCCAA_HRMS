<?php

namespace App\Models;

use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['local_level_id', 'ward_number', 'name_en', 'name_ne', 'status'];

    /**
     * Get the local level that the ward belongs to.
     *
     * @return BelongsTo<LocalLevel, $this>
     */
    public function localLevel(): BelongsTo
    {
        return $this->belongsTo(LocalLevel::class);
    }
}
