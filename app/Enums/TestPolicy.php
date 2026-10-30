<?php

namespace App\Enums;

/**
 * How a college uses SAT or ACT scores in first-year admission, as the college
 * or federal data reports it.
 */
enum TestPolicy: string
{
    case Required = 'required';
    case RequiredForSome = 'required_for_some';
    case Recommended = 'recommended';
    case Optional = 'optional';
    case NotConsidered = 'not_considered';
    case NeitherRequiredNorRecommended = 'neither_required_nor_recommended';

    /**
     * The policy as a sentence, in the wording of the college's Common Data Set.
     */
    public function description(): string
    {
        return match ($this) {
            self::Required => 'Required.',
            self::RequiredForSome => 'Required for some applicants.',
            self::Recommended => 'Recommended, not required.',
            self::Optional => 'Optional. You can apply without test scores.',
            self::NotConsidered => 'Not considered. This college does not use SAT or ACT scores, so test scores don\'t affect this match.',
            self::NeitherRequiredNorRecommended => 'Neither required nor recommended.',
        };
    }

    /**
     * The policy in the wording of federal College Scorecard data.
     */
    public function federalLabel(): string
    {
        return match ($this) {
            self::Required => 'Required',
            self::RequiredForSome => 'Required for some applicants',
            self::Recommended => 'Recommended',
            self::Optional => 'Considered if submitted, not required',
            self::NotConsidered => 'Not considered',
            self::NeitherRequiredNorRecommended => 'Neither required nor recommended',
        };
    }
}
