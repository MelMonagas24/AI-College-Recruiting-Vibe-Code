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
        @if ($results->all()->isEmpty())
            We couldn't find a college in our database that fits your answers well enough to recommend.
        @else
            Based on your answers, here {{ $results->all()->count() === 1 ? 'is 1 college' : 'are '.$results->all()->count().' colleges' }} from our verified database of women's soccer programs, in two groups.
        @endif
    </p>

    @if ($results->all()->isNotEmpty())
        <p class="summary">
            <a href="#strong-heading">{{ $results->strongMatches->count() }} Strong {{ $results->strongMatches->count() === 1 ? 'Match' : 'Matches' }}</a>
            <span aria-hidden="true">·</span>
            <a href="#other-heading">{{ $results->otherOptions->count() }} Other {{ $results->otherOptions->count() === 1 ? 'Option' : 'Options' }} to Consider</a>
        </p>
        @if ($uncheckedCriteria !== [])
            <p class="fine summary-note">
                You chose {{ implode(' and ', array_map(fn (string $criterion): string => ['major' => '"'.$options['majors'][$answers['major']].'" for your major', 'size' => '"No preference" for size', 'budget' => '"Unsure" for budget'][$criterion], $uncheckedCriteria)) }},
                so {{ count($uncheckedCriteria) === 1 ? 'that was' : 'those were' }} not checked. More colleges can be Strong Matches as a result.
            </p>
        @endif
    @endif

    <div class="demo-banner" role="note">
        <strong>Demo only:</strong> these results are for fictional athlete profiles. Your answers are used only to build this page and are not saved.
    </div>

    @if ($explanations->status !== ExplanationStatus::Generated)
        <div class="notice" role="status">
            <strong>{{ $explanations->status === ExplanationStatus::Partial ? 'Some personalized explanations are unavailable.' : 'Personalized explanations are unavailable.' }}</strong>
            {{ $explanations->message }}
        </div>
    @endif

    @if ($results->all()->isNotEmpty() && $answers['budget'] !== 'unsure' && ($results->aboveBudgetCount() > 0 || $results->unknownCostCount() > 0))
        <div class="notice warn" role="status">
            <strong>Affordability check</strong>
            <ul>
                @if ($results->aboveBudgetCount() > 0)
                    <li>{{ $results->aboveBudgetCount() }} of {{ $results->all()->count() }} {{ $results->all()->count() === 1 ? 'college has' : 'colleges have' }} a published cost above your family's estimated budget ({{ $budgetLabel }} per year). {{ $results->aboveBudgetCount() === 1 ? 'It is' : 'They are' }} listed under Other Options to Consider.</li>
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
            <strong>Why you're seeing {{ $results->all()->isEmpty() ? 'no matches' : 'fewer than 3 colleges' }}</strong>
            <ul>
                @foreach ($results->shortfallReasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($results->all()->isNotEmpty())
        <section class="category" aria-labelledby="strong-heading">
            <h2 id="strong-heading">Strong Matches</h2>

            @if ($results->hasStrongMatches())
                <p>
                    @if ($answers['budget'] === 'unsure')
                        These colleges meet your division, major, and size, based on our verified data.
                        <strong>Affordability has not been checked or confirmed</strong>, because you chose "Unsure" for your family's budget. Check each college's published cost.
                    @elseif ($answers['budget'] === '45k_plus')
                        These colleges meet your division, major, and size, based on our verified data. You chose an open-ended budget ($45,000+), so any verified published cost counts for the budget check.
                        That doesn't mean every cost is affordable: each card shows the actual published cost so your family can decide.
                    @else
                        These colleges meet your division, major, size, and budget, based on our verified data.
                    @endif
                </p>

                @foreach ($results->featuredStrongMatches() as $match)
                    @include('questionnaire.partials.college-card', ['expandable' => false])
                @endforeach

                @if ($results->moreStrongMatches()->isNotEmpty())
                    <div class="more" aria-labelledby="more-strong-heading">
                        <h3 id="more-strong-heading">More strong matches ({{ $results->moreStrongMatches()->count() }})</h3>
                        <p class="fine">
                            {{ $results->moreStrongMatches()->count() === 1 ? 'This college also meets' : 'These colleges also meet' }} all of your key criteria.
                            {{ $results->moreStrongMatches()->count() === 1 ? 'It is' : 'They are' }} listed here only because we show five full cards at a time. Select any college to see its full details.
                        </p>

                        @foreach ($results->moreStrongMatches() as $match)
                            @include('questionnaire.partials.college-card', ['expandable' => true])
                        @endforeach
                    </div>
                @endif
            @else
                <div class="notice" role="status">
                    <strong>No Strong Matches</strong>
                    <p>
                        No college in our database meets all of your key criteria (division, major, size, and budget).
                        The colleges below are the closest alternatives. Each one lists exactly what doesn't match, so please don't read any of them as a perfect fit.
                    </p>
                </div>
            @endif
        </section>
    @endif

    @if ($results->otherOptions->isNotEmpty())
        <section class="category" aria-labelledby="other-heading">
            <h2 id="other-heading">Other Options to Consider</h2>
            <p>
                These colleges are in your division and could still be worth exploring, but each one has at least one tradeoff, listed on its card.
                Colleges with the fewest tradeoffs are listed first. A published cost above your budget doesn't rule a college out: financial aid could lower what you would actually pay.
            </p>

            @foreach ($results->featuredOtherOptions() as $match)
                @include('questionnaire.partials.college-card', ['expandable' => false])
            @endforeach

            @if ($results->moreOtherOptions()->isNotEmpty())
                <div class="more" aria-labelledby="more-other-heading">
                    <h3 id="more-other-heading">More options to consider ({{ $results->moreOtherOptions()->count() }})</h3>
                    <p class="fine">Each of these has its tradeoffs listed too. Select any college to see its full details.</p>

                    @foreach ($results->moreOtherOptions() as $match)
                        @include('questionnaire.partials.college-card', ['expandable' => true])
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="card">
        <h3>How these matches were chosen</h3>
        <ul>
            <li>Only colleges with a verified active women's soccer program and a confirmed NCAA division can be recommended.</li>
            <li>Colleges are filtered by your division choice and your planned major. Colleges known not to offer your major are removed.</li>
            <li><strong>Strong Matches</strong> meet your major, size, and budget, each confirmed by verified data. A college we can't confirm offers your major, or whose cost or size isn't verified, is never called a Strong Match. A "$45,000+" budget has no upper limit, so any verified published cost counts for the budget check, but the card shows the actual cost and your family decides whether it is affordable. If you chose "Unsure" for budget, affordability is not checked at all, and a Strong Match says nothing about cost.</li>
            <li><strong>Other Options to Consider</strong> miss or can't confirm at least one of those criteria, and every tradeoff is listed on the card. They are sorted by fewest tradeoffs first.</li>
            <li>Within each group, colleges are ranked by budget, campus environment, size, merit scholarships, academic fit, and what matters most to you. Affordability counts the most. Campus environment only changes the order; it never moves a college between groups.</li>
            <li>Published costs are prices before grants and scholarships. Your family's actual cost will likely be different. Use each college's Net Price Calculator for a personal estimate.</li>
            <li>Public colleges show their in-state price only if you live in the same state ({{ $homeStateName }}); otherwise they show the out-of-state price. Each college decides who qualifies as an in-state resident.</li>
            <li><strong>Academic fit:</strong> When reliable data exists, we compare your SAT or ACT score with the middle 50% of first-year students who submitted that test. A score within or above the range earns full credit, and a score below it still earns most of the credit. Academic fit can change the order of your matches but never removes a college or moves it between groups, and a score below a college's typical range is not a reason to skip it: colleges admit students outside that range and consider much more than test scores. Every college that qualifies is shown, either as a full card or in an expandable list. If you didn't enter a score, or a college doesn't have reliable data, test scores don't affect that college. GPA is shown for context only.</li>
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
