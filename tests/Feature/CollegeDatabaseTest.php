<?php

namespace Tests\Feature;

use App\Enums\CampusEnvironment;
use App\Enums\CollegeSize;
use App\Enums\VerificationStatus;
use App\Models\College;
use App\Models\CollegeSource;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollegeDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_all_25_colleges_by_division(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->assertSame(25, College::count());
        $this->assertSame(8, College::where('ncaa_division', 1)->count());
        $this->assertSame(7, College::where('ncaa_division', 2)->count());
        $this->assertSame(10, College::where('ncaa_division', 3)->count());
    }

    public function test_every_seeded_college_is_eligible_and_tracks_verification(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->assertSame(25, College::eligibleForRecommendations()->count());
        $this->assertSame(19, College::where('verification_status', VerificationStatus::Verified)->count());
        $this->assertSame(6, College::where('verification_status', VerificationStatus::PartiallyVerified)->count());
    }

    public function test_every_seeded_college_keeps_its_official_sources(): void
    {
        $this->seed(CollegeSeeder::class);

        College::with('sources')->get()->each(function (College $college): void {
            $topics = $college->sources->pluck('topic');

            $this->assertTrue($topics->contains('athletics'), "{$college->name} is missing an athletics source.");
            $this->assertTrue($topics->contains('federal_data'), "{$college->name} is missing a federal data source.");
            $this->assertTrue($topics->contains('costs'), "{$college->name} is missing a costs source.");
            $college->sources->each(fn ($source) => $this->assertStringStartsWith('https://', $source->url));
            $this->assertNotEmpty($college->sources->first()->data_period);
        });
    }

    public function test_unconfirmed_costs_are_stored_as_unknown(): void
    {
        $this->seed(CollegeSeeder::class);

        foreach (['Western Washington', 'Susquehanna', 'Catholic University', 'UChicago', 'Penn State'] as $shortName) {
            $college = College::where('short_name', $shortName)->sole();

            $this->assertSame(VerificationStatus::PartiallyVerified, $college->verification_status);
            $this->assertNull($college->tuition_fees_in_state);
            $this->assertNull($college->cost_of_attendance_in_state);
            $this->assertNull($college->cost_of_attendance_out_of_state);
        }
    }

    public function test_verified_colleges_have_costs_with_an_academic_year(): void
    {
        $this->seed(CollegeSeeder::class);

        College::where('verification_status', VerificationStatus::Verified)->get()->each(function (College $college): void {
            $this->assertSame('2026-27', $college->cost_academic_year);
            $this->assertNotNull($college->tuition_fees_in_state, $college->name);
            $this->assertNotNull($college->cost_of_attendance_in_state, $college->name);
            $this->assertSame('Fall 2024', $college->enrollment_term);
        });
    }

    public function test_seeded_values_match_the_verified_research(): void
    {
        $this->seed(CollegeSeeder::class);

        $maryland = College::where('short_name', 'Maryland')->sole();
        $this->assertSame(31133, $maryland->undergraduate_enrollment);
        $this->assertSame(65520, $maryland->cost_of_attendance_out_of_state);
        $this->assertFalse($maryland->offers_nursing);
        $this->assertSame(CampusEnvironment::Suburban, $maryland->campus_environment);
        $this->assertSame(CollegeSize::Large, $maryland->size);

        $susquehanna = College::where('short_name', 'Susquehanna')->sole();
        $this->assertSame('Town: Distant', $susquehanna->federal_locale);
        $this->assertSame(CampusEnvironment::Rural, $susquehanna->campus_environment);
        $this->assertSame(CollegeSize::Small, $susquehanna->size);
    }

    public function test_seeder_can_be_rerun_without_duplicates(): void
    {
        $this->seed(CollegeSeeder::class);
        $sourceCount = CollegeSource::count();

        $this->seed(CollegeSeeder::class);

        $this->assertSame(25, College::count());
        $this->assertSame($sourceCount, CollegeSource::count());
    }

    public function test_colleges_without_verified_soccer_or_division_are_not_eligible(): void
    {
        $eligible = College::factory()->create();
        $noSoccer = College::factory()->withoutVerifiedSoccer()->create();
        $noDivision = College::factory()->withoutConfirmedDivision()->create();

        $this->assertSame([$eligible->id], College::eligibleForRecommendations()->pluck('id')->all());
        $this->assertTrue($eligible->isEligibleForRecommendations());
        $this->assertFalse($noSoccer->isEligibleForRecommendations());
        $this->assertFalse($noDivision->isEligibleForRecommendations());
    }

    public function test_federal_locales_map_to_questionnaire_environments(): void
    {
        $this->assertSame(CampusEnvironment::Urban, CampusEnvironment::fromFederalLocale('City: Small'));
        $this->assertSame(CampusEnvironment::Suburban, CampusEnvironment::fromFederalLocale('Suburb: Large'));
        $this->assertSame(CampusEnvironment::Rural, CampusEnvironment::fromFederalLocale('Town: Distant'));
        $this->assertSame(CampusEnvironment::Rural, CampusEnvironment::fromFederalLocale('Rural: Fringe'));
        $this->assertNull(CampusEnvironment::fromFederalLocale(null));
    }

    public function test_enrollment_maps_to_size_categories(): void
    {
        $this->assertSame(CollegeSize::Small, CollegeSize::fromEnrollment(4999));
        $this->assertSame(CollegeSize::Medium, CollegeSize::fromEnrollment(5000));
        $this->assertSame(CollegeSize::Medium, CollegeSize::fromEnrollment(15000));
        $this->assertSame(CollegeSize::Large, CollegeSize::fromEnrollment(15001));
        $this->assertNull(CollegeSize::fromEnrollment(null));
    }
}
