<?php

namespace App\Enums;

/**
 * Approximate competitive groupings of youth soccer leagues, used for future matching.
 *
 * These are rough groupings only, not official national rankings, and they must never
 * be used on their own to decide which NCAA division an athlete qualifies for.
 */
enum CompetitiveTier: string
{
    case Tier1 = 'tier_1';
    case Tier2 = 'tier_2';
    case Tier3 = 'tier_3';
    case Tier4 = 'tier_4';
    case Tier5 = 'tier_5';
    case HighSchoolOnly = 'high_school_only';
    case OtherOrNotSure = 'other_or_not_sure';

    public function label(): string
    {
        return match ($this) {
            self::Tier1 => 'Tier 1',
            self::Tier2 => 'Tier 2',
            self::Tier3 => 'Tier 3',
            self::Tier4 => 'Tier 4',
            self::Tier5 => 'Tier 5',
            self::HighSchoolOnly => 'High school only',
            self::OtherOrNotSure => 'Other / Not sure',
        };
    }
}
