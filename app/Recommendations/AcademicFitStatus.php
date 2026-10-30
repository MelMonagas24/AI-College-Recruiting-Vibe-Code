<?php

namespace App\Recommendations;

/**
 * Where an athlete's SAT or ACT score falls compared with a college's first-year students.
 */
enum AcademicFitStatus: string
{
    /** The score is within the middle 50% (from the 25th to the 75th percentile, inclusive). */
    case WithinRange = 'within_range';

    /** The score is above the 75th percentile. */
    case AboveRange = 'above_range';

    /** The score is below the 25th percentile. */
    case BelowRange = 'below_range';

    /** No reliable comparison: no score entered, or no comparable data for this college. */
    case NotCompared = 'not_compared';

    /**
     * Share of the academic-fit weight earned. Null when scores are not compared, so the
     * college is neither rewarded nor penalized. A score below the range keeps most of the
     * credit, because colleges admit students below it and consider much more than scores.
     */
    public function credit(): ?float
    {
        return match ($this) {
            self::WithinRange, self::AboveRange => 1.0,
            self::BelowRange => 0.75,
            self::NotCompared => null,
        };
    }
}
