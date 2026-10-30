<?php

namespace App\Recommendations;

use Illuminate\Support\Collection;

/**
 * The colleges recommended for one set of questionnaire answers.
 */
final readonly class MatchResults
{
    /**
     * @param  Collection<int, CollegeMatch>  $matches  The top matches, at most CollegeMatcher::MAXIMUM_RESULTS.
     * @param  list<string>  $shortfallReasons  Why fewer than the minimum number of colleges matched.
     * @param  Collection<int, CollegeMatch>  $alsoFits  Every other college that meets the athlete's preferences, so none is hidden by the top-match limit.
     */
    public function __construct(
        public Collection $matches,
        public array $shortfallReasons,
        public Collection $alsoFits = new Collection,
    ) {}

    public function hasShortfall(): bool
    {
        return $this->matches->count() < CollegeMatcher::MINIMUM_RESULTS;
    }

    /**
     * How many matches have a verified published cost above the family's budget.
     */
    public function aboveBudgetCount(): int
    {
        return $this->matches->filter(fn (CollegeMatch $match): bool => $match->affordability->isAboveBudget())->count();
    }

    /**
     * How many matches have a cost we could not verify.
     */
    public function unknownCostCount(): int
    {
        return $this->matches->filter(fn (CollegeMatch $match): bool => $match->affordability->status === AffordabilityStatus::CostUnknown)->count();
    }
}
