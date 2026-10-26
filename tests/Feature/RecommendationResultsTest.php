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
