<?php

namespace Database\Factories;

use App\Models\JobCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobCategory>
 */
class JobCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $skill = $this->faker->randomElement(['Design', 'Engineering', 'Media', 'Writing']);

        return [
            'name' => $skill.' '.$this->faker->word(),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'icon' => $this->faker->randomElement(['palette', 'layout', 'code', 'server', 'pen', 'video']),
            'field_schema' => [
                'fields' => [
                    [
                        'key' => 'years_experience',
                        'label' => 'Years of experience',
                        'type' => 'select',
                        'required' => true,
                        'options' => ['0-1', '1-3', '3-5', '5+'],
                    ],
                ],
            ],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
