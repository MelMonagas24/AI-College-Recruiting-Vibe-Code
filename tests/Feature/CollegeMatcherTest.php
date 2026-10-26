<?php

namespace Tests\Feature;

use App\Models\College;
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
        $this->assertNull($match->costGap);
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

        $gaps = $results->matches->pluck('costGap')->filter(fn (?int $gap): bool => $gap !== null);
        $this->assertNotEmpty($gaps);
        $this->assertSame($gaps->min(), $results->matches->first()->costGap);
        $this->assertStringContainsString('grants or scholarships would need to cover', $results->matches->first()->tradeoff);
    }

    public function test_a_published_cost_above_budget_is_never_described_as_affordable(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete(['budget' => 'under_15k']));

        $results->matches
            ->filter(fn (CollegeMatch $match): bool => $match->costGap > 0)
            ->each(fn (CollegeMatch $match) => $this->assertNotContains(
                'Published cost is within your family\'s budget range, even before aid',
                $match->matchedOn,
            ));
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
