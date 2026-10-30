<?php

namespace App\Recommendations;

use App\Models\College;

/**
 * One recommended college and the factual reasons it was matched.
 */
final readonly class CollegeMatch
{
    /**
     * @param  float  $score  Ranking score, including academic fit when scores could be compared.
     * @param  float  $preferenceScore  Score from the athlete's preferences only, which decides whether the college is shown.
     * @param  list<string>  $matchedOn  Preferences this college meets, stated as verified facts.
     * @param  list<string>  $unknowns  Facts we could not verify for this college.
     */
    public function __construct(
        public College $college,
        public float $score,
        public float $preferenceScore,
        public array $matchedOn,
        public array $unknowns,
        public ?string $tradeoff,
        public Affordability $affordability,
        public AcademicFit $academicFit,
    ) {}
}
