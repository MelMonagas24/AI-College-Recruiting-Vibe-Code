<?php

namespace Tests\Feature;

use App\Enums\TestPolicy;
use App\Models\College;
use App\Recommendations\AcademicFit;
use App\Recommendations\AcademicFitStatus;
use App\Recommendations\CollegeMatch;
use App\Recommendations\CollegeMatcher;
use Database\Seeders\CollegeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicFitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Answers from a fictional athlete.
     *
     * @return array<string, mixed>
     */
    private function fictionalAthlete(array $overrides = []): array
    {
        return array_merge([
            'sport' => 'womens_soccer',
            'graduation_year' => now()->year + 2,
            'division' => 'open',
            'position' => 'midfielder',
            'league' => 'ecnl_rl',
            'gpa' => '3.60',
            'test_taken' => 'none',
            'major' => 'undecided',
            'budget' => 'unsure',
            'home_state' => 'OH',
            'setting' => 'no_preference',
            'size' => 'no_preference',
            'priority' => 'balance',
        ], $overrides);
    }

    /**
     * A fictional college with Common Data Set admissions facts.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function fictionalCollege(array $overrides = []): College
    {
        return College::factory()->make(array_merge([
            'admissions_data_source' => College::COMMON_DATA_SET,
            'admissions_term' => 'Fall 2025',
            'admission_rate' => 50.0,
            'test_policy' => TestPolicy::Optional,
            'test_policy_term' => 'Fall 2027 applicants',
            'sat_composite_25th' => 1200,
            'sat_composite_75th' => 1400,
            'sat_submitted_percent' => 40.0,
            'act_composite_25th' => 26,
            'act_composite_75th' => 31,
            'act_submitted_percent' => 30.0,
        ], $overrides));
    }

    private function sat(int $score): array
    {
        return $this->fictionalAthlete(['test_taken' => 'sat', 'sat_score' => $score]);
    }

    public function test_scores_within_above_and_below_the_middle_50_percent_earn_the_approved_credit(): void
    {
        $college = $this->fictionalCollege();

        $within = AcademicFit::for($college, $this->sat(1300));
        $edge = AcademicFit::for($college, $this->sat(1200));
        $above = AcademicFit::for($college, $this->sat(1500));
        $below = AcademicFit::for($college, $this->sat(1100));

        $this->assertSame(AcademicFitStatus::WithinRange, $within->status);
        $this->assertSame(1.0, $within->credit());
        $this->assertSame(AcademicFitStatus::WithinRange, $edge->status, 'The 25th percentile score itself is within the range.');
        $this->assertSame(AcademicFitStatus::AboveRange, $above->status);
        $this->assertSame(1.0, $above->credit());
        $this->assertSame(AcademicFitStatus::BelowRange, $below->status);
        $this->assertSame(0.75, $below->credit());

        $this->assertSame(['Your SAT score (1300) is within the middle 50% of Fall 2025 first-year students who submitted SAT scores (1200–1400).'], $within->notes);
        $this->assertStringContainsString('One in four of those students scored below this range, and colleges consider much more than test scores.', $below->notes[0]);
    }

    public function test_athletes_without_test_scores_are_never_scored_or_penalized(): void
    {
        $fit = AcademicFit::for($this->fictionalCollege(), $this->fictionalAthlete());

        $this->assertSame(AcademicFitStatus::NotCompared, $fit->status);
        $this->assertNull($fit->credit());
        $this->assertSame(["You haven't entered an SAT or ACT score, so test scores don't affect this match."], $fit->notes);
    }

    public function test_athletes_without_scores_are_told_when_a_college_requires_them(): void
    {
        $fit = AcademicFit::for($this->fictionalCollege(['test_policy' => TestPolicy::Required]), $this->fictionalAthlete());

        $this->assertNull($fit->credit());
        $this->assertSame(["You haven't entered an SAT or ACT score. This college requires one for Fall 2027 applicants."], $fit->notes);
    }

    public function test_scores_are_not_compared_when_fewer_than_25_percent_of_students_submitted_them(): void
    {
        $college = $this->fictionalCollege(['sat_submitted_percent' => 24.7, 'act_submitted_percent' => 8.2]);

        $fit = AcademicFit::for($college, $this->sat(1300));

        $this->assertSame(AcademicFitStatus::NotCompared, $fit->status);
        $this->assertNull($fit->credit());
        $this->assertSame(['Only 24.7% of Fall 2025 first-year students submitted SAT scores, so we don\'t compare your score here.'], $fit->notes);
        $this->assertTrue(AcademicFit::isReliable($this->fictionalCollege(['sat_submitted_percent' => 25.0]), 'SAT'));
    }

    public function test_sat_and_act_scores_are_never_converted(): void
    {
        $college = $this->fictionalCollege(['act_submitted_percent' => 7.0]);

        $fit = AcademicFit::for($college, $this->fictionalAthlete(['test_taken' => 'act', 'act_score' => 30]));

        $this->assertSame(AcademicFitStatus::NotCompared, $fit->status);
        $this->assertSame(["Too few students here submitted ACT scores (7%) to compare your score. We don't convert between SAT and ACT."], $fit->notes);
    }

    public function test_with_both_tests_it_uses_the_test_more_students_submitted(): void
    {
        $college = $this->fictionalCollege(['sat_submitted_percent' => 30.0, 'act_submitted_percent' => 45.0]);

        $fit = AcademicFit::for($college, $this->fictionalAthlete(['test_taken' => 'both', 'sat_score' => 1100, 'act_score' => 28]));

        $this->assertSame('ACT', $fit->test);
        $this->assertSame(AcademicFitStatus::WithinRange, $fit->status);
    }

    public function test_federal_scorecard_data_and_test_blind_colleges_are_never_compared(): void
    {
        $scorecard = $this->fictionalCollege([
            'admissions_data_source' => College::COLLEGE_SCORECARD,
            'sat_composite_25th' => null,
            'sat_composite_75th' => null,
            'sat_submitted_percent' => null,
            'act_submitted_percent' => null,
        ]);
        $testBlind = $this->fictionalCollege(['test_policy' => TestPolicy::NotConsidered]);
        $athlete = $this->fictionalAthlete(['test_taken' => 'both', 'sat_score' => 1300, 'act_score' => 28]);

        $this->assertNull(AcademicFit::for($scorecard, $athlete)->credit());
        $this->assertSame(["We don't compare your score because federal data doesn't show how many students submitted scores."], AcademicFit::for($scorecard, $athlete)->notes);
        $this->assertNull(AcademicFit::for($testBlind, $athlete)->credit());
    }

    public function test_academic_fit_changes_the_order_but_never_removes_a_college(): void
    {
        foreach ([1100, 1300, 1500] as $sat) {
            $this->fictionalCollege(['name' => "Fictional {$sat} College"])->save();
        }

        $withoutScores = app(CollegeMatcher::class)->match($this->fictionalAthlete(['priority' => 'academics']));
        $lowScore = app(CollegeMatcher::class)->match($this->fictionalAthlete(['test_taken' => 'sat', 'sat_score' => 400, 'priority' => 'academics']));

        $this->assertCount(3, $withoutScores->matches);
        $this->assertCount(3, $lowScore->matches);

        $lowScore->matches->each(function (CollegeMatch $match): void {
            $this->assertSame(AcademicFitStatus::BelowRange, $match->academicFit->status);
            $this->assertLessThan($match->preferenceScore, $match->score);
        });
    }

    public function test_a_low_score_never_drops_a_college_below_the_minimum_score(): void
    {
        $this->seed(CollegeSeeder::class);

        foreach (['division_1', 'division_2', 'division_3', 'open'] as $division) {
            foreach (['academics', 'affordability', 'balance'] as $priority) {
                $answers = ['division' => $division, 'priority' => $priority, 'budget' => 'under_15k', 'setting' => 'urban'];
                $without = app(CollegeMatcher::class)->match($this->fictionalAthlete($answers));
                $lowest = app(CollegeMatcher::class)->match($this->fictionalAthlete([...$answers, 'test_taken' => 'both', 'sat_score' => 400, 'act_score' => 1]));

                $this->assertSame($without->matches->count(), $lowest->matches->count(), "{$division} / {$priority}");
                $this->assertSame(
                    $without->matches->concat($without->alsoFits)->count(),
                    $lowest->matches->concat($lowest->alsoFits)->count(),
                    "{$division} / {$priority}: every qualifying college stays available",
                );
            }
        }
    }

    public function test_colleges_past_the_top_five_stay_available_under_also_fits(): void
    {
        $this->seed(CollegeSeeder::class);

        $allShown = fn ($results): array => $results->matches->concat($results->alsoFits)
            ->map(fn (CollegeMatch $match): int => $match->college->ipeds_unit_id)
            ->sort()
            ->values()
            ->all();

        $withoutScore = app(CollegeMatcher::class)->match($this->fictionalAthlete(['division' => 'division_1', 'priority' => 'academics']));
        $sat1300 = app(CollegeMatcher::class)->match($this->fictionalAthlete(['division' => 'division_1', 'priority' => 'academics', 'test_taken' => 'sat', 'sat_score' => 1300]));

        $this->assertCount(CollegeMatcher::MAXIMUM_RESULTS, $sat1300->matches);
        $this->assertSame($allShown($withoutScore), $allShown($sat1300), 'A test score must never change which colleges are shown.');

        foreach ([131496 => 'Georgetown', 163286 => 'Maryland', 199120 => 'UNC'] as $id => $name) {
            $this->assertContains($id, $allShown($sat1300), "{$name} meets this athlete's preferences and must stay available.");
        }

        $sat1300->alsoFits->each(fn (CollegeMatch $match) => $this->assertGreaterThanOrEqual(CollegeMatcher::MINIMUM_SCORE, $match->preferenceScore));
    }

    public function test_gpa_never_changes_recommendations(): void
    {
        $this->seed(CollegeSeeder::class);

        $low = app(CollegeMatcher::class)->match($this->fictionalAthlete(['gpa' => '2.10', 'priority' => 'academics']));
        $high = app(CollegeMatcher::class)->match($this->fictionalAthlete(['gpa' => '4.00', 'priority' => 'academics']));

        $this->assertSame(
            $low->matches->map(fn (CollegeMatch $match): array => [$match->college->id, $match->score])->all(),
            $high->matches->map(fn (CollegeMatch $match): array => [$match->college->id, $match->score])->all(),
        );
    }

    public function test_seeded_academic_data_matches_the_approved_research(): void
    {
        $this->seed(CollegeSeeder::class);

        $this->assertSame(13, College::where('admissions_data_source', College::COMMON_DATA_SET)->count());
        $this->assertSame(12, College::where('admissions_data_source', College::COLLEGE_SCORECARD)->count());

        College::with('sources')->get()->each(function (College $college): void {
            $this->assertNotNull($college->sources->firstWhere('topic', 'admissions'), "{$college->name} needs an admissions source.");
            $this->assertNotNull($college->admission_rate, "{$college->name} needs an admission rate.");

            if (! $college->hasCommonDataSetAdmissions()) {
                $this->assertSame('Fall 2024', $college->admissions_term);
                $this->assertNull($college->average_high_school_gpa, 'Federal data has no GPA.');
                $this->assertFalse(AcademicFit::isReliable($college, 'SAT'));
                $this->assertFalse(AcademicFit::isReliable($college, 'ACT'));
            }
        });

        $skidmore = College::where('short_name', 'Skidmore')->sole();
        $this->assertSame(24.7, $skidmore->sat_submitted_percent);
        $this->assertFalse(AcademicFit::isReliable($skidmore, 'SAT'), 'Skidmore SAT is context only (171 of 692 students = 24.7%).');

        $georgetown = College::where('ipeds_unit_id', 131496)->sole();
        $this->assertSame(TestPolicy::Required, $georgetown->test_policy);
        $this->assertSame([1405, 1530], AcademicFit::range($georgetown, 'SAT'));
        $this->assertNull($georgetown->average_high_school_gpa);

        $calPolyPomona = College::where('ipeds_unit_id', 110529)->sole();
        $this->assertSame(TestPolicy::NotConsidered, $calPolyPomona->test_policy);
        $this->assertSame(3.7, $calPolyPomona->average_high_school_gpa);
    }
}
