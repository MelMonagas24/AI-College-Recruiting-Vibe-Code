<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\CollegeSource;
use App\Recommendations\ExplanationResult;
use App\Recommendations\MatchResults;
use App\Recommendations\RecommendationExplainer;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationResultsTest extends TestCase
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
            'graduation_year' => now()->year + 1,
            'division' => 'division_2',
            'position' => 'goalkeeper',
            'league' => 'ga_aspire',
            'gpa' => '3.50',
            'test_taken' => 'act',
            'act_score' => 27,
            'major' => 'business',
            'budget' => '30k_45k',
            'home_state' => 'NJ',
            'setting' => 'no_preference',
            'size' => 'no_preference',
            'priority' => 'affordability',
        ], $overrides);
    }

    private function useExplainer(ExplanationResult $result): void
    {
        $this->app->instance(RecommendationExplainer::class, new class($result) implements RecommendationExplainer
        {
            public function __construct(private ExplanationResult $result) {}

            public function explain(array $answers, MatchResults $results): ExplanationResult
            {
                return $this->result;
            }
        });
    }

    public function test_results_show_matches_with_verified_facts_and_no_ai_label_without_a_key(): void
    {
        $this->seed(CollegeSeeder::class);

        $response = $this->post(route('questionnaire.store'), $this->fictionalAthlete());

        $response->assertOk()
            ->assertSee('Your college matches')
            ->assertSee('NCAA Division II')
            ->assertSee('Personalized explanations are unavailable.')
            ->assertSee('Personalized explanation unavailable')
            ->assertSee('before financial aid')
            ->assertDontSee('AI-generated');

        $this->assertGreaterThanOrEqual(3, substr_count($response->getContent(), '<article class="card match">'));
        $this->assertLessThanOrEqual(5, substr_count($response->getContent(), '<article class="card match">'));
    }

    public function test_ai_explanations_are_labeled_when_available(): void
    {
        $this->seed(CollegeSeeder::class);
        $tampa = College::where('short_name', 'Tampa')->sole();
        $this->useExplainer(ExplanationResult::generated([$tampa->id => 'Tampa offers Business and plays in Division II.']));

        $this->post(route('questionnaire.store'), $this->fictionalAthlete())
            ->assertOk()
            ->assertSee('AI-generated')
            ->assertSee('Tampa offers Business and plays in Division II.')
            ->assertDontSee('Personalized explanations are unavailable.');
    }

    public function test_a_failed_ai_service_shows_a_clear_message(): void
    {
        $this->seed(CollegeSeeder::class);
        $this->useExplainer(ExplanationResult::failed('The AI service could not be reached. Check your internet connection and try again.'));

        $this->post(route('questionnaire.store'), $this->fictionalAthlete())
            ->assertOk()
            ->assertSee('Personalized explanations are unavailable.')
            ->assertSee('The AI service could not be reached.')
            ->assertSee('Your college matches')
            ->assertDontSee('AI-generated');
    }

    public function test_unverified_costs_are_shown_as_unknown(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'undecided',
            'setting' => 'urban',
            'size' => 'medium',
            'budget' => 'unsure',
            'priority' => 'balance',
        ]))
            ->assertOk()
            ->assertSee('University of Chicago')
            ->assertSee('Not verified yet');
    }

    public function test_fewer_matches_are_explained(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_1',
            'major' => 'nursing',
            'budget' => 'under_15k',
            'setting' => 'rural',
            'size' => 'small',
            'priority' => 'balance',
            'home_state' => 'OH',
        ]))
            ->assertOk()
            ->assertSee("Why you're seeing fewer than 3 matches", false)
            ->assertSee('Rather than suggest poor matches');
    }

    public function test_results_never_promise_outcomes_and_keep_the_demo_notice(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete())
            ->assertOk()
            ->assertSee('Demo only')
            ->assertSee('do not guarantee admission, scholarships, roster spots, or recruitment')
            ->assertSee('does not decide which NCAA division you can play in');
    }

    public function test_costs_above_budget_get_a_clear_warning_and_aid_reminder(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'undecided',
            'budget' => 'under_15k',
            'home_state' => 'NJ',
        ]))
            ->assertOk()
            ->assertSee('Affordability check')
            ->assertSee("a published cost above your family's estimated budget (Under $15,000 per year)", false)
            ->assertSee("Published cost is above your family's budget", false)
            ->assertSee("Grants and scholarships could lower what you would actually pay, but we can't predict how much.", false)
            ->assertSee('Net Price Calculator')
            ->assertDontSee("Published cost is within your family's budget", false);
    }

    public function test_public_colleges_show_the_in_state_rate_to_residents(): void
    {
        $this->seed(CollegeSeeder::class);
        $answers = ['division' => 'division_3', 'major' => 'undecided', 'budget' => '30k_45k', 'size' => 'medium', 'setting' => 'suburban'];

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([...$answers, 'home_state' => 'NJ']))
            ->assertOk()
            ->assertSee('The College of New Jersey')
            ->assertSee('$44,640 per year (in-state rate)')
            ->assertSee('In-state rate: you live in New Jersey');

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([...$answers, 'home_state' => 'NY']))
            ->assertOk()
            ->assertSee('$51,174 per year (out-of-state rate)')
            ->assertSee('Out-of-state rate: this public college is in New Jersey, and you live in New York.');
    }

    public function test_unverified_costs_are_never_called_affordable(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_3',
            'major' => 'undecided',
            'setting' => 'urban',
            'size' => 'medium',
            'budget' => '45k_plus',
            'priority' => 'balance',
        ]))
            ->assertOk()
            ->assertSee('University of Chicago')
            ->assertSee('Cost not verified')
            ->assertSee("We won't guess.", false);
    }

    public function test_the_questionnaire_asks_for_a_home_state_and_requires_it(): void
    {
        $this->get(route('questionnaire.create'))
            ->assertOk()
            ->assertSee('What U.S. state do you live in?')
            ->assertSee('pick the state of your fictional athlete')
            ->assertSee('<option value="NJ" >New Jersey</option>', false);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete(['home_state' => '']))
            ->assertSessionHasErrors('home_state');

        $this->post(route('questionnaire.store'), $this->fictionalAthlete(['home_state' => 'ZZ']))
            ->assertSessionHasErrors('home_state');
    }

    public function test_every_card_explains_academic_context_without_predicting_admission(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete(['test_taken' => 'none', 'act_score' => null]))
            ->assertOk()
            ->assertSee('Academic context')
            ->assertSee('Overall admission rate:')
            ->assertSee('This describes all applicants, not your individual chances. The process for recruited athletes can differ.')
            ->assertSee("You haven't entered an SAT or ACT score")
            ->assertSee('Academic fit can change the order of your matches but never removes a college')
            ->assertSee('GPA is shown for context only.')
            ->assertDontSee('chances of getting in')
            ->assertDontSee('safety school');
    }

    public function test_cards_compare_reliable_scores_and_show_gpa_as_context(): void
    {
        $college = College::factory()->create([
            'name' => 'Fictional Common Data College',
            'ncaa_division' => 1,
            'admissions_data_source' => College::COMMON_DATA_SET,
            'admissions_term' => 'Fall 2025',
            'admission_rate' => 13.5,
            'test_policy' => 'required',
            'test_policy_term' => 'Fall 2027 applicants',
            'sat_composite_25th' => 1405,
            'sat_composite_75th' => 1530,
            'sat_submitted_percent' => 78,
            'average_high_school_gpa' => 4.41,
            'gpa_submitted_percent' => 95,
        ]);
        $college->sources()->create(['topic' => 'admissions', 'url' => 'https://example.edu/cds.pdf', 'data_period' => '2025-26 Common Data Set (Fall 2025 first-year students)', 'accessed_on' => '2026-10-30']);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete(['division' => 'division_1', 'test_taken' => 'sat', 'sat_score' => 1300, 'act_score' => null]))
            ->assertOk()
            ->assertSee('SAT/ACT policy for Fall 2027 applicants:')
            ->assertSee('Required.')
            ->assertSee('Your SAT score (1300) is below the middle 50% of Fall 2025 first-year students who submitted SAT scores (1405–1530).')
            ->assertSee('One in four of those students scored below this range')
            ->assertSee('Middle 50% of Fall 2025 first-year students who submitted scores: SAT 1405–1530 (78% of students submitted SAT scores).')
            ->assertSee('first-year students: 4.41')
            ->assertSee('(reported by 95% of them)')
            ->assertSee('may not compare directly with your GPA')
            ->assertSee('13.5% of all first-year applicants (Fall 2025)')
            ->assertSee("the college's 2025-26 Common Data Set (Fall 2025 first-year students)");
    }

    public function test_cards_label_federal_scorecard_data_and_its_limits(): void
    {
        $college = College::factory()->create([
            'name' => 'Fictional Federal Data College',
            'ncaa_division' => 2,
            'admissions_data_source' => College::COLLEGE_SCORECARD,
            'admissions_term' => 'Fall 2024',
            'admission_rate' => 58.2,
            'test_policy' => 'optional',
            'test_policy_term' => 'Fall 2024',
            'sat_reading_writing_25th' => 650,
            'sat_reading_writing_75th' => 730,
            'sat_math_25th' => 660,
            'sat_math_75th' => 770,
            'act_composite_25th' => 28,
            'act_composite_75th' => 33,
        ]);
        $college->sources()->create(['topic' => 'admissions', 'url' => 'https://collegescorecard.ed.gov/school/?999999', 'data_period' => 'U.S. Department of Education College Scorecard (Fall 2024 first-year students)', 'accessed_on' => '2026-10-30']);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete())
            ->assertOk()
            ->assertSee('SAT/ACT policy (federal data, Fall 2024):')
            ->assertSee('Considered if submitted, not required.')
            ->assertSee("Policies change, so check the college's admissions website.", false)
            ->assertSee('Federal College Scorecard data (Fall 2024 first-year students), middle 50%: SAT Reading and Writing 650–730 · SAT Math 660–770 · ACT 28–33.')
            ->assertSee("We don't compare your score because federal data doesn't show how many students submitted scores.")
            ->assertSee("Average high school GPA isn't included in federal data.", false)
            ->assertSee('U.S. Department of Education College Scorecard (Fall 2024 first-year students)');
    }

    public function test_colleges_beyond_the_top_five_are_expandable_valid_options(): void
    {
        $this->seed(CollegeSeeder::class);

        $response = $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_1',
            'budget' => 'unsure',
            'home_state' => 'OH',
            'priority' => 'academics',
            'test_taken' => 'sat',
            'sat_score' => 1300,
            'act_score' => null,
        ]));

        $response->assertOk()
            ->assertSee('Also fits your preferences')
            ->assertSee('They are valid options, listed here only because we show five top matches at a time.')
            ->assertSee('a score below a college', false)
            ->assertSee('is not a reason to skip it', false)
            ->assertSee('<details class="card match also">', false)
            ->assertSee('Personalized AI explanations are written for your top matches only.')
            ->assertSee('Georgetown University')
            ->assertSee('University of Maryland-College Park')
            ->assertSee('University of North Carolina at Chapel Hill')
            ->assertSee('Your SAT score (1300) is below the middle 50% of Fall 2025 first-year students who submitted SAT scores (1405–1530).');

        $this->assertSame(8, substr_count($response->getContent(), 'class="academic"'), 'All 8 Division I colleges show their academic context.');
    }

    public function test_the_also_fits_section_is_hidden_when_five_or_fewer_colleges_qualify(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->post(route('questionnaire.store'), $this->fictionalAthlete([
            'division' => 'division_1',
            'major' => 'nursing',
            'budget' => 'under_15k',
            'setting' => 'rural',
            'size' => 'small',
            'priority' => 'balance',
            'home_state' => 'OH',
        ]))
            ->assertOk()
            ->assertDontSee('id="also-fits-heading"', false)
            ->assertDontSee('<details class="card match also">', false);
    }

    public function test_submitting_answers_saves_nothing(): void
    {
        $this->seed(CollegeSeeder::class);
        $colleges = College::count();
        $sources = CollegeSource::count();

        $this->post(route('questionnaire.store'), $this->fictionalAthlete())->assertOk();

        $this->assertSame($colleges, College::count());
        $this->assertSame($sources, CollegeSource::count());
        $this->assertDatabaseCount('users', 0);
    }
}
