<?php

namespace App\Recommendations;

use App\Enums\MeritAidAvailability;
use App\Models\College;
use Illuminate\Support\Collection;

/**
 * Filters and ranks verified colleges against questionnaire answers.
 *
 * Only facts stored in the database are used. Published costs are prices before
 * financial aid, so they are compared to the family's after-aid budget only to show
 * how much grants or scholarships would need to cover, never as the family's cost.
 * GPA, test scores, position and club league are not used: the database has no
 * admissions or roster data, and a club league cannot decide a player's division.
 */
class CollegeMatcher
{
    public const MINIMUM_RESULTS = 3;

    public const MAXIMUM_RESULTS = 5;

    /**
     * The lowest share of weighted preferences a college must meet to be shown.
     */
    public const MINIMUM_SCORE = 0.5;

    /**
     * The bottom and top of each budget range, in dollars per year. A null top means no upper limit.
     *
     * @var array<string, array{0: int, 1: int|null}>
     */
    public const BUDGET_RANGES = [
        'under_15k' => [0, 15000],
        '15k_30k' => [15000, 30000],
        '30k_45k' => [30000, 45000],
        '45k_plus' => [45000, null],
    ];

    /**
     * @var array<string, int>
     */
    private const DIVISIONS = [
        'division_1' => 1,
        'division_2' => 2,
        'division_3' => 3,
    ];

    /**
     * @var array<string, string>
     */
    private const MAJOR_COLUMNS = [
        'business' => 'offers_business',
        'nursing' => 'offers_nursing',
        'psychology' => 'offers_psychology',
        'biology' => 'offers_biology',
        'education' => 'offers_education',
    ];

    /**
     * @param  array<string, mixed>  $answers  Validated questionnaire answers.
     */
    public function match(array $answers): MatchResults
    {
        $eligible = College::eligibleForRecommendations()->with('sources')->get();

        $inDivision = $this->filterByDivision($eligible, $answers['division']);
        $withMajor = $this->filterByMajor($inDivision, $answers['major']);

        $knownCosts = $withMajor
            ->map(fn (College $college): ?int => Affordability::applicableCost($college, $answers['home_state'])[0])
            ->filter(fn (?int $cost): bool => $cost !== null);
        $costRange = $knownCosts->isEmpty() ? null : [$knownCosts->min(), $knownCosts->max()];

        $ranked = $withMajor
            ->map(fn (College $college): CollegeMatch => $this->evaluate($college, $answers, $costRange))
            ->filter(fn (CollegeMatch $match): bool => $match->score >= self::MINIMUM_SCORE)
            ->sort(fn (CollegeMatch $a, CollegeMatch $b): int => [$b->score, $a->affordability->cost ?? PHP_INT_MAX, $a->college->name]
                <=> [$a->score, $b->affordability->cost ?? PHP_INT_MAX, $b->college->name])
            ->take(self::MAXIMUM_RESULTS)
            ->values();

        $shortfallReasons = $ranked->count() < self::MINIMUM_RESULTS
            ? $this->explainShortfall($answers, $eligible->count(), $inDivision->count(), $withMajor->count(), $ranked->count())
            : [];

        return new MatchResults($ranked, $shortfallReasons);
    }

    /**
     * @param  Collection<int, College>  $colleges
     * @return Collection<int, College>
     */
    private function filterByDivision(Collection $colleges, string $division): Collection
    {
        if (! isset(self::DIVISIONS[$division])) {
            return $colleges;
        }

        return $colleges->where('ncaa_division', self::DIVISIONS[$division])->values();
    }

    /**
     * Remove colleges known not to offer the major. Colleges where it is unknown stay,
     * and are flagged as unknown rather than assumed to offer it.
     *
     * @param  Collection<int, College>  $colleges
     * @return Collection<int, College>
     */
    private function filterByMajor(Collection $colleges, string $major): Collection
    {
        if (! isset(self::MAJOR_COLUMNS[$major])) {
            return $colleges;
        }

        return $colleges->reject(fn (College $college): bool => $college->{self::MAJOR_COLUMNS[$major]} === false)->values();
    }

    /**
     * Score a college as the weighted share of the athlete's stated preferences it meets.
     *
     * @param  array<string, mixed>  $answers
     * @param  array{0: int, 1: int}|null  $costRange  Lowest and highest known published cost among the candidates.
     */
    private function evaluate(College $college, array $answers, ?array $costRange): CollegeMatch
    {
        $weights = $this->weights($answers['priority']);
        $earned = 0.0;
        $possible = 0.0;
        $matchedOn = [];
        $unknowns = [];
        $tradeoffs = [];

        $matchedOn[] = 'NCAA Division '.$this->roman($college->ncaa_division).' women\'s soccer (verified for the '.$college->womens_soccer_verified_season.' season)';

        $majorColumn = self::MAJOR_COLUMNS[$answers['major']] ?? null;
        if ($majorColumn !== null) {
            $label = config("questionnaire.majors.{$answers['major']}");
            $possible += $weights['major'];

            if ($college->{$majorColumn} === true) {
                $earned += $weights['major'];
                $matchedOn[] = "Offers {$label} (federal degree data)";
            } else {
                $earned += $weights['major'] * 0.5;
                $unknowns[] = "We could not confirm whether it offers a bachelor's degree in {$label}.";
                $tradeoffs[] = "Its {$label} program is not confirmed in our data; check the college's catalog.";
            }
        }

        $affordability = Affordability::for($college, $answers['home_state'], self::BUDGET_RANGES[$answers['budget']] ?? null);

        if ($affordability->status === AffordabilityStatus::CostUnknown) {
            $unknowns[] = 'Its '.($college->cost_academic_year ?? '2026-27').' cost of attendance is not verified yet.';
        }

        if (isset(self::BUDGET_RANGES[$answers['budget']])) {
            $possible += $weights['budget'];
            $earned += $weights['budget'] * $this->affordabilityCredit($affordability, $costRange);

            if ($affordability->status === AffordabilityStatus::WithinBudget) {
                $matchedOn[] = 'Published cost is within your family\'s budget range, even before aid';
            }

            $possible += $weights['merit'];
            $earned += $weights['merit'] * match ($college->merit_aid) {
                MeritAidAvailability::Available => 1.0,
                MeritAidAvailability::Limited => 0.5,
                default => 0.0,
            };
        }

        foreach (['setting' => 'campus_environment', 'size' => 'size'] as $answer => $attribute) {
            if ($answers[$answer] === 'no_preference') {
                continue;
            }

            $possible += $weights[$answer];
            $actual = $college->{$attribute};
            $wanted = strtolower(config("questionnaire.{$answer}s.{$answers[$answer]}"));

            if ($actual === null) {
                $unknowns[] = $answer === 'size' ? 'Its undergraduate enrollment is not verified.' : 'Its campus setting is not verified.';
            } elseif ($actual->value === $answers[$answer]) {
                $earned += $weights[$answer];
                $matchedOn[] = $answer === 'size'
                    ? "{$actual->label()} college (".number_format($college->undergraduate_enrollment)." undergraduates, {$college->enrollment_term})"
                    : "{$actual->label()} campus (federal location: {$college->federal_locale})";
            } else {
                $tradeoffs[] = $answer === 'size'
                    ? "It is a {$actual->label()} college, not {$wanted}."
                    : "Its campus is {$actual->label()}, not {$wanted}.";
            }
        }

        if ($college->ncaa_division === 3 && in_array($answers['priority'], ['affordability', 'soccer'], true)) {
            $tradeoffs[] = 'Division III schools do not offer athletic scholarships; aid is academic or need-based.';
        }

        return new CollegeMatch(
            college: $college,
            score: $possible > 0 ? round($earned / $possible, 4) : 1.0,
            matchedOn: $matchedOn,
            unknowns: $unknowns,
            tradeoff: $tradeoffs[0] ?? null,
            affordability: $affordability,
        );
    }

    /**
     * How much each preference counts. Affordability always weighs most, and the
     * athlete's top priority shifts weight toward what she values.
     *
     * @return array{major: float, budget: float, merit: float, setting: float, size: float}
     */
    private function weights(string $priority): array
    {
        $weights = ['major' => 2.0, 'budget' => 3.0, 'merit' => 1.0, 'setting' => 2.0, 'size' => 2.0];

        return match ($priority) {
            'academics' => [...$weights, 'major' => 4.0],
            'affordability' => [...$weights, 'budget' => 10.0, 'merit' => 2.0],
            'social_life' => [...$weights, 'setting' => 3.0, 'size' => 3.0],
            default => $weights,
        };
    }

    /**
     * Credit for affordability. A published cost within the budget earns full credit,
     * and an unverified cost earns little. Otherwise colleges are compared with each
     * other: because the budget is after aid and the cost is before aid, most costs
     * exceed the budget, so the cheapest candidate earns the most credit instead of
     * every college being ruled out.
     *
     * @param  array{0: int, 1: int}|null  $costRange
     */
    private function affordabilityCredit(Affordability $affordability, ?array $costRange): float
    {
        if ($affordability->status === AffordabilityStatus::WithinBudget) {
            return 1.0;
        }

        if ($affordability->status === AffordabilityStatus::CostUnknown) {
            return 0.25;
        }

        if ($affordability->status === AffordabilityStatus::MayExceedBudget) {
            return 0.75;
        }

        [$lowest, $highest] = $costRange ?? [$affordability->cost, $affordability->cost];

        if ($highest === $lowest) {
            return 0.75;
        }

        return round(1 - ($affordability->cost - $lowest) / ($highest - $lowest), 4);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return list<string>
     */
    private function explainShortfall(array $answers, int $eligible, int $inDivision, int $withMajor, int $shown): array
    {
        $reasons = ["Our database currently has {$eligible} verified women's soccer programs."];

        if (isset(self::DIVISIONS[$answers['division']])) {
            $division = config("questionnaire.divisions.{$answers['division']}");
            $reasons[] = "{$inDivision} of them compete in {$division}.";
        }

        if (isset(self::MAJOR_COLUMNS[$answers['major']])) {
            $major = config("questionnaire.majors.{$answers['major']}");
            $reasons[] = "{$withMajor} of those offer {$major} or have not been confirmed either way.";
        }

        $reasons[] = $shown === 0
            ? 'None of them met enough of your other preferences (budget, campus environment, and size) to recommend.'
            : "Only {$shown} met enough of your other preferences (budget, campus environment, and size) to recommend.";

        $reasons[] = 'Rather than suggest poor matches, we are showing only the colleges that fit. Choosing "Open to multiple divisions" or "No preference" for some questions would widen the search.';

        return $reasons;
    }

    private function roman(?int $division): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III'][$division] ?? 'unknown';
    }
}
