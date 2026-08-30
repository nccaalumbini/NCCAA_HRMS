<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\LocalLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LocalLevel>
 */
class LocalLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name_en' => fake()->unique()->city(),
            'name_ne' => fake()->city(),
            'type' => fake()->randomElement(['municipality', 'rural_municipality', 'sub_metropolitan', 'metropolitan']),
            'code' => strtoupper(Str::random(4)),
            'status' => 'active',
        ];
    }
}
