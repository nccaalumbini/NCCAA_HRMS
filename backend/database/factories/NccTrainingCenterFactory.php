<?php

namespace Database\Factories;

use App\Models\NccTrainingCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NccTrainingCenter>
 */
class NccTrainingCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name_en' => fake()->sentence(3),
            'name_ne' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
