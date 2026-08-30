<?php

namespace Database\Factories;

use App\Models\LocalLevel;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'local_level_id' => LocalLevel::factory(),
            'ward_number' => fake()->unique()->numberBetween(1, 33),
            'name_en' => fake()->city(),
            'name_ne' => fake()->city(),
            'status' => 'active',
        ];
    }
}
