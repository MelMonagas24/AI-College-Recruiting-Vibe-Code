<?php

namespace App\Recommendations;

use Illuminate\Support\Collection;

/**
 * The colleges recommended for one set of questionnaire answers.
 */
final readonly class MatchResults
{
    /**
     * @param  Collection<int, CollegeMatch>  $matches
     * @param  list<string>  $shortfallReasons  Why fewer than the minimum number of colleges matched.
     */
    public function __construct(
        public Collection $matches,
        public array $shortfallReasons,
    ) {}

    public function hasShortfall(): bool
    {
        return $this->matches->count() < CollegeMatcher::MINIMUM_RESULTS;
    }
}
