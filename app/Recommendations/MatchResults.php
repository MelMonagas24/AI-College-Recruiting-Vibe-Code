<?php

namespace App\Recommendations;

use Illuminate\Support\Collection;

/**
 * The colleges recommended for one set of questionnaire answers, in two categories.
 *
 * Strong Matches meet every key criterion. Other Options to Consider pass the
 * division and major filters but have tradeoffs. Each category shows its first
 * colleges as full cards and the rest as expandable cards, so none is hidden.
 */
final readonly class MatchResults
{
    /**
     * Full cards for Other Options when there are Strong Matches above them.
     */
    public const OTHER_OPTIONS_SHOWN_WITH_STRONG_MATCHES = 3;

    /**
     * The first colleges on the page, which receive personalized explanations.
     *
     * @var Collection<int, CollegeMatch>
     */
    public Collection $matches;

    /**
     * @param  Collection<int, CollegeMatch>  $strongMatches  Every Strong Match, best first.
     * @param  Collection<int, CollegeMatch>  $otherOptions  Every Other Option to Consider, fewest tradeoffs first.
     * @param  list<string>  $shortfallReasons  Why fewer than the minimum number of colleges are shown.
     */
    public function __construct(
        public Collection $strongMatches,
        public Collection $otherOptions,
        public array $shortfallReasons = [],
    ) {
        $this->matches = $this->featuredStrongMatches()
            ->concat($this->featuredOtherOptions())
            ->take(CollegeMatcher::MAXIMUM_RESULTS)
            ->values();
    }

    /**
     * Every college shown on the page, Strong Matches first.
     *
     * @return Collection<int, CollegeMatch>
     */
    public function all(): Collection
    {
        return $this->strongMatches->concat($this->otherOptions)->values();
    }

    public function hasStrongMatches(): bool
    {
        return $this->strongMatches->isNotEmpty();
    }

    public function hasShortfall(): bool
    {
        return $this->all()->count() < CollegeMatcher::MINIMUM_RESULTS;
    }

    /**
     * @return Collection<int, CollegeMatch>
     */
    public function featuredStrongMatches(): Collection
    {
        return $this->strongMatches->take(CollegeMatcher::MAXIMUM_RESULTS)->values();
    }

    /**
     * @return Collection<int, CollegeMatch>
     */
    public function moreStrongMatches(): Collection
    {
        return $this->strongMatches->slice(CollegeMatcher::MAXIMUM_RESULTS)->values();
    }

    /**
     * More Other Options get full cards when there are no Strong Matches, because they are the closest alternatives.
     *
     * @return Collection<int, CollegeMatch>
     */
    public function featuredOtherOptions(): Collection
    {
        return $this->otherOptions->take($this->featuredOtherOptionLimit())->values();
    }

    /**
     * @return Collection<int, CollegeMatch>
     */
    public function moreOtherOptions(): Collection
    {
        return $this->otherOptions->slice($this->featuredOtherOptionLimit())->values();
    }

    /**
     * Whether the college is among the first colleges on the page, which receive personalized explanations.
     */
    public function isExplained(CollegeMatch $match): bool
    {
        return $this->matches->contains(fn (CollegeMatch $explained): bool => $explained->college->is($match->college));
    }

    /**
     * How many shown colleges have a verified published cost above the family's budget.
     */
    public function aboveBudgetCount(): int
    {
        return $this->all()->filter(fn (CollegeMatch $match): bool => $match->affordability->isAboveBudget())->count();
    }

    /**
     * How many Other Options are potentially affordable after aid, based on an applicable federal average net price.
     */
    public function potentiallyAffordableCount(): int
    {
        return $this->otherOptions->filter(fn (CollegeMatch $match): bool => $match->affordability->isPotentiallyAffordable())->count();
    }

    /**
     * How many shown colleges have a cost we could not verify.
     */
    public function unknownCostCount(): int
    {
        return $this->all()->filter(fn (CollegeMatch $match): bool => $match->affordability->status === AffordabilityStatus::CostUnknown)->count();
    }

    private function featuredOtherOptionLimit(): int
    {
        return $this->hasStrongMatches() ? self::OTHER_OPTIONS_SHOWN_WITH_STRONG_MATCHES : CollegeMatcher::MAXIMUM_RESULTS;
    }
}
