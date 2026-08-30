<?php

namespace Database\Factories;

use App\Models\RecruitmentCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentCandidate>
 */
class RecruitmentCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'contact_number' => fake()->unique()->numerify('98########'),
            'email' => fake()->unique()->safeEmail(),
            'skills' => ['Communication', 'IT'],
            'recruitment_status' => RecruitmentCandidate::STATUS_IMPORTED,
        ];
    }
}
