<?php

namespace Database\Factories;

use App\Models\JobCategory;
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

    /**
     * A public job portal submission (the unit the applications module tracks).
     */
    public function portal(?JobCategory $category = null): static
    {
        return $this->state(fn (): array => [
            'source' => 'portal',
            'recruitment_status' => RecruitmentCandidate::STATUS_APPLIED,
            'cadet_number' => fake()->unique()->numerify('NCC-####'),
            'division' => 'junior',
            'ncc_batch' => (string) fake()->numberBetween(1, 51),
            'ncc_year' => (string) fake()->numberBetween(2078, 2086),
            'skill_category_id' => $category?->id,
            'skills' => $category !== null ? [$category->name] : ['IT'],
        ]);
    }
}
