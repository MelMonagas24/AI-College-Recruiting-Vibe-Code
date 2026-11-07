<?php

namespace App\Recommendations;

use App\Models\College;

/**
 * Whether a college's federal average net price can fairly be compared for this athlete.
 *
 * College Scorecard's average net price covers full-time first-year students who
 * received federal financial aid. At public colleges it covers only students who
 * pay in-state tuition, so it never applies to out-of-state athletes there.
 */
enum NetPriceComparison: string
{
    /** The average covers students in the athlete's situation: a private college, or a public college in her home state. */
    case Applies = 'applies';

    /** A public college outside the athlete's home state: the average covers in-state students only. */
    case InStateOnly = 'in_state_only';

    /** The athlete lives outside the U.S.: the average covers students who received U.S. federal aid. */
    case UsAidRecipientsOnly = 'us_aid_recipients_only';

    /** The college has no federal average net price in our data. */
    case Unavailable = 'unavailable';

    public static function for(College $college, ?string $homeState): self
    {
        return match (true) {
            $college->average_net_price === null || $college->is_public === null => self::Unavailable,
            $homeState === null || $homeState === 'outside_us' => self::UsAidRecipientsOnly,
            $college->is_public && $homeState !== $college->state => self::InStateOnly,
            default => self::Applies,
        };
    }
}
