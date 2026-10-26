<?php

namespace App\Recommendations;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\College;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Asks Claude to explain, in plain language, why already-chosen colleges fit.
 *
 * Claude never chooses colleges. It only receives the colleges the matcher picked,
 * with their verified facts, and its answer is rejected unless it explains exactly
 * those colleges without naming others or quoting dollar amounts we did not send.
 * Only questionnaire preferences are sent: no name, email, GPA, or test scores.
 */
class ClaudeRecommendationExplainer implements RecommendationExplainer
{
    public const MAX_EXPLANATION_LENGTH = 600;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You help a high school women's soccer player understand college recommendations in a demo app that uses fictional athlete profiles.

        The app has already chosen the colleges using its verified database. For each college provided, write one or two friendly sentences (under 60 words) explaining why it fits this athlete's stated preferences.

        Rules:
        - Use only the facts provided for that college. If a fact is listed as unknown, say it is unknown rather than guessing.
        - Name only the college you are explaining. Never mention or suggest any other college.
        - Published cost is the price before financial aid. Never say or imply it is what the family will pay.
        - Never call a college affordable unless its cost is "within budget". If it is above budget or unknown, say so plainly.
        - Do not mention any dollar amount that is not in the provided facts.
        - Do not predict or promise admission, scholarships, roster spots, playing time, or recruitment. A club league does not determine which NCAA division a player can compete in.
        - Write directly to the athlete ("you"), in plain language a high school student understands.
        PROMPT;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly ?Client $client = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey) || $this->client !== null;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function explain(array $answers, MatchResults $results): ExplanationResult
    {
        if (! $this->isConfigured()) {
            return ExplanationResult::notConfigured();
        }

        if ($results->matches->isEmpty()) {
            return ExplanationResult::generated([]);
        }

        try {
            $message = ($this->client ?? new Client(apiKey: $this->apiKey))->messages->create(
                maxTokens: 8000,
                messages: [['role' => 'user', 'content' => $this->buildRequest($answers, $results)]],
                model: $this->model,
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
                ],
                system: self::SYSTEM_PROMPT,
                requestOptions: ['timeout' => 60.0, 'maxRetries' => 1],
            );
        } catch (AuthenticationException $e) {
            return $this->failure('The AI service rejected the API key. Check ANTHROPIC_API_KEY in your .env file.', $e);
        } catch (RateLimitException $e) {
            return $this->failure('The AI service is busy right now. Please try again in a minute.', $e);
        } catch (APIConnectionException $e) {
            return $this->failure('The AI service could not be reached. Check your internet connection and try again.', $e);
        } catch (AnthropicException $e) {
            return $this->failure('The AI service returned an error, so explanations are unavailable.', $e);
        }

        if ($message->stopReason === 'refusal') {
            return ExplanationResult::failed('The AI service declined to write explanations for this request.');
        }

        $text = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        try {
            return ExplanationResult::generated($this->validateResponse($text ?? '', $results));
        } catch (InvalidExplanationException $e) {
            return $this->failure('The AI response did not pass our fact check, so it is not shown.', $e);
        }
    }

    /**
     * The verified facts Claude may use, as JSON. Only matching preferences are sent.
     *
     * @param  array<string, mixed>  $answers
     */
    public function buildRequest(array $answers, MatchResults $results): string
    {
        $preferences = [
            'ncaa_division' => config("questionnaire.divisions.{$answers['division']}"),
            'interested_in_studying' => config("questionnaire.majors.{$answers['major']}"),
            'family_budget_per_year_after_aid' => config("questionnaire.budgets.{$answers['budget']}"),
            'campus_environment' => config("questionnaire.settings.{$answers['setting']}"),
            'college_size' => config("questionnaire.sizes.{$answers['size']}"),
            'matters_most' => config("questionnaire.priorities.{$answers['priority']}"),
        ];

        $colleges = $results->matches->map(fn (CollegeMatch $match): array => [
            'college_id' => $match->college->id,
            'name' => $match->college->name,
            'location' => "{$match->college->city}, {$match->college->state}",
            'ncaa_division' => $match->college->ncaa_division,
            'conference' => $match->college->conference ?? 'unknown',
            'campus_environment' => $match->college->campus_environment?->label() ?? 'unknown',
            'undergraduates' => $match->college->undergraduate_enrollment
                ? number_format($match->college->undergraduate_enrollment)." ({$match->college->enrollment_term})"
                : 'unknown',
            'published_cost_of_attendance_before_aid' => $this->costFacts($match),
            'merit_scholarships' => $match->college->merit_aid->label(),
            'matched_preferences' => $match->matchedOn,
            'unknown_facts' => $match->unknowns,
            'tradeoff' => $match->tradeoff,
        ])->values()->all();

        return json_encode(['athlete_preferences' => $preferences, 'colleges' => $colleges], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Accept the AI answer only if it explains exactly the given colleges and
     * introduces no other colleges or dollar amounts.
     *
     * @return array<int, string>
     *
     * @throws InvalidExplanationException
     */
    public function validateResponse(string $json, MatchResults $results): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidExplanationException('The AI response was not valid JSON.');
        }

        $matchesById = $results->matches->keyBy(fn (CollegeMatch $match): int => $match->college->id);
        $explanations = [];

        foreach ($data['explanations'] ?? [] as $item) {
            $id = $item['college_id'] ?? null;
            $text = trim((string) ($item['why_it_fits'] ?? ''));

            if (! is_int($id) || ! $matchesById->has($id)) {
                throw new InvalidExplanationException('The AI response included a college that was not recommended.');
            }

            if (isset($explanations[$id]) || $text === '' || mb_strlen($text) > self::MAX_EXPLANATION_LENGTH) {
                throw new InvalidExplanationException('The AI response had a missing, repeated, or overly long explanation.');
            }

            $this->assertNoOtherColleges($text, $matchesById->get($id)->college);
            $this->assertOnlyKnownAmounts($text, $matchesById->get($id));

            $explanations[$id] = $text;
        }

        if (count($explanations) !== $matchesById->count()) {
            throw new InvalidExplanationException('The AI response did not explain every recommended college.');
        }

        return $explanations;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'explanations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'college_id' => ['type' => 'integer'],
                            'why_it_fits' => ['type' => 'string'],
                        ],
                        'required' => ['college_id', 'why_it_fits'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['explanations'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function costFacts(CollegeMatch $match): array
    {
        $college = $match->college;
        $affordability = $match->affordability;
        $format = fn (?int $amount): string => $amount === null ? 'unknown' : '$'.number_format($amount);

        return [
            'academic_year' => $college->cost_academic_year ?? 'unknown',
            'applies_to_this_athlete' => $format($affordability->cost),
            'rate_used' => $affordability->rate ?? 'same price for all students',
            'compared_with_family_budget' => str_replace('_', ' ', $affordability->status->value),
            'amount_above_budget' => $format($affordability->gap),
            'includes_books_travel_personal' => match ($college->cost_of_attendance_is_complete) {
                true => 'yes',
                false => 'no',
                null => 'unknown',
            },
        ];
    }

    /**
     * Reject text that names any college in our database other than the one explained.
     */
    private function assertNoOtherColleges(string $text, College $explained): void
    {
        $ownWords = strtolower("{$explained->name} {$explained->short_name} {$explained->city}");

        foreach (College::where('id', '!=', $explained->id)->get(['name', 'short_name']) as $other) {
            foreach ([$other->name, $other->short_name] as $candidate) {
                if (str_contains($ownWords, strtolower($candidate))) {
                    continue;
                }

                if (preg_match('/\b'.preg_quote($candidate, '/').'\b/i', $text) === 1) {
                    throw new InvalidExplanationException("The AI response mentioned another college ({$candidate}).");
                }
            }
        }
    }

    /**
     * Reject dollar amounts that are not among the verified facts sent for this college.
     */
    private function assertOnlyKnownAmounts(string $text, CollegeMatch $match): void
    {
        $allowed = array_filter([
            $match->college->cost_of_attendance_in_state,
            $match->college->cost_of_attendance_out_of_state,
            $match->college->tuition_fees_in_state,
            $match->college->tuition_fees_out_of_state,
            $match->affordability->gap,
            ...array_filter(array_merge(...array_values(CollegeMatcher::BUDGET_RANGES))),
        ]);

        preg_match_all('/\$\s?([\d,]+)/', $text, $found);

        foreach ($found[1] as $amount) {
            if (! in_array((int) str_replace(',', '', $amount), $allowed, true)) {
                throw new InvalidExplanationException("The AI response mentioned an unverified dollar amount (\${$amount}).");
            }
        }
    }

    private function failure(string $message, \Throwable $e): ExplanationResult
    {
        Log::warning('College explanations unavailable: '.$e->getMessage());

        return ExplanationResult::failed($message);
    }
}
