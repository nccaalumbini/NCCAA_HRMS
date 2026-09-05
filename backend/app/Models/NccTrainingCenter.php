<?php

namespace App\Models;

use Database\Factories\NccTrainingCenterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NccTrainingCenter extends Model
{
    /** @use HasFactory<NccTrainingCenterFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name_en', 'name_ne', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
