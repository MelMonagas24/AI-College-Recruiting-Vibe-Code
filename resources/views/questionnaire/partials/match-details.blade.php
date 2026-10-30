@php
    $college = $match->college;
    $explanation = $isTopMatch ? $explanations->for($college->id) : null;
    $costSource = $college->sources->firstWhere('topic', 'costs');
    $athleticsSource = $college->sources->firstWhere('topic', 'athletics');
@endphp

<section class="why">
    <h3>Why it fits</h3>
    @if ($explanation !== null)
        <p><span class="badge ai">AI-generated</span> {{ $explanation }}</p>
        <p class="fine">Written by Claude using only the verified facts below. Double-check anything important with the college.</p>
    @elseif ($isTopMatch)
        <p><span class="badge off">Personalized explanation unavailable</span></p>
    @else
        <p>This college meets your preferences. The verified facts below show how it fits. Personalized AI explanations are written for your top matches only.</p>
    @endif
</section>

@include('questionnaire.partials.affordability', ['affordability' => $match->affordability])

@include('questionnaire.partials.academic')

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
