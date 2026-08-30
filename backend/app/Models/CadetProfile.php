<?php

namespace App\Models;

use Database\Factories\CadetProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cadet_id',
    'date_of_birth',
    'gender',
    'blood_group',
    'photo_path',
    'father_name',
    'mother_name',
    'guardian_name',
    'guardian_phone',
    'guardian_relation',
    'citizenship_number',
    'local_address',
    'enrollment_date',
])]
class CadetProfile extends Model
{
    /** @use HasFactory<CadetProfileFactory> */
    use HasFactory;

    /**
     * Get the cadet this profile belongs to.
     *
     * @return BelongsTo<Cadet, $this>
     */
    public function cadet(): BelongsTo
    {
        return $this->belongsTo(Cadet::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'enrollment_date' => 'date',
        ];
    }
}
