<?php

namespace App\Enums;

/**
 * How completely a college record has been checked against official sources.
 */
enum VerificationStatus: string
{
    /** Every stored fact was read directly from an official source. */
    case Verified = 'verified';

    /** Some facts are verified; the rest are stored as unknown. */
    case PartiallyVerified = 'partially_verified';

    /** Not yet checked against official sources. */
    case Unverified = 'unverified';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::PartiallyVerified => 'Partially verified',
            self::Unverified => 'Unverified',
        };
    }
}
