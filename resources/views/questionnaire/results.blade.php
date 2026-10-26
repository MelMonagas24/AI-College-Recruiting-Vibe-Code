@extends('layouts.app')

@section('title', 'Your College Matches')

@use('App\Recommendations\ExplanationStatus')

@php
    $divisionNames = [1 => 'Division I', 2 => 'Division II', 3 => 'Division III'];
    $budgetLabel = $options['budgets'][$answers['budget']];
    $homeStateName = $options['states'][$answers['home_state']];
    $money = fn (?int $amount): ?string => $amount === null ? null : '$'.number_format($amount);
@endphp

@section('content')
    <h1>Your college matches</h1>
    <p class="intro">
        @if ($results->matches->isEmpty())
            We couldn't find a college in our database that fits your answers well enough to recommend.
        @else
            Based on your answers, here {{ $results->matches->count() === 1 ? 'is 1 college' : 'are '.$results->matches->count().' colleges' }} from our verified database of women's soccer programs.
        @endif
    </p>

    <div class="demo-banner" role="note">
        <strong>Demo only:</strong> these results are for fictional athlete profiles. Your answers are used only to build this page and are not saved.
    </div>

    @if ($explanations->status !== ExplanationStatus::Generated)
        <div class="notice" role="status">
            <strong>Personalized explanations are unavailable.</strong>
            {{ $explanations->message }}
        </div>
    @endif

    @if ($results->matches->isNotEmpty() && $answers['budget'] !== 'unsure' && ($results->aboveBudgetCount() > 0 || $results->unknownCostCount() > 0))
        <div class="notice warn" role="status">
            <strong>Affordability check</strong>
            <ul>
                @if ($results->aboveBudgetCount() > 0)
                    <li>{{ $results->aboveBudgetCount() }} of {{ $results->matches->count() }} {{ $results->matches->count() === 1 ? 'college has' : 'colleges have' }} a published cost above your family's estimated budget ({{ $budgetLabel }} per year).</li>
                @endif
                @if ($results->unknownCostCount() > 0)
                    <li>{{ $results->unknownCostCount() }} {{ $results->unknownCostCount() === 1 ? 'college has' : 'colleges have' }} a cost we could not verify, so we can't tell whether {{ $results->unknownCostCount() === 1 ? 'it fits' : 'they fit' }} your budget.</li>
                @endif
                <li>Published costs are before financial aid. Grants and scholarships could lower what your family actually pays, but we can't predict how much.</li>
            </ul>
        </div>
    @endif

    @if ($results->hasShortfall())
        <div class="notice" role="status">
            <strong>Why you're seeing {{ $results->matches->isEmpty() ? 'no matches' : 'fewer than 3 matches' }}</strong>
            <ul>
                @foreach ($results->shortfallReasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach ($results->matches as $match)
        @php
            $college = $match->college;
            $explanation = $explanations->for($college->id);
            $costSource = $college->sources->firstWhere('topic', 'costs');
            $athleticsSource = $college->sources->firstWhere('topic', 'athletics');
        @endphp

        <article class="card match">
            <header>
                <h2>{{ $college->name }}</h2>
                <p class="meta">
                    <span class="pill">NCAA {{ $divisionNames[$college->ncaa_division] }}</span>
                    @if ($college->conference)
                        <span>{{ $college->conference }}</span>
                    @endif
                    <span>{{ $college->city }}, {{ $college->state }}</span>
                </p>
            </header>

            <section class="why">
                <h3>Why it fits</h3>
                @if ($explanation !== null)
                    <p><span class="badge ai">AI-generated</span> {{ $explanation }}</p>
                    <p class="fine">Written by Claude using only the verified facts below. Double-check anything important with the college.</p>
                @else
                    <p><span class="badge off">Personalized explanation unavailable</span></p>
                @endif
            </section>

            @include('questionnaire.partials.affordability', ['affordability' => $match->affordability])

            @if ($match->matchedOn !== [])
                <section>
                    <h3>Matches your preferences <span class="fine">(from our verified data)</span></h3>
                    <ul class="checks">
                        @foreach ($match->matchedOn as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($match->tradeoff)
                <section>
                    <h3>Tradeoff to consider</h3>
                    <p>{{ $match->tradeoff }}</p>
                </section>
            @endif

            @if ($match->unknowns !== [])
                <section>
                    <h3>Not verified yet</h3>
                    <ul>
                        @foreach ($match->unknowns as $unknown)
                            <li>{{ $unknown }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <dl class="facts">
                <div>
                    <dt>Campus environment</dt>
                    <dd>{{ $college->campus_environment?->label() ?? 'Not verified' }}
                        @if ($college->federal_locale)<small>Federal location code: {{ $college->federal_locale }}</small>@endif
                    </dd>
                </div>
                <div>
                    <dt>Size</dt>
                    <dd>
                        @if ($college->undergraduate_enrollment)
                            {{ $college->size->label() }}
                            <small>{{ number_format($college->undergraduate_enrollment) }} undergraduates ({{ $college->enrollment_term }})</small>
                        @else
                            Not verified
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Published cost of attendance, {{ $college->cost_academic_year ?? '2026-27' }} (before financial aid)</dt>
                    <dd>
                        @if ($college->cost_of_attendance_in_state === null)
                            Not verified yet
                        @elseif ($college->cost_of_attendance_in_state === $college->cost_of_attendance_out_of_state)
                            {{ $money($college->cost_of_attendance_in_state) }} per year
                        @else
                            {{ $money($college->cost_of_attendance_in_state) }} in-state · {{ $money($college->cost_of_attendance_out_of_state) }} out-of-state
                        @endif
                        @if ($college->cost_of_attendance_is_complete === false)
                            <small>Does not include every expense: {{ $college->cost_note }}</small>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Merit scholarships</dt>
                    <dd>{{ $college->merit_aid->label() }}
                        @if ($college->ncaa_division === 3)<small>Division III schools do not offer athletic scholarships.</small>@endif
                    </dd>
                </div>
                <div>
                    <dt>Data status</dt>
                    <dd>{{ $college->verification_status->label() }} <small>Checked {{ $college->last_verified_on?->format('F j, Y') }}</small></dd>
                </div>
            </dl>

            <p class="sources">
                Sources:
                @if ($athleticsSource)<a href="{{ $athleticsSource->url }}" target="_blank" rel="noopener">Women's soccer ({{ $athleticsSource->data_period }})</a>@endif
                @if ($costSource)<a href="{{ $costSource->url }}" target="_blank" rel="noopener">Costs ({{ $costSource->data_period }})</a>@endif
            </p>
        </article>
    @endforeach

    <div class="card">
        <h3>How these matches were chosen</h3>
        <ul>
            <li>Only colleges with a verified active women's soccer program and a confirmed NCAA division can be recommended.</li>
            <li>Colleges are filtered by your division choice and your planned major, then ranked by budget, campus environment, size, and what matters most to you. Affordability counts the most.</li>
            <li>Published costs are prices before grants and scholarships. Your family's actual cost will likely be different. Use each college's Net Price Calculator for a personal estimate.</li>
            <li>Public colleges show their in-state price only if you live in the same state ({{ $homeStateName }}); otherwise they show the out-of-state price. Each college decides who qualifies as an in-state resident.</li>
            <li>Your GPA, test scores, position, and club league were not used to rank colleges, because our database has no admissions or roster data. Your club level ({{ $league->label() }}, approximate grouping: {{ $league->tier()->label() }}) does not decide which NCAA division you can play in.</li>
            <li>These results do not guarantee admission, scholarships, roster spots, or recruitment. Talk with college coaches and admissions offices.</li>
        </ul>
    </div>

    <details class="card answers">
        <summary>Your answers</summary>
        <dl>
            <div>
                <dt>Sport</dt>
                <dd>{{ $options['sports'][$answers['sport']] }}</dd>
            </div>
            <div>
                <dt>High school graduation year</dt>
                <dd>{{ $answers['graduation_year'] }}</dd>
            </div>
            <div>
                <dt>NCAA division interest</dt>
                <dd>{{ $options['divisions'][$answers['division']] }}</dd>
            </div>
            <div>
                <dt>Position</dt>
                <dd>{{ $options['positions'][$answers['position']] }}</dd>
            </div>
            <div>
                <dt>Highest level of soccer</dt>
                <dd>{{ $league->label() }} <small>Competitive grouping: {{ $league->tier()->label() }} (approximate, not an official ranking)</small></dd>
            </div>
            <div>
                <dt>GPA</dt>
                <dd>{{ number_format((float) $answers['gpa'], 2) }}</dd>
            </div>
            <div>
                <dt>SAT / ACT</dt>
                <dd>
                    @php
                        $scores = array_filter([
                            ! empty($answers['sat_score']) ? 'SAT '.$answers['sat_score'] : null,
                            ! empty($answers['act_score']) ? 'ACT '.$answers['act_score'] : null,
                        ]);
                    @endphp
                    {{ $scores === [] ? 'No scores entered' : implode(' · ', $scores) }}
                </dd>
            </div>
            <div>
                <dt>Interested in studying</dt>
                <dd>{{ $options['majors'][$answers['major']] }}</dd>
            </div>
            <div>
                <dt>Estimated annual family budget (after grants and scholarships)</dt>
                <dd>{{ $options['budgets'][$answers['budget']] }}</dd>
            </div>
            <div>
                <dt>Home state</dt>
                <dd>{{ $homeStateName }}</dd>
            </div>
            <div>
                <dt>Preferred environment</dt>
                <dd>{{ $options['settings'][$answers['setting']] }}</dd>
            </div>
            <div>
                <dt>Preferred college size</dt>
                <dd>{{ $options['sizes'][$answers['size']] }}</dd>
            </div>
            <div>
                <dt>What matters most</dt>
                <dd>{{ $options['priorities'][$answers['priority']] }}</dd>
            </div>
        </dl>
    </details>

    <div class="actions">
        <a class="button" href="{{ route('questionnaire.create') }}">Start Over</a>
        <a class="button secondary" href="{{ url('/') }}">Back to Home</a>
    </div>
@endsection
