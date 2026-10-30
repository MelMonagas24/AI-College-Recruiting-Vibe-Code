<?php

namespace App\Recommendations;

use App\Enums\TestPolicy;
use App\Models\College;

/**
 * Compares an athlete's SAT or ACT score with a college's first-year students, only
 * when the comparison is reliable.
 *
 * A score is compared only with a total-score middle 50% range from the college's own
 * Common Data Set, and only when at least MINIMUM_SUBMITTED_PERCENT of first-year
 * students submitted that test. SAT and ACT scores are never converted. GPA is never
 * compared, because colleges calculate it on different scales. Nothing here predicts
 * admission: these ranges describe all first-year students, not recruited athletes.
 */
final readonly class AcademicFit
{
    public const MINIMUM_SUBMITTED_PERCENT = 25.0;

    /**
     * @param  string|null  $test  "SAT" or "ACT" when a score was compared.
     * @param  list<string>  $notes  Plain-language sentences about the athlete's scores at this college.
     */
    public function __construct(
        public AcademicFitStatus $status,
        public ?string $test,
        public ?int $score,
        public ?int $rangeLow,
        public ?int $rangeHigh,
        public array $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $answers  Validated questionnaire answers.
     */
    public static function for(College $college, array $answers): self
    {
        $scores = array_filter([
            'SAT' => filled($answers['sat_score'] ?? null) ? (int) $answers['sat_score'] : null,
            'ACT' => filled($answers['act_score'] ?? null) ? (int) $answers['act_score'] : null,
        ], fn (?int $score): bool => $score !== null);

        if ($college->admissions_data_source === null || $college->test_policy === TestPolicy::NotConsidered) {
            return self::notCompared([]);
        }

        if ($scores === []) {
            return self::notCompared([
                $college->hasCommonDataSetAdmissions() && $college->test_policy === TestPolicy::Required
                    ? "You haven't entered an SAT or ACT score. This college requires one for {$college->test_policy_term}."
                    : "You haven't entered an SAT or ACT score, so test scores don't affect this match.",
            ]);
        }

        if (! $college->hasCommonDataSetAdmissions()) {
            return self::notCompared([
                "We don't compare your score because federal data doesn't show how many students submitted scores.",
            ]);
        }

        $comparable = array_filter(
            $scores,
            fn (int $score, string $test): bool => self::isReliable($college, $test),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($comparable === []) {
            return self::notCompared(array_map(
                fn (string $test): string => self::whyNotCompared($college, $test),
                array_keys($scores),
            ));
        }

        $test = collect(array_keys($comparable))
            ->sortByDesc(fn (string $test): float => self::submittedPercent($college, $test))
            ->first();

        return self::compare($college, $test, $comparable[$test]);
    }

    /**
     * Whether the college's range for this test is detailed and representative enough to compare.
     */
    public static function isReliable(College $college, string $test): bool
    {
        [$low, $high] = self::range($college, $test);

        return $college->hasCommonDataSetAdmissions()
            && $low !== null
            && $high !== null
            && self::submittedPercent($college, $test) >= self::MINIMUM_SUBMITTED_PERCENT;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    public static function range(College $college, string $test): array
    {
        return $test === 'SAT'
            ? [$college->sat_composite_25th, $college->sat_composite_75th]
            : [$college->act_composite_25th, $college->act_composite_75th];
    }

    public static function submittedPercent(College $college, string $test): float
    {
        return ($test === 'SAT' ? $college->sat_submitted_percent : $college->act_submitted_percent) ?? 0.0;
    }

    /**
     * A percentage without a needless ".0", such as "9%" or "24.7%".
     */
    public static function percent(?float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
    }

    public function credit(): ?float
    {
        return $this->status->credit();
    }

    private static function compare(College $college, string $test, int $score): self
    {
        [$low, $high] = self::range($college, $test);

        $status = match (true) {
            $score < $low => AcademicFitStatus::BelowRange,
            $score > $high => AcademicFitStatus::AboveRange,
            default => AcademicFitStatus::WithinRange,
        };

        $position = match ($status) {
            AcademicFitStatus::BelowRange => 'below',
            AcademicFitStatus::AboveRange => 'above',
            default => 'within',
        };

        $note = "Your {$test} score ({$score}) is {$position} the middle 50% of {$college->admissions_term} first-year students who submitted {$test} scores ({$low}–{$high}).";

        if ($status === AcademicFitStatus::BelowRange) {
            $note .= ' One in four of those students scored below this range, and colleges consider much more than test scores.';
        }

        return new self($status, $test, $score, $low, $high, [$note]);
    }

    private static function whyNotCompared(College $college, string $test): string
    {
        [$low, $high] = self::range($college, $test);
        $otherTest = $test === 'SAT' ? 'ACT' : 'SAT';
        $percent = self::percent(self::submittedPercent($college, $test));

        if ($low === null || $high === null) {
            return "This college doesn't report an {$test} range for {$college->admissions_term} first-year students, so we don't compare your {$test} score.";
        }

        if (self::isReliable($college, $otherTest)) {
            return "Too few students here submitted {$test} scores ({$percent}) to compare your score. We don't convert between SAT and ACT.";
        }

        return "Only {$percent} of {$college->admissions_term} first-year students submitted {$test} scores, so we don't compare your score here.";
    }

    /**
     * @param  list<string>  $notes
     */
    private static function notCompared(array $notes): self
    {
        return new self(AcademicFitStatus::NotCompared, null, null, null, null, $notes);
    }
}
