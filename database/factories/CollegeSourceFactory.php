<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\CollegeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollegeSource>
 */
class CollegeSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'college_id' => College::factory(),
            'topic' => fake()->randomElement(CollegeSource::TOPICS),
            'url' => 'https://example.edu/'.fake()->slug(),
            'data_period' => '2026-27',
            'accessed_on' => '2026-10-26',
            'note' => null,
        ];
    }
}
