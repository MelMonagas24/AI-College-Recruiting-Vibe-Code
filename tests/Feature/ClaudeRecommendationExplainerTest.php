<?php

namespace Tests\Feature;

use App\Recommendations\ClaudeRecommendationExplainer;
use App\Recommendations\CollegeMatch;
use App\Recommendations\CollegeMatcher;
use App\Recommendations\ExplanationStatus;
use App\Recommendations\MatchResults;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClaudeRecommendationExplainerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Answers from a fictional athlete.
     *
     * @var array<string, mixed>
     */
    private array $answers = [
        'sport' => 'womens_soccer',
        'graduation_year' => 2028,
        'division' => 'division_3',
        'position' => 'forward',
        'league' => 'ecnl',
        'gpa' => '3.90',
        'test_taken' => 'sat',
        'sat_score' => 1480,
        'major' => 'nursing',
        'budget' => '30k_45k',
        'home_state' => 'GA',
        'setting' => 'no_preference',
        'size' => 'no_preference',
        'priority' => 'academics',
    ];

    /**
     * The New Jersey athlete: TCNJ is a Strong Match, and Montclair State and Scranton are Other Options.
     *
     * @var array<string, mixed>
     */
    private array $newJerseyAnswers = [
        'sport' => 'womens_soccer',
        'graduation_year' => 2028,
        'division' => 'division_3',
        'position' => 'defender',
        'league' => 'ecnl_rl',
        'gpa' => '3.70',
        'test_taken' => 'none',
        'major' => 'education',
        'budget' => '30k_45k',
        'home_state' => 'NJ',
        'setting' => 'no_preference',
        'size' => 'medium',
        'priority' => 'affordability',
    ];

    private function results(?array $answers = null): MatchResults
    {
        $this->seed(CollegeSeeder::class);

        return app(CollegeMatcher::class)->match($answers ?? $this->answers);
    }

    private function explainer(): ClaudeRecommendationExplainer
    {
        return new ClaudeRecommendationExplainer(apiKey: null, model: 'claude-opus-5-5');
    }

    /**
     * An explainer that returns prepared responses instead of calling the AI service.
     *
     * @param  list<string|null>  $responses  One per request; null means the AI declined.
     */
    private function fakeExplainer(array $responses): ClaudeRecommendationExplainer
    {
        return new class($responses) extends ClaudeRecommendationExplainer
        {
            /** @var list<string> */
            public array $requests = [];

            public function __construct(private array $responses)
            {
                parent::__construct(apiKey: 'not-a-real-key', model: 'test-model');
            }

            protected function requestExplanations(string $request): ?string
            {
                $this->requests[] = $request;

                return array_shift($this->responses);
            }
        };
    }

    /**
     * @param  array<int, string>  $textById
     */
    private function response(array $textById): string
    {
        return json_encode(['explanations' => collect($textById)
            ->map(fn (string $text, int $id): array => ['college_id' => $id, 'why_it_fits' => $text])
            ->values()
            ->all()]);
    }

    /**
     * @return array<int, string>
     */
    private function validTexts(MatchResults $results): array
    {
        return $results->matches
            ->mapWithKeys(fn (CollegeMatch $match): array => [$match->college->id => "{$match->college->short_name} offers what you're looking for in a college soccer program."])
            ->all();
    }

    private function idOf(MatchResults $results, string $shortName): int
    {
        return $results->matches->first(fn (CollegeMatch $match): bool => $match->college->short_name === $shortName)->college->id;
    }

    /**
     * @return array<int, string> Rejection rules keyed by college ID.
     */
    private function rejectedRules(MatchResults $results, array $texts): array
    {
        [, $rejections] = $this->explainer()->validateResponse($this->response($texts), $results->matches);

        return array_map(fn ($rejection): string => $rejection->rule, $rejections);
    }

    public function test_without_an_api_key_it_reports_not_configured_and_makes_no_request(): void
    {
        $result = $this->explainer()->explain($this->answers, $this->results());

        $this->assertFalse($this->explainer()->isConfigured());
        $this->assertSame(ExplanationStatus::NotConfigured, $result->status);
        $this->assertSame([], $result->explanations);
    }

    public function test_it_accepts_explanations_that_stick_to_the_recommended_colleges(): void
    {
        $results = $this->results();

        [$accepted, $rejections] = $this->explainer()->validateResponse($this->response($this->validTexts($results)), $results->matches);

        $this->assertSame([], $rejections);
        $this->assertEqualsCanonicalizing($results->matches->pluck('college.id')->all(), array_keys($accepted));
    }

    public function test_each_college_is_checked_separately(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $bad = array_key_first($texts);
        $texts[$bad] = 'You should also consider Georgetown.';

        [$accepted, $rejections] = $this->explainer()->validateResponse($this->response($texts), $results->matches);

        $this->assertSame([$bad], array_keys($rejections));
        $this->assertSame('other_college', $rejections[$bad]->rule);
        $this->assertCount($results->matches->count() - 1, $accepted, 'The other explanations are kept.');
    }

    public function test_a_college_that_was_not_recommended_is_ignored(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $texts[999999] = 'Fictional Invented College would be perfect for you.';

        [$accepted, $rejections] = $this->explainer()->validateResponse($this->response($texts), $results->matches);

        $this->assertArrayNotHasKey(999999, $accepted);
        $this->assertSame([], $rejections);
        $this->assertCount($results->matches->count(), $accepted);
    }

    public function test_it_rejects_other_colleges_but_allows_place_names(): void
    {
        $results = $this->results($this->newJerseyAnswers);
        $tcnj = $this->idOf($results, 'TCNJ');

        foreach ([
            'TCNJ is near Georgetown University.' => 'other_college',
            'Like the University of Maryland, TCNJ offers Education.' => 'other_college',
            'Unlike Rutgers, TCNJ is a medium-size college.' => 'other_college',
            'TCNJ is closer to you than The University of Tampa.' => 'other_college',
        ] as $text => $rule) {
            $this->assertSame([$tcnj => $rule], $this->rejectedRules($results, array_replace($this->validTexts($results), [$tcnj => $text])), $text);
        }

        foreach ([
            'TCNJ is a short drive from Maryland and Pennsylvania.',
            'TCNJ is in New Jersey, not Colorado or Florida.',
            'You can visit Tampa or Scranton on a road trip, but TCNJ is close to home.',
        ] as $text) {
            $this->assertSame([], $this->rejectedRules($results, array_replace($this->validTexts($results), [$tcnj => $text])), $text);
        }
    }

    public function test_it_rejects_money_in_any_form(): void
    {
        $results = $this->results($this->newJerseyAnswers);
        $tcnj = $this->idOf($results, 'TCNJ');

        foreach ([
            'You could receive a $20,000 award here.' => 'money',
            'TCNJ is about $44,640 per year.' => 'money',
            'TCNJ comes to about 45K a year.' => 'money',
            'TCNJ is 44,640 dollars a year.' => 'money',
            'TCNJ comes to 44,640 a year.' => 'unverified_number',
        ] as $text => $rule) {
            $this->assertSame([$tcnj => $rule], $this->rejectedRules($results, array_replace($this->validTexts($results), [$tcnj => $text])), $text);
        }
    }

    public function test_it_rejects_cost_and_budget_claims_even_when_they_are_negated(): void
    {
        $results = $this->results($this->newJerseyAnswers);
        $scranton = $this->idOf($results, 'Scranton');

        foreach ([
            'Scranton is within your budget.',
            'Scranton is not within your budget.',
            "Scranton doesn't fit your family's budget.",
            'Scranton is affordable.',
            'Financial aid could help at Scranton.',
            'Scranton has merit scholarships.',
            'Scranton tuition is high.',
            'Scranton is more expensive than you hoped.',
        ] as $text) {
            $this->assertSame([$scranton => 'cost_or_budget'], $this->rejectedRules($results, array_replace($this->validTexts($results), [$scranton => $text])), $text);
        }
    }

    public function test_it_allows_the_cost_pointer_and_verified_enrollment(): void
    {
        $results = $this->results($this->newJerseyAnswers);
        $texts = $this->validTexts($results);
        $texts[$this->idOf($results, 'Scranton')] = 'Scranton offers Education, but it is a small college rather than a medium one. Check the cost details below.';
        $texts[$this->idOf($results, 'TCNJ')] = 'TCNJ offers Education and has 7,435 undergraduates, so it is the medium size you wanted.';

        $this->assertSame([], $this->rejectedRules($results, $texts));
    }

    public function test_a_missing_explanation_only_affects_that_college(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $skipped = array_key_last($texts);
        unset($texts[$skipped]);

        $this->assertSame([$skipped => 'missing'], $this->rejectedRules($results, $texts));
    }

    public function test_text_that_is_not_json_rejects_every_college(): void
    {
        $results = $this->results();

        [$accepted, $rejections] = $this->explainer()->validateResponse('Here are some colleges you might like!', $results->matches);

        $this->assertSame([], $accepted);
        $this->assertCount($results->matches->count(), $rejections);
        $this->assertSame('invalid_json', reset($rejections)->rule);
    }

    public function test_it_rejects_explanations_that_predict_admission(): void
    {
        $results = $this->results();
        $first = $results->matches->first()->college->id;

        foreach ([
            'Your chances of getting in are strong here.',
            'This is a safety school for you.',
            'You are likely to be admitted.',
            'The odds are in your favor.',
            'Your admission chances look good.',
            'Nothing here can guarantee a roster spot.',
        ] as $prediction) {
            $this->assertSame([$first => 'admission_prediction'], $this->rejectedRules($results, array_replace($this->validTexts($results), [$first => $prediction])), $prediction);
        }
    }

    public function test_it_rejects_an_other_option_described_as_a_full_fit(): void
    {
        $results = $this->results($this->newJerseyAnswers);
        $montclair = $this->idOf($results, 'Montclair State');
        $tcnj = $this->idOf($results, 'TCNJ');

        foreach ([
            'Montclair State is a perfect fit for you.',
            'Montclair State is an ideal match.',
            'Montclair State is a great fit.',
            'Montclair State meets all of your preferences.',
            'Montclair State checks every box.',
            "Montclair State has everything you're looking for.",
            'Montclair State has no real tradeoffs.',
        ] as $overstated) {
            $this->assertSame([$montclair => 'overstated_fit'], $this->rejectedRules($results, array_replace($this->validTexts($results), [$montclair => $overstated])), $overstated);
        }

        $honest = array_replace($this->validTexts($results), [
            $montclair => 'If Education is your top choice, Montclair State offers it, though it is a large college rather than a medium one.',
            $tcnj => 'TCNJ is a strong fit: it offers Education and is the medium size you wanted.',
        ]);

        $this->assertSame([], $this->rejectedRules($results, $honest));
    }

    public function test_the_request_never_includes_cost_budget_or_aid(): void
    {
        $results = $this->results($this->newJerseyAnswers);

        $request = $this->explainer()->buildRequest($this->newJerseyAnswers, $results->matches);
        $colleges = collect(json_decode($request, true)['colleges'])->keyBy('name');

        $this->assertStringNotContainsString('$', $request);
        $this->assertDoesNotMatchRegularExpression('/budget|scholarship|tuition|afford|before aid/i', str_replace('cost_is_a_tradeoff', '', $request));
        $this->assertStringStartsWith('not shared', json_decode($request, true)['athlete_preferences']['matters_most'], 'An "Affordability" priority is not sent, so the AI is not invited to discuss cost.');
        $this->assertSame('Strong Match', $colleges['The College of New Jersey']['category']);
        $this->assertFalse($colleges['The College of New Jersey']['cost_is_a_tradeoff']);
        $this->assertSame('Other Option to Consider', $colleges['University of Scranton']['category']);
        $this->assertTrue($colleges['University of Scranton']['cost_is_a_tradeoff']);
        $this->assertSame(['It\'s a Small college (3,613 undergraduates, Fall 2024), not medium.'], $colleges['University of Scranton']['tradeoffs']);
    }

    public function test_the_request_shares_only_the_test_score_comparison_never_the_score(): void
    {
        $results = $this->results();

        $colleges = json_decode($this->explainer()->buildRequest($this->answers, $results->matches), true)['colleges'];

        foreach ($colleges as $college) {
            $this->assertMatchesRegularExpression('/^(SAT score is (within|above|below) the middle 50%|not compared)/', $college['athlete_test_score_compared_with_first_year_students']);
            $this->assertArrayHasKey('sat_act_policy', $college);
        }
    }

    public function test_the_request_contains_only_preferences_and_verified_facts(): void
    {
        $results = $this->results();

        $request = $this->explainer()->buildRequest($this->answers, $results->matches);
        $decoded = json_decode($request, true);

        $this->assertSame(
            ['ncaa_division', 'interested_in_studying', 'campus_environment', 'college_size', 'matters_most'],
            array_keys($decoded['athlete_preferences']),
        );
        $this->assertStringNotContainsString('3.90', $request);
        $this->assertStringNotContainsString('1480', $request);
        $this->assertStringNotContainsString('2028', $request);
        $this->assertSame($results->matches->pluck('college.name')->all(), array_column($decoded['colleges'], 'name'));
    }

    public function test_good_explanations_need_only_one_request(): void
    {
        $results = $this->results();
        $explainer = $this->fakeExplainer([$this->response($this->validTexts($results))]);

        $result = $explainer->explain($this->answers, $results);

        $this->assertSame(ExplanationStatus::Generated, $result->status);
        $this->assertCount(1, $explainer->requests);
    }

    public function test_a_rejected_explanation_is_retried_once_for_that_college_only(): void
    {
        Log::spy();
        $results = $this->results();
        $texts = $this->validTexts($results);
        $bad = array_key_first($texts);
        $first = array_replace($texts, [$bad => 'It is only $20,000 a year.']);
        $retry = [$bad => $texts[$bad]];
        $explainer = $this->fakeExplainer([$this->response($first), $this->response($retry)]);

        $result = $explainer->explain($this->answers, $results);

        $this->assertSame(ExplanationStatus::Generated, $result->status);
        $this->assertCount($results->matches->count(), $result->explanations);
        $this->assertCount(2, $explainer->requests);

        $retried = json_decode($explainer->requests[1], true)['colleges'];
        $this->assertSame([$bad], array_column($retried, 'college_id'), 'Only the rejected college is sent again.');
        $this->assertStringContainsString('It mentioned money', $retried[0]['previous_explanation_rejected_because']);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($bad): bool {
            $logged = json_encode($context);

            return $message === 'AI college explanation rejected'
                && $context['college_id'] === $bad
                && $context['rule'] === 'money'
                && $context['attempt'] === 1
                && ! str_contains($logged, '3.90')
                && ! str_contains($logged, '1480')
                && ! str_contains($logged, 'Nursing');
        });
    }

    public function test_after_one_failed_retry_the_other_explanations_are_still_shown(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $bad = array_key_first($texts);
        $explainer = $this->fakeExplainer([
            $this->response(array_replace($texts, [$bad => 'This is a safety school.'])),
            $this->response([$bad => 'Still a safety school.']),
            $this->response($texts),
        ]);

        $result = $explainer->explain($this->answers, $results);

        $this->assertSame(ExplanationStatus::Partial, $result->status);
        $this->assertCount(2, $explainer->requests, 'There is only one retry.');
        $this->assertNull($result->for($bad));
        $this->assertCount($results->matches->count() - 1, $result->explanations);
        $this->assertStringContainsString('Some AI explanations did not pass our fact check', $result->message);
    }

    public function test_if_the_retry_is_declined_the_accepted_explanations_are_kept(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $bad = array_key_first($texts);
        $explainer = $this->fakeExplainer([$this->response(array_replace($texts, [$bad => 'This is a safety school.'])), null]);

        $result = $explainer->explain($this->answers, $results);

        $this->assertSame(ExplanationStatus::Partial, $result->status);
        $this->assertCount($results->matches->count() - 1, $result->explanations);
    }

    public function test_when_every_explanation_fails_twice_the_page_explains_why(): void
    {
        $results = $this->results();
        $explainer = $this->fakeExplainer(['not json', 'still not json']);

        $result = $explainer->explain($this->answers, $results);

        $this->assertSame(ExplanationStatus::Failed, $result->status);
        $this->assertCount(2, $explainer->requests);
        $this->assertStringContainsString('did not pass our fact check', $result->message);
    }
}
