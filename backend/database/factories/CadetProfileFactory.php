<?php

namespace Database\Factories;

use App\Models\Cadet;
use App\Models\CadetProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CadetProfile>
 */
class CadetProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cadet_id' => Cadet::factory(),
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'father_name' => fake()->name('male'),
            'mother_name' => fake()->name('female'),
            'guardian_relation' => fake()->randomElement(['Father', 'Mother', 'Guardian']),
            'local_address' => fake()->address(),
            'enrollment_date' => fake()->date(),
        ];
    }
}
