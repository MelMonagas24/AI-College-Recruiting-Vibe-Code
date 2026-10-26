<?php

namespace Tests\Feature;

use App\Recommendations\ClaudeRecommendationExplainer;
use App\Recommendations\CollegeMatcher;
use App\Recommendations\ExplanationStatus;
use App\Recommendations\InvalidExplanationException;
use App\Recommendations\MatchResults;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaudeRecommendationExplainerTest extends TestCase
{
    use RefreshDatabase;

    /**
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
        'setting' => 'no_preference',
        'size' => 'no_preference',
        'priority' => 'academics',
    ];

    private function results(): MatchResults
    {
        $this->seed(CollegeSeeder::class);

        return app(CollegeMatcher::class)->match($this->answers);
    }

    private function explainer(): ClaudeRecommendationExplainer
    {
        return new ClaudeRecommendationExplainer(apiKey: null, model: 'claude-opus-5-5');
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
            ->mapWithKeys(fn ($match): array => [$match->college->id => "{$match->college->short_name} offers Nursing and fits what you're looking for."])
            ->all();
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

        $explanations = $this->explainer()->validateResponse($this->response($this->validTexts($results)), $results);

        $this->assertSame($results->matches->pluck('college.id')->sort()->values()->all(), collect($explanations)->keys()->sort()->values()->all());
    }

    public function test_it_rejects_a_college_that_was_not_recommended(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $texts[999999] = 'Fictional Invented College would be perfect for you.';

        $this->expectException(InvalidExplanationException::class);
        $this->expectExceptionMessage('included a college that was not recommended');

        $this->explainer()->validateResponse($this->response($texts), $results);
    }

    public function test_it_rejects_an_explanation_that_mentions_another_college(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $texts[array_key_first($texts)] = 'This is a great fit, and you should also consider Georgetown.';

        $this->expectException(InvalidExplanationException::class);
        $this->expectExceptionMessage('mentioned another college (Georgetown)');

        $this->explainer()->validateResponse($this->response($texts), $results);
    }

    public function test_it_rejects_dollar_amounts_that_are_not_verified_facts(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        $texts[array_key_first($texts)] = 'You could receive a $20,000 scholarship here.';

        $this->expectException(InvalidExplanationException::class);
        $this->expectExceptionMessage('unverified dollar amount');

        $this->explainer()->validateResponse($this->response($texts), $results);
    }

    public function test_it_allows_verified_dollar_amounts(): void
    {
        $results = $this->results();
        $match = $results->matches->first();
        $texts = $this->validTexts($results);
        $texts[$match->college->id] = 'Its published cost is $'.number_format($match->college->cost_of_attendance_out_of_state).' before aid.';

        $explanations = $this->explainer()->validateResponse($this->response($texts), $results);

        $this->assertStringContainsString('before aid', $explanations[$match->college->id]);
    }

    public function test_it_rejects_a_response_that_skips_a_recommended_college(): void
    {
        $results = $this->results();
        $texts = $this->validTexts($results);
        array_pop($texts);

        $this->expectException(InvalidExplanationException::class);
        $this->expectExceptionMessage('did not explain every recommended college');

        $this->explainer()->validateResponse($this->response($texts), $results);
    }

    public function test_it_rejects_text_that_is_not_json(): void
    {
        $this->expectException(InvalidExplanationException::class);

        $this->explainer()->validateResponse('Here are some colleges you might like!', $this->results());
    }

    public function test_the_request_contains_only_preferences_and_verified_facts(): void
    {
        $results = $this->results();

        $request = $this->explainer()->buildRequest($this->answers, $results);
        $decoded = json_decode($request, true);

        $this->assertSame(
            ['ncaa_division', 'interested_in_studying', 'family_budget_per_year_after_aid', 'campus_environment', 'college_size', 'matters_most'],
            array_keys($decoded['athlete_preferences']),
        );
        $this->assertStringNotContainsString('3.90', $request);
        $this->assertStringNotContainsString('1480', $request);
        $this->assertStringNotContainsString('2028', $request);
        $this->assertCount($results->matches->count(), $decoded['colleges']);
        $this->assertSame($results->matches->pluck('college.name')->all(), array_column($decoded['colleges'], 'name'));
    }
}
