<?php

namespace App\Recommendations;

/**
 * The affordability label on a college's cost details.
 *
 * Only WithinBudget, which compares the verified published cost, can support a
 * Strong Match. PotentiallyAffordable uses a federal average for aided students,
 * so it only suggests a college may be worth exploring after financial aid.
 */
enum CostLabel: string
{
    /** The verified published cost is within the family's budget, even before aid. */
    case WithinBudget = 'within_budget';

    /** The published cost is above budget or unverified, but the applicable federal average net price is within budget. */
    case PotentiallyAffordable = 'potentially_affordable';

    /** The published cost, and the applicable federal average net price when available, are above budget. */
    case AboveBudget = 'above_budget';

    /** No fair comparison exists: no verified cost, or a federal average that does not apply to this athlete. */
    case CannotCompare = 'cannot_compare';

    /** The family chose "Unsure" or the open-ended "$45,000+" budget, so costs are shown but not compared. */
    case NotCompared = 'not_compared';

    public function label(): string
    {
        return match ($this) {
            self::WithinBudget => 'Within budget (published cost)',
            self::PotentiallyAffordable => 'Potentially affordable after aid',
            self::AboveBudget => 'Above budget',
            self::CannotCompare => "Can't compare",
            self::NotCompared => 'Not compared',
        };
    }
}
