<?php

namespace App\Recommendations;

use RuntimeException;

/**
 * Thrown when an AI response does not stick to the verified facts it was given.
 */
class InvalidExplanationException extends RuntimeException
{
    /**
     * @param  string  $rule  Short name of the check that failed, safe to log (for example "money" or "other_college").
     */
    public function __construct(public readonly string $rule, string $message)
    {
        parent::__construct($message);
    }
}
