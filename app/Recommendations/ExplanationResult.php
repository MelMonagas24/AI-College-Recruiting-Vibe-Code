<?php

namespace App\Recommendations;

/**
 * Personalized explanations for a set of matches, or the reason they are unavailable.
 */
final readonly class ExplanationResult
{
    /**
     * @param  array<int, string>  $explanations  Explanation text keyed by college ID.
     */
    public function __construct(
        public ExplanationStatus $status,
        public array $explanations = [],
        public ?string $message = null,
    ) {}

    /**
     * @param  array<int, string>  $explanations
     */
    public static function generated(array $explanations): self
    {
        return new self(ExplanationStatus::Generated, $explanations);
    }

    public static function notConfigured(): self
    {
        return new self(
            ExplanationStatus::NotConfigured,
            message: 'Personalized AI explanations are not connected yet. The facts shown for each college come straight from our verified database.',
        );
    }

    public static function failed(string $message): self
    {
        return new self(ExplanationStatus::Failed, message: $message);
    }

    public function for(int $collegeId): ?string
    {
        return $this->explanations[$collegeId] ?? null;
    }
}
