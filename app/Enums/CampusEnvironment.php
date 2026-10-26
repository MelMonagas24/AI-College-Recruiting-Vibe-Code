<?php

namespace App\Enums;

/**
 * The questionnaire's broad campus environment categories.
 */
enum CampusEnvironment: string
{
    case Urban = 'urban';
    case Suburban = 'suburban';
    case Rural = 'rural';

    /**
     * Map a U.S. Department of Education (IPEDS) locale such as "City: Small"
     * to a broad category: City is Urban, Suburb is Suburban, Town or Rural is Rural.
     */
    public static function fromFederalLocale(?string $locale): ?self
    {
        if ($locale === null) {
            return null;
        }

        return match (strtolower(trim(strtok($locale, ':')))) {
            'city' => self::Urban,
            'suburb' => self::Suburban,
            'town', 'rural' => self::Rural,
            default => null,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
