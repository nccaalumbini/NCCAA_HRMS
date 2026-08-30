<?php

namespace Database\Factories;

use App\Models\Rank;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rank>
 */
class RankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_en' => fake()->unique()->jobTitle(),
            'name_ne' => fake()->word(),
            'short_code' => strtoupper(Str::random(4)),
            'display_order' => fake()->unique()->numberBetween(1, 50),
            'status' => 'active',
        ];
    }
}
