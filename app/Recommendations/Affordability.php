<?php

namespace App\Recommendations;

use App\Models\College;

/**
 * A college's published cost for this athlete compared with her family's budget.
 *
 * Only verified published costs are used. These are prices before financial aid,
 * so nothing here estimates scholarships or what a family will actually pay.
 */
final readonly class Affordability
{
    /**
     * @param  int|null  $cost  Published annual cost of attendance that applies to the athlete.
     * @param  string|null  $rate  "in-state" or "out-of-state" for public colleges; null when one price applies to everyone.
     * @param  int|null  $budgetMinimum  Bottom of the family's budget range.
     * @param  int|null  $budgetMaximum  Top of the family's budget range; null when there is no upper limit.
     * @param  int|null  $gap  How far the cost is above the top of the budget.
     */
    public function __construct(
        public AffordabilityStatus $status,
        public ?int $cost,
        public ?string $rate,
        public ?int $budgetMinimum,
        public ?int $budgetMaximum,
        public ?int $gap,
        public ?bool $costIsComplete,
    ) {}

    /**
     * @param  array{0: int|null, 1: int|null}|null  $budgetRange  Null when the family is unsure.
     */
    public static function for(College $college, ?string $homeState, ?array $budgetRange): self
    {
        [$cost, $rate] = self::applicableCost($college, $homeState);
        [$minimum, $maximum] = $budgetRange ?? [null, null];

        $status = match (true) {
            $cost === null => AffordabilityStatus::CostUnknown,
            $budgetRange === null => AffordabilityStatus::NoBudget,
            $maximum === null => $cost <= $minimum ? AffordabilityStatus::WithinBudget : AffordabilityStatus::MayExceedBudget,
            $cost <= $maximum => AffordabilityStatus::WithinBudget,
            default => AffordabilityStatus::AboveBudget,
        };

        return new self(
            status: $status,
            cost: $cost,
            rate: $rate,
            budgetMinimum: $minimum,
            budgetMaximum: $maximum,
            gap: $status === AffordabilityStatus::AboveBudget ? $cost - $maximum : null,
            costIsComplete: $college->cost_of_attendance_is_complete,
        );
    }

    /**
     * The published cost that applies to the athlete: the in-state rate when she lives
     * in the college's state, otherwise the out-of-state rate. Private colleges publish
     * one price, stored as the same amount in both columns.
     *
     * @return array{0: int|null, 1: string|null}
     */
    public static function applicableCost(College $college, ?string $homeState): array
    {
        $inState = $homeState !== null && $homeState === $college->state;
        $cost = $inState ? $college->cost_of_attendance_in_state : $college->cost_of_attendance_out_of_state;
        $hasTwoRates = $college->cost_of_attendance_in_state !== null
            && $college->cost_of_attendance_in_state !== $college->cost_of_attendance_out_of_state;

        return [$cost, $cost !== null && $hasTwoRates ? ($inState ? 'in-state' : 'out-of-state') : null];
    }

    public function isAboveBudget(): bool
    {
        return $this->status === AffordabilityStatus::AboveBudget;
    }
}
