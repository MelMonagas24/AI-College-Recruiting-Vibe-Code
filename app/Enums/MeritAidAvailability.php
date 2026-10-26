<?php

namespace App\Enums;

/**
 * Whether a college offers merit scholarships to incoming first-year students.
 *
 * This records availability only, never award amounts.
 */
enum MeritAidAvailability: string
{
    case Available = 'available';
    case Limited = 'limited';
    case NotOffered = 'not_offered';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Limited => 'Limited',
            self::NotOffered => 'Not offered',
            self::Unknown => 'Unknown',
        };
    }
}
