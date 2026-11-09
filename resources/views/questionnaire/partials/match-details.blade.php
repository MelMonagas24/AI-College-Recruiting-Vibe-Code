@php
    $college = $match->college;
    $isExplained = $results->isExplained($match);
    $explanation = $isExplained ? $explanations->for($college->id) : null;
    $costSource = $college->sources->firstWhere('topic', 'costs');
    $athleticsSource = $college->sources->firstWhere('topic', 'athletics');
@endphp

<section class="why {{ $explanation !== null ? 'has-ai' : 'no-ai' }}">
    <div class="why-head">
        <h3>{{ $match->isStrongMatch() ? 'Why it fits' : 'How it compares' }}</h3>
        @if ($explanation !== null)
            <span class="badge ai">AI-generated</span>
        @elseif ($isExplained)
            <span class="badge off">Personalized explanation unavailable</span>
        @endif
    </div>
    @if ($explanation !== null)
        <p class="why-text">{{ $explanation }}</p>
        <p class="fine">Written by Claude using only the verified facts below. Double-check anything important with the college.</p>
    @elseif ($isExplained)
        <p>
            An AI explanation isn't available for this college right now, so none is shown.
            {{ $match->isStrongMatch() ? 'The verified facts below show how it fits.' : 'The tradeoffs and verified facts below show how it compares.' }}
        </p>
    @else
        <p>
            {{ $match->isStrongMatch() ? 'This college meets all of your key criteria. The verified facts below show how it fits.' : 'The tradeoffs and verified facts below show how this college compares with what you are looking for.' }}
            Personalized AI explanations are written for the first five colleges on this page only.
        </p>
    @endif
</section>

@if (! $match->isStrongMatch())
    <section class="tradeoffs" aria-label="Tradeoffs">
        <h3>{{ count($match->tradeoffs) === 1 ? 'Tradeoff to consider' : 'Tradeoffs to consider' }}</h3>
        <ul>
            @foreach ($match->tradeoffs as $tradeoff)
                <li>{{ $tradeoff }}</li>
            @endforeach
        </ul>
        @if (! $match->meetsMinimumScore())
            <p class="fine">This college meets fewer than half of your preferences. It is shown so you have at least three alternatives to compare.</p>
        @endif
    </section>
@endif

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

@include('questionnaire.partials.affordability', ['affordability' => $match->affordability])

@include('questionnaire.partials.academic')

@if ($match->notes !== [])
    <section>
        <h3>Also note</h3>
        <ul>
            @foreach ($match->notes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
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

<section>
    <h3>Verified college facts</h3>
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
</section>

<p class="sources">
    <strong>Sources:</strong>
    @if ($athleticsSource)<a href="{{ $athleticsSource->url }}" target="_blank" rel="noopener">Women's soccer ({{ $athleticsSource->data_period }})</a>@endif
    @if ($costSource)<a href="{{ $costSource->url }}" target="_blank" rel="noopener">Costs ({{ $costSource->data_period }})</a>@endif
</p>
