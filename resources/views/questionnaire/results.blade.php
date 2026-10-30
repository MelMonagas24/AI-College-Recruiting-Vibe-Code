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
        <article class="card match">
            <header>
                <h2>{{ $match->college->name }}</h2>
                <p class="meta">
                    <span class="pill">NCAA {{ $divisionNames[$match->college->ncaa_division] }}</span>
                    @if ($match->college->conference)
                        <span>{{ $match->college->conference }}</span>
                    @endif
                    <span>{{ $match->college->city }}, {{ $match->college->state }}</span>
                </p>
            </header>

            @include('questionnaire.partials.match-details', ['isTopMatch' => true])
        </article>
    @endforeach

    @if ($results->alsoFits->isNotEmpty())
        <section class="also-fits" aria-labelledby="also-fits-heading">
            <h2 id="also-fits-heading">Also fits your preferences</h2>
            <p>
                {{ $results->alsoFits->count() === 1 ? 'This college also meets' : 'These '.$results->alsoFits->count().' colleges also meet' }}
                what you're looking for. {{ $results->alsoFits->count() === 1 ? 'It is a valid option' : 'They are valid options' }}, listed here only because we show five top matches at a time.
            </p>
            <p class="fine">
                Test scores can change the order of colleges, but a score below a college's typical range is not a reason to skip it.
                Colleges admit students outside that range and consider much more than test scores. Select any college to see its full details.
            </p>

            @foreach ($results->alsoFits as $match)
                <details class="card match also">
                    <summary>
                        <span class="also-name">{{ $match->college->name }}</span>
                        <span class="meta">
                            <span class="pill">NCAA {{ $divisionNames[$match->college->ncaa_division] }}</span>
                            <span>{{ $match->college->city }}, {{ $match->college->state }}</span>
                        </span>
                    </summary>

                    <div class="also-body">
                        @include('questionnaire.partials.match-details', ['isTopMatch' => false])
                    </div>
                </details>
            @endforeach
        </section>
    @endif

    <div class="card">
        <h3>How these matches were chosen</h3>
        <ul>
            <li>Only colleges with a verified active women's soccer program and a confirmed NCAA division can be recommended.</li>
            <li>Colleges are filtered by your division choice and your planned major, then ranked by budget, campus environment, size, and what matters most to you. Affordability counts the most.</li>
            <li>Published costs are prices before grants and scholarships. Your family's actual cost will likely be different. Use each college's Net Price Calculator for a personal estimate.</li>
            <li>Public colleges show their in-state price only if you live in the same state ({{ $homeStateName }}); otherwise they show the out-of-state price. Each college decides who qualifies as an in-state resident.</li>
            <li><strong>Academic fit:</strong> When reliable data exists, we compare your SAT or ACT score with the middle 50% of first-year students who submitted that test. A score within or above the range earns full credit, and a score below it still earns most of the credit. Academic fit can change the order of your matches but never removes a college: every college that meets your preferences is shown, either as a top match or under "Also fits your preferences." If you didn't enter a score, or a college doesn't have reliable data, test scores don't affect that college. GPA is shown for context only.</li>
            <li>Your position and club league were not used to rank colleges, because our database has no roster data. Your club level ({{ $league->label() }}, approximate grouping: {{ $league->tier()->label() }}) does not decide which NCAA division you can play in.</li>
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
