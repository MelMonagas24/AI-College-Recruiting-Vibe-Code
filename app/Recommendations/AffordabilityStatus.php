<?php

namespace App\Recommendations;

/**
 * How a college's published cost compares with the family's estimated budget.
 */
enum AffordabilityStatus: string
{
    /** The published cost is at or below the top of the budget range, even before aid. */
    case WithinBudget = 'within_budget';

    /** The published cost is above the top of the budget range. */
    case AboveBudget = 'above_budget';

    /** The budget is "$45,000+" with no upper limit, so a higher cost may or may not fit. */
    case MayExceedBudget = 'may_exceed_budget';

    /** The family chose "Unsure", so no comparison is made. */
    case NoBudget = 'no_budget';

    /** The college's cost is not verified, so no comparison can be made. */
    case CostUnknown = 'cost_unknown';
}
