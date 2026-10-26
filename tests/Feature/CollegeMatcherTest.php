<?php

namespace Tests\Feature;

use App\Models\College;
use App\Recommendations\Affordability;
use App\Recommendations\AffordabilityStatus;
use App\Recommendations\CollegeMatch;
use App\Recommendations\CollegeMatcher;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollegeMatcherTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Answers from a fictional athlete.
     *
     * @return array<string, mixed>
     */
    private function fictionalAthlete(array $overrides = []): array
    {
        return array_merge([
            'sport' => 'womens_soccer',
            'graduation_year' => now()->year + 2,
            'division' => 'open',
            'position' => 'defender',
            'league' => 'npl',
            'gpa' => '3.40',
            'test_taken' => 'none',
            'major' => 'undecided',
            'budget' => 'unsure',
            'home_state' => 'OH',
            'setting' => 'no_preference',
            'size' => 'no_preference',
            'priority' => 'balance',
        ], $overrides);
    }

    private function matcher(): CollegeMatcher
    {
        return app(CollegeMatcher::class);
    }

    public function test_it_returns_three_to_five_colleges_for_broad_preferences(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete());

        $this->assertCount(CollegeMatcher::MAXIMUM_RESULTS, $results->matches);
        $this->assertFalse($results->hasShortfall());
    }

    public function test_it_only_returns_the_chosen_division(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete(['division' => 'division_2']));

        $this->assertNotEmpty($results->matches);
        $results->matches->each(fn (CollegeMatch $match) => $this->assertSame(2, $match->college->ncaa_division));
    }

    public function test_it_never_recommends_colleges_known_not_to_offer_the_major(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete(['major' => 'nursing']));

        $this->assertNotEmpty($results->matches);
        $results->matches->each(fn (CollegeMatch $match) => $this->assertNotFalse($match->college->offers_nursing));
    }

    public function test_colleges_without_verified_soccer_or_division_are_never_recommended(): void
    {
        $perfectFit = ['federal_locale' => 'City: Large', 'undergraduate_enrollment' => 2000, 'cost_of_attendance_out_of_state' => 10000];
        College::factory()->withoutVerifiedSoccer()->create([...$perfectFit, 'name' => 'Fictional No Soccer College']);
        College::factory()->withoutConfirmedDivision()->create([...$perfectFit, 'name' => 'Fictional No Division College']);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'budget' => 'under_15k', 'setting' => 'urban', 'size' => 'small',
        ]));

        $this->assertTrue($results->matches->isEmpty());
        $this->assertTrue($results->hasShortfall());
    }

    public function test_unknown_program_information_is_flagged_not_assumed(): void
    {
        College::factory()->count(3)->create(['offers_education' => null]);

        $results = $this->matcher()->match($this->fictionalAthlete(['major' => 'education']));

        $this->assertCount(3, $results->matches);
        $results->matches->each(function (CollegeMatch $match): void {
            $this->assertStringContainsString("could not confirm whether it offers a bachelor's degree in Education", $match->unknowns[0]);
            $this->assertNotContains('Offers Education (federal degree data)', $match->matchedOn);
        });
    }

    public function test_unknown_costs_are_flagged_and_never_estimated(): void
    {
        $college = College::factory()->create([
            'tuition_fees_in_state' => null,
            'tuition_fees_out_of_state' => null,
            'cost_of_attendance_in_state' => null,
            'cost_of_attendance_out_of_state' => null,
            'cost_of_attendance_is_complete' => null,
        ]);

        $match = $this->matcher()->match($this->fictionalAthlete(['budget' => '15k_30k', 'setting' => 'suburban']))->matches->first();

        $this->assertTrue($match->college->is($college));
        $this->assertSame(AffordabilityStatus::CostUnknown, $match->affordability->status);
        $this->assertNull($match->affordability->cost);
        $this->assertNull($match->affordability->gap);
        $this->assertStringContainsString('cost of attendance is not verified yet', implode(' ', $match->unknowns));
        $this->assertStringNotContainsString('$', implode(' ', [...$match->unknowns, ...$match->matchedOn]));
    }

    public function test_affordability_ranks_lower_published_costs_first(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'budget' => 'under_15k',
            'priority' => 'affordability',
        ]));

        $costs = $results->matches->map(fn (CollegeMatch $match): ?int => $match->affordability->cost)->filter();
        $this->assertNotEmpty($costs);
        $this->assertSame($costs->min(), $results->matches->first()->affordability->cost);
        $this->assertTrue($results->matches->first()->affordability->isAboveBudget());
    }

    public function test_a_published_cost_above_budget_is_never_described_as_affordable(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete(['budget' => 'under_15k']));

        $this->assertSame($results->matches->count(), $results->aboveBudgetCount() + $results->unknownCostCount());
        $results->matches->each(fn (CollegeMatch $match) => $this->assertNotContains(
            'Published cost is within your family\'s budget range, even before aid',
            $match->matchedOn,
        ));
    }

    public function test_public_colleges_use_the_in_state_rate_for_residents(): void
    {
        $this->seed(CollegeSeeder::class);
        $rutgers = College::where('short_name', 'Rutgers')->sole();

        $resident = Affordability::for($rutgers, 'NJ', CollegeMatcher::BUDGET_RANGES['30k_45k']);
        $nonResident = Affordability::for($rutgers, 'PA', CollegeMatcher::BUDGET_RANGES['30k_45k']);

        $this->assertSame(41332, $resident->cost);
        $this->assertSame('in-state', $resident->rate);
        $this->assertSame(62782, $nonResident->cost);
        $this->assertSame('out-of-state', $nonResident->rate);
        $this->assertSame(62782 - 45000, $nonResident->gap);
    }

    public function test_athletes_outside_the_us_see_out_of_state_rates(): void
    {
        $this->seed(CollegeSeeder::class);
        $usf = College::where('short_name', 'USF')->sole();

        $affordability = Affordability::for($usf, 'outside_us', CollegeMatcher::BUDGET_RANGES['15k_30k']);

        $this->assertSame(40134, $affordability->cost);
        $this->assertSame('out-of-state', $affordability->rate);
    }

    public function test_private_colleges_charge_everyone_the_same_published_price(): void
    {
        $this->seed(CollegeSeeder::class);
        $georgetown = College::where('short_name', 'Georgetown')->sole();

        $local = Affordability::for($georgetown, 'DC', null);
        $visitor = Affordability::for($georgetown, 'CA', null);

        $this->assertSame(100864, $local->cost);
        $this->assertSame($local->cost, $visitor->cost);
        $this->assertNull($local->rate);
        $this->assertSame(AffordabilityStatus::NoBudget, $local->status);
    }

    public function test_residency_changes_which_public_colleges_rank_highest(): void
    {
        $this->seed(CollegeSeeder::class);
        $answers = ['division' => 'division_1', 'budget' => '15k_30k', 'priority' => 'affordability'];

        $northCarolinian = $this->matcher()->match($this->fictionalAthlete([...$answers, 'home_state' => 'NC']));
        $floridian = $this->matcher()->match($this->fictionalAthlete([...$answers, 'home_state' => 'FL']));

        $this->assertSame('UNC', $northCarolinian->matches->first()->college->short_name);
        $this->assertSame('in-state', $northCarolinian->matches->first()->affordability->rate);
        $this->assertSame('USF', $floridian->matches->first()->college->short_name);
        $this->assertSame(27248, $floridian->matches->first()->affordability->cost);
    }

    public function test_affordability_statuses_compare_published_cost_with_the_budget_range(): void
    {
        $college = College::factory()->make(['cost_of_attendance_in_state' => 40000, 'cost_of_attendance_out_of_state' => 40000]);

        $this->assertSame(AffordabilityStatus::WithinBudget, Affordability::for($college, 'OH', CollegeMatcher::BUDGET_RANGES['30k_45k'])->status);
        $this->assertSame(AffordabilityStatus::AboveBudget, Affordability::for($college, 'OH', CollegeMatcher::BUDGET_RANGES['15k_30k'])->status);
        $this->assertSame(10000, Affordability::for($college, 'OH', CollegeMatcher::BUDGET_RANGES['15k_30k'])->gap);
        $this->assertSame(AffordabilityStatus::WithinBudget, Affordability::for($college, 'OH', CollegeMatcher::BUDGET_RANGES['45k_plus'])->status);

        $expensive = College::factory()->make(['cost_of_attendance_in_state' => 80000, 'cost_of_attendance_out_of_state' => 80000]);
        $this->assertSame(AffordabilityStatus::MayExceedBudget, Affordability::for($expensive, 'OH', CollegeMatcher::BUDGET_RANGES['45k_plus'])->status);
        $this->assertNull(Affordability::for($expensive, 'OH', CollegeMatcher::BUDGET_RANGES['45k_plus'])->gap);
    }

    public function test_it_shows_fewer_results_and_explains_why_instead_of_poor_matches(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'division' => 'division_1',
            'major' => 'nursing',
            'budget' => 'under_15k',
            'setting' => 'rural',
            'size' => 'small',
        ]));

        $this->assertTrue($results->hasShortfall());
        $this->assertLessThan(CollegeMatcher::MINIMUM_RESULTS, $results->matches->count());
        $results->matches->each(fn (CollegeMatch $match) => $this->assertGreaterThanOrEqual(CollegeMatcher::MINIMUM_SCORE, $match->score));
        $this->assertStringContainsString('8 of them compete in Division I', implode(' ', $results->shortfallReasons));
    }

    public function test_preference_mismatches_become_tradeoffs(): void
    {
        College::factory()->create(['federal_locale' => 'Town: Distant', 'undergraduate_enrollment' => 2000]);

        $match = $this->matcher()->match($this->fictionalAthlete(['setting' => 'urban', 'size' => 'small']))->matches->first();

        $this->assertSame('Its campus is Rural, not urban.', $match->tradeoff);
    }
}
