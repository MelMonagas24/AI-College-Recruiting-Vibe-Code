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

    /**
     * The result after each college's explanation was checked on its own.
     *
     * @param  array<int, string>  $accepted  Explanations that passed the fact check, keyed by college ID.
     */
    public static function checked(array $accepted, int $requested): self
    {
        return match (true) {
            count($accepted) === $requested => self::generated($accepted),
            $accepted === [] => self::failed('The AI explanations did not pass our fact check, so they are not shown. The verified facts on every card are unaffected.'),
            default => new self(
                ExplanationStatus::Partial,
                $accepted,
                'Some AI explanations did not pass our fact check, so they are not shown. The verified facts on every card are unaffected.',
            ),
        };
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
