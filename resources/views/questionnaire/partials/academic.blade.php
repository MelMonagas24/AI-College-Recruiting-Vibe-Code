@use('App\Enums\TestPolicy')
@use('App\Recommendations\AcademicFit')
@use('App\Recommendations\AcademicFitStatus')

@php
    $fromCommonDataSet = $college->hasCommonDataSetAdmissions();
    $admissionsSource = $college->sources->firstWhere('topic', 'admissions');
    $ranges = [];

    if ($fromCommonDataSet) {
        foreach (['SAT', 'ACT'] as $test) {
            [$low, $high] = AcademicFit::range($college, $test);
            if ($low !== null) {
                $ranges[] = "{$test} {$low}–{$high} (".AcademicFit::percent(AcademicFit::submittedPercent($college, $test))." of students submitted {$test} scores)";
            }
        }
    } else {
        if ($college->sat_reading_writing_25th !== null) {
            $ranges[] = "SAT Reading and Writing {$college->sat_reading_writing_25th}–{$college->sat_reading_writing_75th}";
        }
        if ($college->sat_math_25th !== null) {
            $ranges[] = "SAT Math {$college->sat_math_25th}–{$college->sat_math_75th}";
        }
        if ($college->act_composite_25th !== null) {
            $ranges[] = "ACT {$college->act_composite_25th}–{$college->act_composite_75th}";
        }
    }
@endphp

<section class="academic" aria-label="Academic context">
    <h3>Academic context</h3>

    @if ($college->admissions_data_source === null)
        <p>We don't have verified admissions data for this college yet.</p>
    @else
        <p>
            @if ($fromCommonDataSet)
                <strong>SAT/ACT policy for {{ $college->test_policy_term }}:</strong> {{ $college->test_policy->description() }}
                @if ($college->test_policy_note)
                    <span class="fine">{{ $college->test_policy_note }}</span>
                @endif
            @else
                <strong>SAT/ACT policy (federal data, {{ $college->test_policy_term }}):</strong> {{ $college->test_policy->federalLabel() }}.
                Policies change, so check the college's admissions website.
            @endif
        </p>

        @if ($college->test_policy !== TestPolicy::NotConsidered)
            @if ($ranges !== [])
                <p>{{ $fromCommonDataSet ? "Middle 50% of {$college->admissions_term} first-year students who submitted scores:" : "Federal College Scorecard data ({$college->admissions_term} first-year students), middle 50%:" }} {{ implode(' · ', $ranges) }}.</p>
            @elseif (! $fromCommonDataSet)
                <p>Federal data doesn't include SAT or ACT ranges for this college.</p>
            @endif
        @endif

        @foreach ($match->academicFit->notes as $note)
            <p class="{{ $match->academicFit->status === AcademicFitStatus::NotCompared ? '' : 'fit' }}">{{ $note }}</p>
        @endforeach

        <p>
            @if ($college->average_high_school_gpa !== null)
                <strong>Average high school GPA</strong> of {{ $college->admissions_term }} first-year students: {{ number_format($college->average_high_school_gpa, 2) }}
                (reported by {{ AcademicFit::percent($college->gpa_submitted_percent) }} of them).
                <span class="fine">Colleges calculate GPA differently, often adding extra points for honors and AP classes, so this may not compare directly with your GPA. {{ $college->gpa_note }}</span>
            @elseif ($fromCommonDataSet)
                This college doesn't publish an average high school GPA.
            @else
                Average high school GPA isn't included in federal data.
            @endif
        </p>

        <p>
            <strong>Overall admission rate:</strong> {{ AcademicFit::percent($college->admission_rate) }} of all first-year applicants ({{ $college->admissions_term }}).
            <span class="fine">This describes all applicants, not your individual chances. The process for recruited athletes can differ.</span>
        </p>

        @if ($admissionsSource)
            <p class="fine">
                Source:
                <a href="{{ $admissionsSource->url }}" target="_blank" rel="noopener">{{ $fromCommonDataSet ? "the college's ".$admissionsSource->data_period : $admissionsSource->data_period }}</a>
            </p>
        @endif
    @endif
</section>
