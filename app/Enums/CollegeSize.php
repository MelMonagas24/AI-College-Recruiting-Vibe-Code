<?php

namespace App\Enums;

/**
 * The questionnaire's college size categories, based on undergraduate enrollment.
 */
enum CollegeSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    /**
     * Small is fewer than 5,000 undergraduates, Medium is 5,000 to 15,000, and Large is more than 15,000.
     */
    public static function fromEnrollment(?int $undergraduates): ?self
    {
        return match (true) {
            $undergraduates === null => null,
            $undergraduates < 5000 => self::Small,
            $undergraduates <= 15000 => self::Medium,
            default => self::Large,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
