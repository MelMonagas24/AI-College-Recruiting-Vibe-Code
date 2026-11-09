<?php

namespace Tests\Feature;

use App\Models\College;
use App\Recommendations\Affordability;
use App\Recommendations\AffordabilityStatus;
use App\Recommendations\CollegeMatch;
use App\Recommendations\CollegeMatcher;
use App\Recommendations\CostLabel;
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

        $this->assertFalse($results->hasStrongMatches());
        $this->assertCount(3, $results->otherOptions);
        $results->otherOptions->each(function (CollegeMatch $match): void {
            $this->assertStringContainsString("We couldn't confirm that it offers a bachelor's degree in Education, so this match can't be confirmed.", $match->tradeoffs['major']);
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
        $this->assertFalse($match->isStrongMatch());
        $this->assertSame(['budget' => "Its 2026-27 cost of attendance isn't verified, so we can't confirm it fits your family's budget."], $match->tradeoffs);
        $this->assertStringNotContainsString('$', implode(' ', [...$match->unknowns, ...$match->matchedOn, ...$match->tradeoffs]));
    }

    public function test_affordability_ranks_lower_published_costs_first(): void
    {
        foreach ([60000, 30000, 45000] as $cost) {
            College::factory()->create(['name' => "Fictional {$cost} College", 'cost_of_attendance_in_state' => $cost, 'cost_of_attendance_out_of_state' => $cost]);
        }

        $results = $this->matcher()->match($this->fictionalAthlete([
            'budget' => 'under_15k',
            'priority' => 'affordability',
        ]));

        $this->assertSame(30000, $results->matches->first()->affordability->cost);
        $this->assertTrue($results->matches->first()->affordability->isAboveBudget());
    }

    public function test_a_published_cost_above_budget_is_never_described_as_affordable(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete(['budget' => 'under_15k']));

        $this->assertSame($results->all()->count(), $results->aboveBudgetCount() + $results->unknownCostCount());
        $this->assertFalse($results->hasStrongMatches());
        $results->all()->each(fn (CollegeMatch $match) => $this->assertNotContains(
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

    public function test_with_a_strong_match_it_does_not_pad_results_with_poor_alternatives(): void
    {
        College::factory()->create(['ncaa_division' => 1, 'cost_of_attendance_in_state' => 14000, 'cost_of_attendance_out_of_state' => 14000]);
        College::factory()->count(3)->create(['ncaa_division' => 1, 'undergraduate_enrollment' => 30000, 'merit_aid' => 'not_offered']);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'division' => 'division_1',
            'budget' => 'under_15k',
            'size' => 'medium',
            'priority' => 'affordability',
        ]));

        $this->assertCount(1, $results->strongMatches);
        $this->assertCount(0, $results->otherOptions);
        $this->assertTrue($results->hasShortfall());
        $this->assertStringContainsString('4 of them compete in Division I', implode(' ', $results->shortfallReasons));
        $this->assertStringContainsString('Rather than suggest poor matches', implode(' ', $results->shortfallReasons));
    }

    public function test_it_explains_when_the_division_and_major_leave_fewer_than_three_colleges(): void
    {
        College::factory()->count(2)->create(['ncaa_division' => 2]);
        College::factory()->count(3)->create(['ncaa_division' => 3]);

        $results = $this->matcher()->match($this->fictionalAthlete(['division' => 'division_2', 'budget' => 'under_15k']));

        $this->assertTrue($results->hasShortfall());
        $this->assertCount(2, $results->all());
        $this->assertStringContainsString('2 of them compete in Division II', implode(' ', $results->shortfallReasons));
        $this->assertStringContainsString('All 2 are shown below.', implode(' ', $results->shortfallReasons));
    }

    public function test_campus_setting_is_a_note_that_never_demotes_a_strong_match(): void
    {
        College::factory()->create(['federal_locale' => 'Town: Distant', 'undergraduate_enrollment' => 2000]);

        $match = $this->matcher()->match($this->fictionalAthlete(['setting' => 'urban', 'size' => 'small']))->matches->first();

        $this->assertTrue($match->isStrongMatch());
        $this->assertContains('Its campus is Rural, not urban.', $match->notes);
    }

    public function test_a_strong_match_meets_major_size_and_budget_at_the_correct_rate(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'education',
            'budget' => '30k_45k',
            'home_state' => 'NJ',
            'size' => 'medium',
            'priority' => 'affordability',
        ]));

        $this->assertSame(['TCNJ', 'UW-La Crosse'], $results->strongMatches->pluck('college.short_name')->all());
        $this->assertSame([29247, 'out-of-state'], [$results->strongMatches[1]->affordability->cost, $results->strongMatches[1]->affordability->rate], 'UW-La Crosse fits even at its out-of-state rate.');
        $tcnj = $results->strongMatches->first();
        $this->assertSame(44640, $tcnj->affordability->cost);
        $this->assertSame('in-state', $tcnj->affordability->rate);
        $this->assertSame([], $tcnj->tradeoffs);

        $this->assertSame('Montclair State', $results->otherOptions[0]->college->short_name, 'Its published cost is confirmed within budget, so it comes first.');
        $this->assertSame(['size' => 'It\'s a Large college (18,712 undergraduates, Fall 2024), not medium.'], $results->otherOptions[0]->tradeoffs);

        $scranton = $results->otherOptions->first(fn (CollegeMatch $match): bool => $match->college->short_name === 'Scranton');
        $this->assertCount(2, $scranton->tradeoffs, 'Scranton is above budget and small, and both tradeoffs are listed.');
        $this->assertStringContainsString('is $30,710 above the top of your family\'s budget ($30,000–$45,000). Financial aid could close some or all of this gap', $scranton->tradeoffs['budget']);
    }

    public function test_out_of_state_pricing_can_turn_a_strong_match_into_an_other_option(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'education',
            'budget' => '30k_45k',
            'home_state' => 'PA',
            'size' => 'medium',
            'priority' => 'affordability',
        ]));

        $this->assertSame(['UW-La Crosse'], $results->strongMatches->pluck('college.short_name')->all(), 'TCNJ is no longer a Strong Match at the out-of-state rate.');
        $tcnj = $results->otherOptions->first(fn (CollegeMatch $match): bool => $match->college->short_name === 'TCNJ');
        $this->assertSame('out-of-state', $tcnj->affordability->rate);
        $this->assertSame(CostLabel::CannotCompare, $tcnj->affordability->label, 'The in-state federal average is never used for an out-of-state athlete.');
        $this->assertSame(['budget' => 'Its published cost, $51,174 per year (out-of-state rate), is $6,174 above the top of your family\'s budget ($30,000–$45,000). Financial aid could close some or all of this gap; the college\'s Net Price Calculator can estimate it. This college offers merit scholarships.'], $tcnj->tradeoffs);
    }

    public function test_other_options_with_fewer_tradeoffs_are_listed_first(): void
    {
        College::factory()->create(['name' => 'Fictional Two Tradeoffs College', 'undergraduate_enrollment' => 30000, 'cost_of_attendance_in_state' => 31000, 'cost_of_attendance_out_of_state' => 31000]);
        College::factory()->create(['name' => 'Fictional One Tradeoff College', 'cost_of_attendance_in_state' => 70000, 'cost_of_attendance_out_of_state' => 70000]);

        $results = $this->matcher()->match($this->fictionalAthlete(['budget' => '15k_30k', 'size' => 'medium']));

        $this->assertSame(
            ['Fictional One Tradeoff College', 'Fictional Two Tradeoffs College'],
            $results->otherOptions->pluck('college.name')->all(),
        );
    }

    public function test_an_open_ended_budget_is_met_by_any_verified_cost_and_still_shows_the_cost(): void
    {
        College::factory()->create(['cost_of_attendance_in_state' => 90000, 'cost_of_attendance_out_of_state' => 90000]);

        $match = $this->matcher()->match($this->fictionalAthlete(['budget' => '45k_plus']))->matches->first();

        $this->assertTrue($match->isStrongMatch());
        $this->assertSame(AffordabilityStatus::MayExceedBudget, $match->affordability->status);
        $this->assertSame(['budget' => 'You chose an open-ended budget ($45,000+), so this college still counts for the budget check. Its published cost is $90,000 per year before aid. Your family will need to decide whether that is affordable.'], $match->notes);
        $this->assertStringNotContainsString('within', implode(' ', [...$match->matchedOn, ...$match->notes]), 'An expensive college is never called "within budget".');
    }

    public function test_an_unsure_budget_is_never_presented_as_checked(): void
    {
        College::factory()->create(['name' => 'Fictional Known Cost College', 'cost_of_attendance_in_state' => 30000, 'cost_of_attendance_out_of_state' => 30000]);
        College::factory()->create(['name' => 'Fictional Unknown Cost College', 'cost_of_attendance_in_state' => null, 'cost_of_attendance_out_of_state' => null]);

        $results = $this->matcher()->match($this->fictionalAthlete(['budget' => 'unsure']));

        $this->assertCount(2, $results->strongMatches, 'Unknown costs are not penalized when the budget is "Unsure".');
        $notes = $results->strongMatches->mapWithKeys(fn (CollegeMatch $match): array => [$match->college->name => $match->notes]);
        $this->assertSame(['budget' => 'Affordability has not been checked: you chose "Unsure" for your family\'s budget, so this college\'s published cost ($30,000 per year, before aid) was not compared with a budget.'], $notes['Fictional Known Cost College']);
        $this->assertSame(['budget' => 'Affordability has not been checked or confirmed: you chose "Unsure" for your family\'s budget, and this college\'s 2026-27 cost of attendance is not verified yet.'], $notes['Fictional Unknown Cost College']);
        $results->strongMatches->each(fn (CollegeMatch $match) => $this->assertStringNotContainsString('budget range', implode(' ', $match->matchedOn)));
    }

    public function test_unverified_cost_or_size_is_never_a_strong_match(): void
    {
        College::factory()->create(['name' => 'Fictional Unknown Size College', 'undergraduate_enrollment' => null, 'cost_of_attendance_in_state' => 20000, 'cost_of_attendance_out_of_state' => 20000]);
        College::factory()->create(['name' => 'Fictional Unknown Cost College', 'cost_of_attendance_in_state' => null, 'cost_of_attendance_out_of_state' => null]);

        $results = $this->matcher()->match($this->fictionalAthlete(['budget' => '15k_30k', 'size' => 'medium']));

        $this->assertFalse($results->hasStrongMatches());
        $tradeoffs = $results->otherOptions->mapWithKeys(fn (CollegeMatch $match): array => [$match->college->name => $match->tradeoffs]);
        $this->assertSame(['size' => "Its undergraduate enrollment isn't verified, so we can't confirm it's a medium college."], $tradeoffs['Fictional Unknown Size College']);
        $this->assertSame(['budget' => "Its 2026-27 cost of attendance isn't verified, so we can't confirm it fits your family's budget."], $tradeoffs['Fictional Unknown Cost College']);
    }

    public function test_no_preference_unsure_and_undecided_are_not_checked(): void
    {
        College::factory()->create(['undergraduate_enrollment' => 30000, 'cost_of_attendance_in_state' => 90000, 'cost_of_attendance_out_of_state' => 90000]);

        $answers = $this->fictionalAthlete(['major' => 'undecided', 'size' => 'no_preference', 'budget' => 'unsure']);
        $results = $this->matcher()->match($answers);

        $this->assertTrue($results->strongMatches->first()->isStrongMatch());
        $this->assertSame(['major', 'size', 'budget'], CollegeMatcher::uncheckedCriteria($answers));
        $this->assertSame([], CollegeMatcher::uncheckedCriteria(['major' => 'biology', 'size' => 'small', 'budget' => '15k_30k']));
    }

    public function test_with_no_strong_matches_the_closest_alternatives_are_shown_and_labeled(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'division' => 'division_1',
            'major' => 'education',
            'budget' => '15k_30k',
            'size' => 'small',
            'setting' => 'rural',
            'priority' => 'affordability',
        ]));

        $this->assertFalse($results->hasStrongMatches());
        $this->assertCount(CollegeMatcher::MINIMUM_RESULTS, $results->otherOptions);
        $this->assertFalse($results->hasShortfall());
        $this->assertSame(['Ohio State', 'USF', 'Monmouth'], $results->otherOptions->pluck('college.short_name')->all());
        $this->assertTrue($results->otherOptions->first()->affordability->isPotentiallyAffordable(), 'Ohio State is in-state for this Ohio athlete, and its federal average is within budget.');
        $this->assertTrue($results->otherOptions->first()->meetsMinimumScore());
        $this->assertFalse($results->otherOptions->last()->meetsMinimumScore(), 'Fallback alternatives are marked so the page can label them.');
        $results->otherOptions->each(fn (CollegeMatch $match) => $this->assertNotEmpty($match->tradeoffs));
    }

    public function test_the_expanded_database_gives_a_small_college_nursing_athlete_real_division_ii_options(): void
    {
        $this->seed(CollegeSeeder::class);

        $results = $this->matcher()->match($this->fictionalAthlete([
            'division' => 'division_2',
            'major' => 'nursing',
            'budget' => '15k_30k',
            'size' => 'small',
            'priority' => 'affordability',
        ]));

        $this->assertGreaterThanOrEqual(5, $results->otherOptions->count());
        $this->assertTrue($results->otherOptions->every(fn (CollegeMatch $match): bool => $match->meetsMinimumScore()), 'No fallback alternatives are needed any more.');
        $this->assertContains('Midwestern State', $results->otherOptions->pluck('college.short_name')->all());
        $this->assertContains('Central Missouri', $results->otherOptions->pluck('college.short_name')->all());
    }

    public function test_full_cards_and_explanations_are_split_between_the_categories(): void
    {
        College::factory()->count(6)->create(['offers_education' => true, 'cost_of_attendance_in_state' => 20000, 'cost_of_attendance_out_of_state' => 20000]);
        College::factory()->count(4)->create(['offers_education' => null, 'cost_of_attendance_in_state' => 20000, 'cost_of_attendance_out_of_state' => 20000]);

        $results = $this->matcher()->match($this->fictionalAthlete(['major' => 'education', 'budget' => '15k_30k']));

        $this->assertCount(5, $results->featuredStrongMatches());
        $this->assertCount(1, $results->moreStrongMatches());
        $this->assertCount(3, $results->featuredOtherOptions());
        $this->assertCount(1, $results->moreOtherOptions());
        $this->assertCount(CollegeMatcher::MAXIMUM_RESULTS, $results->matches, 'Personalized explanations stay limited to five colleges.');
        $this->assertTrue($results->matches->every(fn (CollegeMatch $match): bool => $match->isStrongMatch()));
    }
}
