<?php

namespace App\Recommendations;

use App\Models\College;

/**
 * One recommended college and the factual reasons it was matched.
 */
final readonly class CollegeMatch
{
    /**
     * Each list of reasons is keyed by topic (such as "budget", "size", or "setting"),
     * so cost information can be kept out of AI explanations.
     *
     * @param  float  $score  Ranking score, including academic fit when scores could be compared.
     * @param  float  $preferenceScore  Score from the athlete's preferences only, which decides whether an Other Option is shown.
     * @param  array<string, string>  $matchedOn  Preferences this college meets, stated as verified facts.
     * @param  array<string, string>  $unknowns  Facts we could not verify for this college.
     * @param  array<string, string>  $tradeoffs  Key criteria ("major", "size", "budget") this college misses or we could not confirm. Empty for a Strong Match.
     * @param  array<string, string>  $notes  Other things to consider that never decide the category, such as campus setting.
     */
    public function __construct(
        public College $college,
        public float $score,
        public float $preferenceScore,
        public array $matchedOn,
        public array $unknowns,
        public array $tradeoffs,
        public array $notes,
        public Affordability $affordability,
        public AcademicFit $academicFit,
    ) {}

    /**
     * A Strong Match meets the athlete's division, major, size, and budget, each confirmed by verified data.
     */
    public function isStrongMatch(): bool
    {
        return $this->tradeoffs === [];
    }

    /**
     * Topics that describe cost, budget, or financial aid. They are shown only in the verified data on each card.
     */
    public const COST_TOPICS = ['budget', 'athletic_scholarships'];

    /**
     * Whether the college meets enough of the athlete's preferences to be listed without being a fallback alternative.
     */
    public function meetsMinimumScore(): bool
    {
        return $this->preferenceScore >= CollegeMatcher::MINIMUM_SCORE;
    }
}
