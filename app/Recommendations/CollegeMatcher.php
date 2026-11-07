<?php

namespace App\Recommendations;

use App\Enums\MeritAidAvailability;
use App\Models\College;
use Illuminate\Support\Collection;

/**
 * Filters and ranks verified colleges against questionnaire answers.
 *
 * Every college in the chosen division that is not known to lack the major is
 * sorted into one of two categories. A Strong Match meets the athlete's major,
 * size, and budget, each confirmed by verified data ("No preference" and "Unsure"
 * are not checked, and a "$45,000+" budget has no upper limit, so the card shows
 * the cost and says affordability is the family's decision). Every other college is an Other Option to Consider, and each
 * missed or unconfirmed criterion is listed as a tradeoff. Campus setting and
 * academic fit only change the order within a category.
 *
 * Only facts stored in the database are used. Published costs are prices before
 * financial aid, so they are compared to the family's after-aid budget only to show
 * how much grants or scholarships would need to cover, never as the family's cost.
 * SAT and ACT scores only change the order of colleges, and only when a reliable
 * comparison exists (see AcademicFit); they never remove a college. GPA is never
 * scored, because colleges report it on different scales. Position and club league
 * are not used: there is no roster data, and a club league cannot decide a division.
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

        $evaluated = $withMajor
            ->map(fn (College $college): CollegeMatch => $this->evaluate($college, $answers, $costRange))
            ->sort(fn (CollegeMatch $a, CollegeMatch $b): int => $this->compareScores($a, $b))
            ->values();

        $strongMatches = $evaluated->filter(fn (CollegeMatch $match): bool => $match->isStrongMatch())->values();

        $alternatives = $evaluated
            ->reject(fn (CollegeMatch $match): bool => $match->isStrongMatch())
            ->sort(fn (CollegeMatch $a, CollegeMatch $b): int => count($a->tradeoffs) <=> count($b->tradeoffs) ?: $this->compareScores($a, $b))
            ->values();

        $otherOptions = $alternatives->filter(fn (CollegeMatch $match): bool => $match->meetsMinimumScore())->values();

        if ($strongMatches->isEmpty() && $otherOptions->count() < self::MINIMUM_RESULTS) {
            $closestBelowMinimum = $alternatives
                ->reject(fn (CollegeMatch $match): bool => $match->meetsMinimumScore())
                ->sort(fn (CollegeMatch $a, CollegeMatch $b): int => [count($a->tradeoffs), $b->preferenceScore, $a->affordability->cost ?? PHP_INT_MAX, $a->college->name]
                    <=> [count($b->tradeoffs), $a->preferenceScore, $b->affordability->cost ?? PHP_INT_MAX, $b->college->name])
                ->take(self::MINIMUM_RESULTS - $otherOptions->count());

            $otherOptions = $otherOptions->concat($closestBelowMinimum)->values();
        }

        $shown = $strongMatches->count() + $otherOptions->count();

        $shortfallReasons = $shown < self::MINIMUM_RESULTS
            ? $this->explainShortfall($answers, $eligible->count(), $inDivision->count(), $withMajor->count(), $shown)
            : [];

        return new MatchResults($strongMatches, $otherOptions, $shortfallReasons);
    }

    /**
     * Higher scores first, then lower published costs, then alphabetical.
     */
    private function compareScores(CollegeMatch $a, CollegeMatch $b): int
    {
        return [$b->score, $a->affordability->cost ?? PHP_INT_MAX, $a->college->name]
            <=> [$a->score, $b->affordability->cost ?? PHP_INT_MAX, $b->college->name];
    }

    /**
     * The key criteria a Strong Match is not checked against, because the athlete chose
     * "Undecided" or "Other" for her major, "No preference" for size, or "Unsure" for budget.
     *
     * @param  array<string, mixed>  $answers
     * @return list<string>
     */
    public static function uncheckedCriteria(array $answers): array
    {
        return array_keys(array_filter([
            'major' => ! isset(self::MAJOR_COLUMNS[$answers['major']]),
            'size' => $answers['size'] === 'no_preference',
            'budget' => ! isset(self::BUDGET_RANGES[$answers['budget']]),
        ]));
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
        $notes = [];

        $matchedOn['division'] = 'NCAA Division '.$this->roman($college->ncaa_division).' women\'s soccer (verified for the '.$college->womens_soccer_verified_season.' season)';

        $majorColumn = self::MAJOR_COLUMNS[$answers['major']] ?? null;
        if ($majorColumn !== null) {
            $label = config("questionnaire.majors.{$answers['major']}");
            $possible += $weights['major'];

            if ($college->{$majorColumn} === true) {
                $earned += $weights['major'];
                $matchedOn['major'] = "Offers {$label} (federal degree data)";
            } else {
                $earned += $weights['major'] * 0.5;
                $tradeoffs['major'] = "We couldn't confirm that it offers a bachelor's degree in {$label}, so this match can't be confirmed. Check the college's course catalog.";
            }
        }

        $budgetRange = self::BUDGET_RANGES[$answers['budget']] ?? null;
        $affordability = Affordability::for($college, $answers['home_state'], $budgetRange);
        $costYear = $college->cost_academic_year ?? '2026-27';

        if ($budgetRange === null) {
            $notes['budget'] = $affordability->status === AffordabilityStatus::CostUnknown
                ? "Affordability has not been checked or confirmed: you chose \"Unsure\" for your family's budget, and this college's {$costYear} cost of attendance is not verified yet."
                : 'Affordability has not been checked: you chose "Unsure" for your family\'s budget, so this college\'s published cost ($'.number_format($affordability->cost).' per year, before aid) was not compared with a budget.';
        } else {
            $possible += $weights['budget'];
            $earned += $weights['budget'] * $this->affordabilityCredit($affordability, $costRange);

            match ($affordability->status) {
                AffordabilityStatus::WithinBudget => $matchedOn['budget'] = 'Published cost is within your family\'s budget range, even before aid',
                AffordabilityStatus::MayExceedBudget => $notes['budget'] = 'You chose an open-ended budget ($45,000+), so this college still counts for the budget check. Its published cost is $'.number_format($affordability->cost).' per year before aid. Your family will need to decide whether that is affordable.',
                AffordabilityStatus::AboveBudget => $tradeoffs['budget'] = $this->aboveBudgetTradeoff($college, $affordability, $answers['budget']),
                default => $tradeoffs['budget'] = "Its {$costYear} cost of attendance isn't verified, so we can't confirm it fits your family's budget.",
            };

            $possible += $weights['merit'];
            $earned += $weights['merit'] * match ($college->merit_aid) {
                MeritAidAvailability::Available => 1.0,
                MeritAidAvailability::Limited => 0.5,
                default => 0.0,
            };
        }

        if ($answers['size'] !== 'no_preference') {
            $possible += $weights['size'];
            $wanted = strtolower(config("questionnaire.sizes.{$answers['size']}"));
            $size = $college->size;

            if ($size === null) {
                $tradeoffs['size'] = "Its undergraduate enrollment isn't verified, so we can't confirm it's a {$wanted} college.";
            } elseif ($size->value === $answers['size']) {
                $earned += $weights['size'];
                $matchedOn['size'] = "{$size->label()} college (".number_format($college->undergraduate_enrollment)." undergraduates, {$college->enrollment_term})";
            } else {
                $tradeoffs['size'] = "It's a {$size->label()} college (".number_format($college->undergraduate_enrollment)." undergraduates, {$college->enrollment_term}), not {$wanted}.";
            }
        }

        if ($answers['setting'] !== 'no_preference') {
            $possible += $weights['setting'];
            $wanted = strtolower(config("questionnaire.settings.{$answers['setting']}"));
            $setting = $college->campus_environment;

            if ($setting === null) {
                $unknowns['setting'] = 'Its campus setting is not verified.';
            } elseif ($setting->value === $answers['setting']) {
                $earned += $weights['setting'];
                $matchedOn['setting'] = "{$setting->label()} campus (federal location: {$college->federal_locale})";
            } else {
                $notes['setting'] = "Its campus is {$setting->label()}, not {$wanted}.";
            }
        }

        if ($college->ncaa_division === 3 && in_array($answers['priority'], ['affordability', 'soccer'], true)) {
            $notes['athletic_scholarships'] = 'Division III schools do not offer athletic scholarships; aid is academic or need-based.';
        }

        $preferenceScore = $possible > 0 ? round($earned / $possible, 4) : 1.0;

        $academicFit = AcademicFit::for($college, $answers);

        if ($academicFit->credit() !== null) {
            $possible += $weights['academic'];
            $earned += $weights['academic'] * $academicFit->credit();
        }

        if (in_array($academicFit->status, [AcademicFitStatus::WithinRange, AcademicFitStatus::AboveRange], true)) {
            $position = $academicFit->status === AcademicFitStatus::WithinRange ? 'within' : 'above';
            $matchedOn['academic'] = "Your {$academicFit->test} score is {$position} the middle 50% at this college ({$college->admissions_term})";
        }

        return new CollegeMatch(
            college: $college,
            score: $possible > 0 ? round($earned / $possible, 4) : 1.0,
            preferenceScore: $preferenceScore,
            matchedOn: $matchedOn,
            unknowns: $unknowns,
            tradeoffs: $tradeoffs,
            notes: $notes,
            affordability: $affordability,
            academicFit: $academicFit,
        );
    }

    /**
     * Describe how far a published cost is above the budget, without treating it as the family's final cost.
     */
    private function aboveBudgetTradeoff(College $college, Affordability $affordability, string $budget): string
    {
        $rate = $affordability->rate ? " ({$affordability->rate} rate)" : '';
        $tradeoff = 'Its published cost, $'.number_format($affordability->cost)." per year{$rate}, is $".number_format($affordability->gap)
            .' above the top of your family\'s budget ('.config("questionnaire.budgets.{$budget}").'). Financial aid could close some or all of this gap; the college\'s Net Price Calculator can estimate it.';

        return $tradeoff.match ($college->merit_aid) {
            MeritAidAvailability::Available => ' This college offers merit scholarships.',
            MeritAidAvailability::Limited => ' This college offers limited merit scholarships.',
            default => '',
        };
    }

    /**
     * How much each preference counts. Affordability always weighs most, and the
     * athlete's top priority shifts weight toward what she values.
     *
     * @return array{major: float, budget: float, merit: float, setting: float, size: float, academic: float}
     */
    private function weights(string $priority): array
    {
        $weights = ['major' => 2.0, 'budget' => 3.0, 'merit' => 1.0, 'setting' => 2.0, 'size' => 2.0, 'academic' => 2.0];

        return match ($priority) {
            'academics' => [...$weights, 'major' => 4.0, 'academic' => 4.0],
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

        if ($shown < $withMajor) {
            $reasons[] = "Only {$shown} met enough of your other preferences (budget, campus environment, and size) to recommend.";
            $reasons[] = 'Rather than suggest poor matches, we are showing only the colleges that fit.';
        } elseif ($shown > 0) {
            $reasons[] = $shown === 1 ? 'That college is shown below.' : "All {$shown} are shown below.";
        }

        $reasons[] = 'Choosing "Open to multiple divisions" or "No preference" for some questions would widen the search.';

        return $reasons;
    }

    private function roman(?int $division): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III'][$division] ?? 'unknown';
    }
}
