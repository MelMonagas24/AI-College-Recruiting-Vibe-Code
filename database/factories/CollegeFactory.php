<?php

namespace Database\Factories;

use App\Enums\MeritAidAvailability;
use App\Enums\VerificationStatus;
use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fictional colleges for tests only. Never use these values as real data.
 *
 * @extends Factory<College>
 */
class CollegeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ipeds_unit_id' => fake()->unique()->numberBetween(900000, 999999),
            'name' => 'Fictional '.fake()->unique()->lastName().' College',
            'short_name' => fake()->lastName(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'ncaa_division' => fake()->randomElement([1, 2, 3]),
            'conference' => 'Fictional Conference',
            'has_active_womens_soccer' => true,
            'womens_soccer_verified_season' => '2026',
            'federal_locale' => 'Suburb: Large',
            'undergraduate_enrollment' => 6000,
            'enrollment_term' => 'Fall 2024',
            'offers_business' => true,
            'offers_nursing' => false,
            'offers_psychology' => true,
            'offers_biology' => true,
            'offers_education' => null,
            'cost_academic_year' => '2026-27',
            'tuition_fees_in_state' => 40000,
            'tuition_fees_out_of_state' => 40000,
            'cost_of_attendance_in_state' => 60000,
            'cost_of_attendance_out_of_state' => 60000,
            'cost_of_attendance_is_complete' => true,
            'merit_aid' => MeritAidAvailability::Available,
            'verification_status' => VerificationStatus::Verified,
            'last_verified_on' => '2026-10-26',
        ];
    }

    /**
     * A college whose women's soccer program has not been verified.
     */
    public function withoutVerifiedSoccer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_active_womens_soccer' => false,
            'womens_soccer_verified_season' => null,
            'verification_status' => VerificationStatus::Unverified,
        ]);
    }

    /**
     * A college whose NCAA division has not been confirmed.
     */
    public function withoutConfirmedDivision(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ncaa_division' => null,
            'verification_status' => VerificationStatus::PartiallyVerified,
        ]);
    }
}
