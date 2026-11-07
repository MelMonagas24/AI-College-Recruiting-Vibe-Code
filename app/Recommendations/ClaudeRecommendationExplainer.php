<?php

namespace App\Recommendations;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\College;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Asks Claude to explain, in plain language, why already-chosen colleges fit.
 *
 * Claude never chooses colleges. It only receives the colleges the matcher picked,
 * with their verified facts, and each explanation is checked on its own: one that
 * names another college, mentions money, cost, or budget, predicts admission, or
 * overstates an Other Option is rejected, and only that college loses its
 * explanation. Rejected colleges get one retry. Cost and affordability are never
 * sent to Claude; they appear only in the verified data on each card.
 * Only questionnaire preferences are sent: no name, email, GPA, test scores, or budget.
 */
class ClaudeRecommendationExplainer implements RecommendationExplainer
{
    public const MAX_EXPLANATION_LENGTH = 600;

    /**
     * Requests per results page: the first request, plus one retry for rejected colleges.
     */
    public const MAX_ATTEMPTS = 2;

    /**
     * The only sentence about cost Claude may write, pointing to the verified cost details on the card.
     */
    public const COST_POINTER = 'Check the cost details below.';

    private const STRONG_MATCH = 'Strong Match';

    private const OTHER_OPTION = 'Other Option to Consider';

    /**
     * Phrases that predict admission or label colleges by selectivity.
     */
    private const ADMISSION_PREDICTION_PATTERNS = [
        '/\b(admission|acceptance)\s+(chances?|odds|likelihood)\b/i',
        '/\bchances?\s+(of|for)\s+(getting\s+in|admission|acceptance|being\s+(admitted|accepted))\b/i',
        '/\bodds\b/i',
        '/\bguarantee[sd]?\b/i',
        '/\b(likely|unlikely)\s+to\s+(get\s+in|be\s+(admitted|accepted))\b/i',
        '/\b(reach|safety|target)\s+(school|college)s?\b/i',
    ];

    /**
     * Money in any form: currency symbols and words, and shorthand like "51K".
     */
    private const MONEY_PATTERN = '/[$€£]|\b(dollars?|usd|bucks)\b|\b\d+(\.\d+)?\s?k\b/i';

    /**
     * Words about cost, budget, or financial aid, which belong only in the verified cost details.
     */
    private const COST_WORDS_PATTERN = '/\b(budgets?|afford\w*|costs?|costly|pric(e|es|ed|ey|ing)|tuition|fees?|expensive|inexpensive|cheap\w*|bargain|financial(ly)?|aid|scholarships?|grants?|loans?|pay|pays|paying|paid|money|savings?)\b/i';

    /**
     * Phrases that would present a college with tradeoffs as a complete fit.
     */
    private const OVERSTATED_FIT_PATTERNS = [
        '/\b(perfect|ideal|flawless)\s+(fit|match|choice|school|college|option)\b/i',
        '/\b(strong|great|excellent|complete|total|exact)\s+(fit|match)\b/i',
        '/\b(checks|ticks)\s+(all|every)\b/i',
        '/\b(meets|fits|matches|satisfies)\s+(all|every|everything)\b/i',
        '/\beverything\s+you(\'re|\s+are)?\s+(looking|want)/i',
        '/\bno\s+(real\s+)?(tradeoffs?|downsides?|drawbacks?)\b/i',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You help a high school women's soccer player understand college recommendations in a demo app that uses fictional athlete profiles.

        The app has already chosen the colleges using its verified database. For each college provided, write one or two friendly sentences (under 60 words) explaining why it fits this athlete's stated preferences.

        Rules:
        - Use only the facts provided for that college. If a fact is listed as unknown, say it is unknown rather than guessing.
        - Name only the college you are explaining, using its name exactly as provided. Never mention or suggest any other college.
        - Never discuss money: no dollar amounts or other numbers about money, and no words like cost, price, tuition, budget, afford, aid, scholarships, or pay. The page shows verified cost details separately. If cost_is_a_tradeoff is true, end with exactly this sentence: "Check the cost details below."
        - Only use numbers that appear in the facts for that college.
        - Do not predict or promise admission, roster spots, playing time, or recruitment. A club league does not determine which NCAA division a player can compete in.
        - Never discuss admission chances or label a college a reach, match, target, or safety. Do not use the words "chances", "odds", or "guarantee". If the athlete's test score is below the middle 50%, do not discourage her: colleges admit students below that range and consider much more than test scores.
        - Each college has a category. A "Strong Match" meets the athlete's division, major, and size, and passed the budget check shown on the page. An "Other Option to Consider" has tradeoffs: name its most important non-cost tradeoff honestly, and never call it a perfect, ideal, strong, or great fit or say it meets all of her preferences. If its tradeoffs list is empty, cost is its only tradeoff: do not invent another one, and end with "Check the cost details below."
        - If a college has previous_explanation_rejected_because, a previous explanation broke a rule. Write a new one that avoids that problem.
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

        $pending = $results->matches->keyBy(fn (CollegeMatch $match): int => $match->college->id);
        $accepted = [];
        $rejections = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS && $pending->isNotEmpty(); $attempt++) {
            try {
                $response = $this->requestExplanations($this->buildRequest($answers, $pending->values(), $rejections));
            } catch (AnthropicException $e) {
                if ($attempt === 1) {
                    return $this->failure($this->serviceErrorMessage($e), $e);
                }

                Log::warning('College explanation retry unavailable: '.class_basename($e));
                break;
            }

            if ($response === null) {
                if ($attempt === 1) {
                    return ExplanationResult::failed('The AI service declined to write explanations for this request.');
                }
                break;
            }

            [$passed, $rejections] = $this->validateResponse($response, $pending->values());
            $this->logRejections($rejections, $pending, $attempt);

            $accepted += $passed;
            $pending = $pending->except(array_keys($passed));
        }

        return ExplanationResult::checked($accepted, $results->matches->count());
    }

    /**
     * The verified facts Claude may use, as JSON. Cost, budget, and financial aid are never included.
     *
     * @param  array<string, mixed>  $answers
     * @param  Collection<int, CollegeMatch>  $matches
     * @param  array<int, InvalidExplanationException>  $rejections  Why a previous explanation was rejected, keyed by college ID.
     */
    public function buildRequest(array $answers, Collection $matches, array $rejections = []): string
    {
        $preferences = [
            'ncaa_division' => config("questionnaire.divisions.{$answers['division']}"),
            'interested_in_studying' => config("questionnaire.majors.{$answers['major']}"),
            'campus_environment' => config("questionnaire.settings.{$answers['setting']}"),
            'college_size' => config("questionnaire.sizes.{$answers['size']}"),
            'matters_most' => $answers['priority'] === 'affordability'
                ? 'not shared (it is covered by the verified cost details on the page)'
                : config("questionnaire.priorities.{$answers['priority']}"),
        ];

        $colleges = $matches->map(function (CollegeMatch $match) use ($rejections): array {
            $facts = $this->collegeFacts($match);

            if (isset($rejections[$match->college->id])) {
                $facts['previous_explanation_rejected_because'] = $rejections[$match->college->id]->getMessage();
            }

            return $facts;
        })->values()->all();

        return json_encode(['athlete_preferences' => $preferences, 'colleges' => $colleges], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Check each college's explanation on its own.
     *
     * @param  Collection<int, CollegeMatch>  $matches  The colleges that were asked about.
     * @return array{0: array<int, string>, 1: array<int, InvalidExplanationException>} Accepted explanations and rejections, each keyed by college ID.
     */
    public function validateResponse(string $json, Collection $matches): array
    {
        $matchesById = $matches->keyBy(fn (CollegeMatch $match): int => $match->college->id);

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $invalid = new InvalidExplanationException('invalid_json', 'The response was not valid JSON.');

            return [[], $matchesById->map(fn (): InvalidExplanationException => $invalid)->all()];
        }

        $texts = [];
        $rejections = [];

        foreach ($data['explanations'] ?? [] as $item) {
            $id = $item['college_id'] ?? null;

            if (! is_int($id) || ! $matchesById->has($id)) {
                Log::warning('AI college explanation ignored', ['rule' => 'unrequested_college']);

                continue;
            }

            if (isset($texts[$id])) {
                $rejections[$id] = new InvalidExplanationException('duplicate', 'This college was explained more than once.');

                continue;
            }

            $texts[$id] = trim((string) ($item['why_it_fits'] ?? ''));
        }

        $accepted = [];

        foreach ($matchesById as $id => $match) {
            if (isset($rejections[$id])) {
                continue;
            }

            try {
                $this->validateExplanation($texts[$id] ?? '', $match);
                $accepted[$id] = $texts[$id];
            } catch (InvalidExplanationException $e) {
                $rejections[$id] = $e;
            }
        }

        return [$accepted, $rejections];
    }

    /**
     * Accept one explanation only if it sticks to the verified, non-cost facts sent for its college.
     *
     * @throws InvalidExplanationException
     */
    public function validateExplanation(string $text, CollegeMatch $match): void
    {
        if ($text === '') {
            throw new InvalidExplanationException('missing', 'No explanation was written for this college.');
        }

        if (mb_strlen($text) > self::MAX_EXPLANATION_LENGTH) {
            throw new InvalidExplanationException('too_long', 'The explanation was too long.');
        }

        $withoutPointer = trim(str_ireplace(self::COST_POINTER, '', $text));

        $this->assertNoOtherColleges($withoutPointer, $match->college);
        $this->assertNoMoneyOrBudgetClaims($withoutPointer);
        $this->assertOnlyKnownNumbers($withoutPointer, $match);
        $this->assertNoAdmissionPredictions($withoutPointer);
        $this->assertTradeoffsAreNotHidden($withoutPointer, $match);
    }

    /**
     * Send the request to Claude. Returns the response text, or null if Claude declined.
     *
     * @throws AnthropicException
     */
    protected function requestExplanations(string $request): ?string
    {
        $message = ($this->client ?? new Client(apiKey: $this->apiKey))->messages->create(
            maxTokens: 8000,
            messages: [['role' => 'user', 'content' => $request]],
            model: $this->model,
            outputConfig: [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
            ],
            system: self::SYSTEM_PROMPT,
            requestOptions: ['timeout' => 60.0, 'maxRetries' => 1],
        );

        if ($message->stopReason === 'refusal') {
            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return '';
    }

    /**
     * The verified facts for one college, leaving out every cost, budget, and aid detail.
     *
     * @return array<string, mixed>
     */
    private function collegeFacts(CollegeMatch $match): array
    {
        $college = $match->college;
        $withoutCost = fn (array $items): array => array_values(array_diff_key($items, array_flip(CollegeMatch::COST_TOPICS)));

        return [
            'college_id' => $college->id,
            'name' => $college->name,
            'location' => "{$college->city}, {$college->state}",
            'ncaa_division' => $college->ncaa_division,
            'conference' => $college->conference ?? 'unknown',
            'campus_environment' => $college->campus_environment?->label() ?? 'unknown',
            'undergraduates' => $college->undergraduate_enrollment
                ? number_format($college->undergraduate_enrollment)." ({$college->enrollment_term})"
                : 'unknown',
            'sat_act_policy' => $college->test_policy?->federalLabel() ?? 'unknown',
            'athlete_test_score_compared_with_first_year_students' => $this->academicFitFact($match->academicFit),
            'matched_preferences' => $withoutCost($match->matchedOn),
            'unknown_facts' => $withoutCost($match->unknowns),
            'category' => $match->isStrongMatch() ? self::STRONG_MATCH : self::OTHER_OPTION,
            'tradeoffs' => $withoutCost($match->tradeoffs),
            'cost_is_a_tradeoff' => isset($match->tradeoffs['budget']),
            'other_things_to_consider' => $withoutCost($match->notes),
        ];
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
     * Reject text that names any college in our database other than the one explained.
     *
     * Full names and unambiguous short names count. A short name that is also a
     * state or the college's own city (such as "Maryland" or "Tampa") only counts
     * as "University of ...", so places can be mentioned.
     */
    private function assertNoOtherColleges(string $text, College $explained): void
    {
        $explainedState = config("questionnaire.states.{$explained->state}", $explained->state);
        $ownWords = strtolower("{$explained->name} {$explained->short_name} {$explained->city} {$explainedState}");

        foreach (College::where('id', '!=', $explained->id)->get(['name', 'short_name', 'city']) as $other) {
            foreach ($this->namesFor($other) as $candidate) {
                if (str_contains($ownWords, strtolower($candidate))) {
                    continue;
                }

                if (preg_match('/\b'.preg_quote($candidate, '/').'\b/i', $text) === 1) {
                    throw new InvalidExplanationException('other_college', "It mentioned another college ({$candidate}).");
                }
            }
        }
    }

    /**
     * The names a college can be recognized by in text.
     *
     * @return list<string>
     */
    private function namesFor(College $college): array
    {
        $names = [$college->name, preg_replace('/^The\s+/i', '', $college->name)];

        $hyphen = strrpos($college->name, '-');
        if ($hyphen !== false && preg_match('/\b(University|College)\b/', substr($college->name, 0, $hyphen)) === 1) {
            $names[] = preg_replace('/^The\s+/i', '', substr($college->name, 0, $hyphen));
        }

        $isPlaceName = in_array(strtolower($college->short_name), array_map('strtolower', [...array_values(config('questionnaire.states')), $college->city]), true);
        $names[] = $isPlaceName ? "University of {$college->short_name}" : $college->short_name;

        return array_values(array_unique($names));
    }

    /**
     * Reject any mention of money, cost, budget, or financial aid. Those facts are shown only in the verified cost details.
     */
    private function assertNoMoneyOrBudgetClaims(string $text): void
    {
        if (preg_match(self::MONEY_PATTERN, $text, $found) === 1) {
            throw new InvalidExplanationException('money', "It mentioned money (\"{$found[0]}\"). Never mention money.");
        }

        if (preg_match(self::COST_WORDS_PATTERN, $text, $found) === 1) {
            throw new InvalidExplanationException('cost_or_budget', "It discussed cost, budget, or aid (\"{$found[0]}\"). Leave that to the verified cost details.");
        }
    }

    /**
     * Reject numbers of 100 or more that do not appear in the facts sent for this college, such as an invented price.
     */
    private function assertOnlyKnownNumbers(string $text, CollegeMatch $match): void
    {
        $numbers = fn (string $source): array => array_map(
            fn (string $number): string => str_replace(',', '', $number),
            preg_match_all('/\d[\d,]*/', $source, $found) ? $found[0] : [],
        );

        $allowed = $numbers(json_encode($this->collegeFacts($match)));

        foreach ($numbers($text) as $number) {
            if ((int) $number >= 100 && ! in_array($number, $allowed, true)) {
                throw new InvalidExplanationException('unverified_number', "It used a number that is not in the facts ({$number}).");
            }
        }
    }

    /**
     * Reject text that predicts admission or sorts colleges by how hard they are to get into.
     */
    private function assertNoAdmissionPredictions(string $text): void
    {
        foreach (self::ADMISSION_PREDICTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $found) === 1) {
                throw new InvalidExplanationException('admission_prediction', "It predicted admission (\"{$found[0]}\").");
            }
        }
    }

    /**
     * Reject text that calls an Other Option a complete fit. Strong Matches may be described that way.
     */
    private function assertTradeoffsAreNotHidden(string $text, CollegeMatch $match): void
    {
        if ($match->isStrongMatch()) {
            return;
        }

        foreach (self::OVERSTATED_FIT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $found) === 1) {
                throw new InvalidExplanationException('overstated_fit', "It called an Other Option a full fit (\"{$found[0]}\").");
            }
        }
    }

    /**
     * Only the comparison result is sent, never the athlete's score or GPA.
     */
    private function academicFitFact(AcademicFit $fit): string
    {
        return match ($fit->status) {
            AcademicFitStatus::WithinRange => "{$fit->test} score is within the middle 50% of first-year students who submitted {$fit->test} scores",
            AcademicFitStatus::AboveRange => "{$fit->test} score is above the middle 50% of first-year students who submitted {$fit->test} scores",
            AcademicFitStatus::BelowRange => "{$fit->test} score is below the middle 50% of first-year students who submitted {$fit->test} scores; one in four of those students scored below this range",
            AcademicFitStatus::NotCompared => 'not compared (no score entered or no reliable data); do not mention test scores',
        };
    }

    /**
     * Log which rule each rejected explanation broke. Athlete answers are never logged.
     *
     * @param  array<int, InvalidExplanationException>  $rejections
     * @param  Collection<int, CollegeMatch>  $matchesById
     */
    private function logRejections(array $rejections, Collection $matchesById, int $attempt): void
    {
        foreach ($rejections as $id => $rejection) {
            Log::warning('AI college explanation rejected', [
                'attempt' => $attempt,
                'college_id' => $id,
                'college' => $matchesById->get($id)?->college->short_name,
                'rule' => $rejection->rule,
                'reason' => $rejection->getMessage(),
            ]);
        }
    }

    private function serviceErrorMessage(AnthropicException $e): string
    {
        return match (true) {
            $e instanceof AuthenticationException => 'The AI service rejected the API key. Check ANTHROPIC_API_KEY in your .env file.',
            $e instanceof RateLimitException => 'The AI service is busy right now. Please try again in a minute.',
            $e instanceof APIConnectionException => 'The AI service could not be reached. Check your internet connection and try again.',
            default => 'The AI service returned an error, so explanations are unavailable.',
        };
    }

    private function failure(string $message, \Throwable $e): ExplanationResult
    {
        Log::warning('College explanations unavailable: '.class_basename($e).': '.$e->getMessage());

        return ExplanationResult::failed($message);
    }
}
