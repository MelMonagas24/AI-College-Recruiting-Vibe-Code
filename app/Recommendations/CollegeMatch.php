<?php

namespace App\Recommendations;

use App\Models\College;

/**
 * One recommended college and the factual reasons it was matched.
 */
final readonly class CollegeMatch
{
    /**
     * @param  list<string>  $matchedOn  Preferences this college meets, stated as verified facts.
     * @param  list<string>  $unknowns  Facts we could not verify for this college.
     * @param  int|null  $costGap  Published cost of attendance minus the family's top budget, when both are known.
     */
    public function __construct(
        public College $college,
        public float $score,
        public array $matchedOn,
        public array $unknowns,
        public ?string $tradeoff,
        public ?int $costGap,
    ) {}
}
