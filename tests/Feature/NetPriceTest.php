<?php

namespace Tests\Feature;

use App\Models\College;
use App\Recommendations\ClaudeRecommendationExplainer;
use App\Recommendations\CollegeMatch;
use App\Recommendations\CollegeMatcher;
use App\Recommendations\CostLabel;
use App\Recommendations\MatchResults;
use App\Recommendations\NetPriceComparison;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetPriceTest extends TestCase
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
            'position' => 'midfielder',
            'league' => 'ecnl_rl',
            'gpa' => '3.60',
            'test_taken' => 'none',
            'major' => 'undecided',
            'budget' => '15k_30k',
            'home_state' => 'OH',
            'setting' => 'no_preference',
            'size' => 'no_preference',
            'priority' => 'balance',
        ], $overrides);
    }

    private function match(array $overrides = []): MatchResults
    {
        return app(CollegeMatcher::class)->match($this->fictionalAthlete($overrides));
    }

    private function find(MatchResults $results, string $name): CollegeMatch
    {
        return $results->all()->first(fn (CollegeMatch $match): bool => $match->college->name === $name)
            ?? $this->fail("{$name} was not shown.");
    }

    public function test_a_public_college_average_applies_only_to_in_state_students(): void
    {
        College::factory()->publicIn('OH', 35000, 60000)->withAverageNetPrice(12000)->create(['name' => 'Fictional Ohio Public College']);

        $resident = $this->find($this->match(['home_state' => 'OH']), 'Fictional Ohio Public College')->affordability;
        $nonresident = $this->find($this->match(['home_state' => 'PA']), 'Fictional Ohio Public College')->affordability;

        $this->assertSame(NetPriceComparison::Applies, $resident->netPrice);
        $this->assertSame(CostLabel::PotentiallyAffordable, $resident->label);
        $this->assertSame(NetPriceComparison::InStateOnly, $nonresident->netPrice);
        $this->assertSame(CostLabel::CannotCompare, $nonresident->label, 'An in-state average never judges affordability for an out-of-state student.');
    }

    public function test_a_private_college_average_applies_in_every_state_but_not_outside_the_us(): void
    {
        College::factory()->withAverageNetPrice(20000)->create(['name' => 'Fictional Private College', 'state' => 'MA']);

        foreach (['OH', 'MA', 'CA'] as $state) {
            $this->assertSame(CostLabel::PotentiallyAffordable, $this->find($this->match(['home_state' => $state]), 'Fictional Private College')->affordability->label, $state);
        }

        $international = $this->find($this->match(['home_state' => 'outside_us']), 'Fictional Private College')->affordability;
        $this->assertSame(NetPriceComparison::UsAidRecipientsOnly, $international->netPrice);
        $this->assertSame(CostLabel::CannotCompare, $international->label);
    }

    public function test_missing_data_is_labeled_honestly(): void
    {
        College::factory()->create(['name' => 'Fictional No Average College']);
        College::factory()->create(['name' => 'Fictional Unknown Cost College', 'cost_of_attendance_in_state' => null, 'cost_of_attendance_out_of_state' => null]);
        College::factory()->withAverageNetPrice(18000)->create(['name' => 'Fictional Unknown Cost With Average College', 'cost_of_attendance_in_state' => null, 'cost_of_attendance_out_of_state' => null]);

        $results = $this->match();

        $noAverage = $this->find($results, 'Fictional No Average College')->affordability;
        $this->assertSame(NetPriceComparison::Unavailable, $noAverage->netPrice);
        $this->assertSame(CostLabel::AboveBudget, $noAverage->label, 'The verified published cost is still above budget.');
        $this->assertSame(CostLabel::CannotCompare, $this->find($results, 'Fictional Unknown Cost College')->affordability->label);
        $this->assertSame(CostLabel::PotentiallyAffordable, $this->find($results, 'Fictional Unknown Cost With Average College')->affordability->label);
    }

    public function test_low_budgets_compare_the_average_with_the_top_of_the_budget(): void
    {
        College::factory()->withAverageNetPrice(13000)->create(['name' => 'Fictional 13000 Average College']);
        College::factory()->withAverageNetPrice(16000)->create(['name' => 'Fictional 16000 Average College']);
        College::factory()->withAverageNetPrice(15000)->create(['name' => 'Fictional 15000 Average College']);

        $results = $this->match(['budget' => 'under_15k']);

        $this->assertSame(CostLabel::PotentiallyAffordable, $this->find($results, 'Fictional 13000 Average College')->affordability->label);
        $this->assertSame(CostLabel::PotentiallyAffordable, $this->find($results, 'Fictional 15000 Average College')->affordability->label);
        $this->assertSame(CostLabel::AboveBudget, $this->find($results, 'Fictional 16000 Average College')->affordability->label);
    }

    public function test_unsure_and_open_ended_budgets_are_not_compared(): void
    {
        College::factory()->withAverageNetPrice(10000)->create(['name' => 'Fictional Low Average College']);

        foreach (['unsure', '45k_plus'] as $budget) {
            $affordability = $this->find($this->match(['budget' => $budget]), 'Fictional Low Average College')->affordability;

            $this->assertSame(CostLabel::NotCompared, $affordability->label, $budget);
            $this->assertSame(NetPriceComparison::Applies, $affordability->netPrice, 'The average is still shown for information.');
        }
    }

    public function test_average_net_price_never_makes_a_strong_match(): void
    {
        College::factory()->withAverageNetPrice(10000)->create(['name' => 'Fictional Generous Aid College']);
        College::factory()->withAverageNetPrice(10000)->create(['name' => 'Fictional Within Budget College', 'cost_of_attendance_in_state' => 25000, 'cost_of_attendance_out_of_state' => 25000]);

        $results = $this->match();
        $generous = $this->find($results, 'Fictional Generous Aid College');

        $this->assertFalse($generous->isStrongMatch());
        $this->assertSame(CostLabel::PotentiallyAffordable, $generous->affordability->label);
        $this->assertStringContainsString('Its 2024-25 federal average net price is within your budget, so it may be worth exploring after aid.', $generous->tradeoffs['budget']);
        $this->assertSame(['Fictional Within Budget College'], $results->strongMatches->pluck('college.name')->all());
        $this->assertSame(CostLabel::WithinBudget, $results->strongMatches->first()->affordability->label);
    }

    public function test_other_options_rank_confirmed_budgets_then_potentially_affordable_then_the_rest(): void
    {
        College::factory()->create(['name' => 'Fictional Above Budget College', 'cost_of_attendance_in_state' => 40000, 'cost_of_attendance_out_of_state' => 40000]);
        College::factory()->withAverageNetPrice(20000)->create(['name' => 'Fictional Potentially Affordable College', 'cost_of_attendance_in_state' => 60000, 'cost_of_attendance_out_of_state' => 60000]);
        College::factory()->create(['name' => 'Fictional Wrong Size College', 'undergraduate_enrollment' => 30000, 'cost_of_attendance_in_state' => 25000, 'cost_of_attendance_out_of_state' => 25000]);

        $results = $this->match(['size' => 'medium']);

        $this->assertSame(
            ['Fictional Wrong Size College', 'Fictional Potentially Affordable College', 'Fictional Above Budget College'],
            $results->otherOptions->pluck('college.name')->all(),
            'All three have one tradeoff. A confirmed published-cost budget comes first, then potentially affordable, even though its published cost is the highest.',
        );
        $this->assertSame(1, $results->potentiallyAffordableCount());
    }

    public function test_the_ranking_boost_does_not_bypass_preferences_division_or_major(): void
    {
        College::factory()->create(['name' => 'Fictional Strong College', 'ncaa_division' => 3, 'offers_education' => true, 'federal_locale' => 'City: Large', 'cost_of_attendance_in_state' => 25000, 'cost_of_attendance_out_of_state' => 25000]);
        College::factory()->withAverageNetPrice(12000)->create(['name' => 'Fictional Poor Fit College', 'ncaa_division' => 3, 'offers_education' => null, 'undergraduate_enrollment' => 30000, 'federal_locale' => 'Rural: Remote', 'merit_aid' => 'not_offered']);
        College::factory()->withAverageNetPrice(12000)->create(['name' => 'Fictional Other Division College', 'ncaa_division' => 1, 'offers_education' => true]);
        College::factory()->withAverageNetPrice(12000)->create(['name' => 'Fictional No Education College', 'ncaa_division' => 3, 'offers_education' => false]);

        $results = $this->match(['division' => 'division_3', 'major' => 'education', 'size' => 'medium', 'setting' => 'urban']);
        $shown = $results->all()->pluck('college.name')->all();

        $this->assertSame(['Fictional Strong College'], $shown, 'A potentially affordable college must still meet enough preferences, the division, and the major.');
    }

    public function test_the_ai_request_never_includes_net_price(): void
    {
        $this->seed(CollegeSeeder::class);
        $answers = $this->fictionalAthlete(['division' => 'division_3', 'major' => 'education', 'budget' => '30k_45k', 'home_state' => 'PA', 'size' => 'medium']);
        $results = app(CollegeMatcher::class)->match($answers);

        $request = (new ClaudeRecommendationExplainer(apiKey: null, model: 'test'))->buildRequest($answers, $results->matches);

        $this->assertStringNotContainsStringIgnoringCase('net price', $request);
        $this->assertStringNotContainsString('32,132', $request);
        $this->assertStringNotContainsString('32132', $request);
    }

    public function test_cards_show_both_years_the_label_and_expandable_cost_details(): void
    {
        $this->seed(CollegeSeeder::class);

        $response = $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'education',
            'budget' => '30k_45k',
            'home_state' => 'PA',
            'size' => 'medium',
        ]));

        $response->assertOk()
            ->assertSee('<span class="pill cost-maybe">Potentially affordable after aid</span>', false)
            ->assertSee('Published cost, 2026-27 (before financial aid)')
            ->assertSee('Federal average net price, 2024-25 (after grants and scholarships)')
            ->assertSee('$32,132 per year')
            ->assertSee("This average is within your family's budget, so this college may be worth exploring after financial aid.", false)
            ->assertSee('It is not a personalized estimate or a scholarship guarantee.')
            ->assertSee('<summary>More cost details</summary>', false)
            ->assertSee('Families earning over $110,000 who received federal aid paid an average of')
            ->assertSee('$38,921 per year')
            ->assertSee('https://collegescorecard.ed.gov/school/?215929')
            ->assertSee('U.S. Department of Education College Scorecard (2024-25 data)')
            ->assertSee("You can find it on Scranton's financial aid website.", false)
            ->assertSee('href="https://financialaid.washu.edu/costs/cost-calculators/net-price-calculator"', false)
            ->assertSee('(link the college reported to the U.S. Department of Education)')
            ->assertSee('<span class="pill cost-neutral">Can&#039;t compare</span>', false)
            ->assertSee('Not compared for you. At public colleges, the federal average covers only students who pay in-state tuition')
            ->assertDontSee('$26,752', false);
    }

    public function test_very_expensive_colleges_repeat_the_full_cost_next_to_the_average(): void
    {
        $this->seed(CollegeSeeder::class);

        $response = $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'education',
            'budget' => '30k_45k',
            'home_state' => 'NJ',
            'size' => 'medium',
        ]));

        $html = $response->assertOk()->getContent();
        $washu = substr($html, strpos($html, 'Washington University in St Louis'));
        $washu = substr($washu, 0, strpos($washu, '</article>'));
        $tcnj = substr($html, strpos($html, '<h2>The College of New Jersey</h2>'));
        $tcnj = substr($tcnj, 0, strpos($tcnj, '</article>'));

        $this->assertStringContainsString('<strong>Full published cost: $102,260 per year.</strong>', $washu);
        $this->assertStringContainsString('<strong>not a personalized estimate</strong>', $washu);
        $this->assertStringContainsString("Families who don't receive aid pay the full published cost", $washu);
        $this->assertStringNotContainsString('class="price-gap"', $tcnj, 'TCNJ costs $44,640 in-state against a $26,752 average, which is less than twice the average.');
    }

    public function test_the_seeded_averages_match_the_federal_data_we_retrieved(): void
    {
        $this->seed(CollegeSeeder::class);

        $tcnj = College::where('short_name', 'TCNJ')->sole();
        $washu = College::where('short_name', 'WashU')->sole();

        $this->assertTrue($tcnj->is_public);
        $this->assertSame(26752, $tcnj->average_net_price);
        $this->assertSame('2024-25', $tcnj->average_net_price_year);
        $this->assertFalse($washu->is_public);
        $this->assertSame(13360, $washu->average_net_price);
        $this->assertSame(47130, $washu->average_net_price_high_income);
        $this->assertSame(41, College::whereNotNull('average_net_price')->count());
        $this->assertSame(41, College::whereHas('sources', fn ($query) => $query->where('topic', 'net_price'))->count());
    }
}
