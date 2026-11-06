@php
    $tradeoffCount = count($match->tradeoffs);
    $categoryLabel = $match->isStrongMatch() ? 'Strong Match' : ($tradeoffCount === 1 ? '1 tradeoff' : "{$tradeoffCount} tradeoffs");
    $categoryStyle = $match->isStrongMatch() ? 'strong' : 'tradeoff';
    $budgetPill = match (true) {
        ! $match->isStrongMatch() => null,
        $answers['budget'] === 'unsure' => 'Budget not checked',
        $match->affordability->status === \App\Recommendations\AffordabilityStatus::MayExceedBudget => 'Open-ended budget',
        default => null,
    };
@endphp

@if ($expandable)
    <details class="card match also">
        <summary>
            <span class="also-name">{{ $match->college->name }}</span>
            <span class="meta">
                <span class="pill {{ $categoryStyle }}">{{ $categoryLabel }}</span>
                @if ($budgetPill)
                    <span class="pill unchecked">{{ $budgetPill }}</span>
                @endif
                <span class="pill">NCAA {{ $divisionNames[$match->college->ncaa_division] }}</span>
                <span>{{ $match->college->city }}, {{ $match->college->state }}</span>
            </span>
        </summary>

        <div class="also-body">
            @include('questionnaire.partials.match-details')
        </div>
    </details>
@else
    <article class="card match">
        <header>
            <h2>{{ $match->college->name }}</h2>
            <p class="meta">
                <span class="pill {{ $categoryStyle }}">{{ $categoryLabel }}</span>
                @if ($budgetPill)
                    <span class="pill unchecked">{{ $budgetPill }}</span>
                @endif
                <span class="pill">NCAA {{ $divisionNames[$match->college->ncaa_division] }}</span>
                @if ($match->college->conference)
                    <span>{{ $match->college->conference }}</span>
                @endif
                <span>{{ $match->college->city }}, {{ $match->college->state }}</span>
            </p>
        </header>

        @include('questionnaire.partials.match-details')
    </article>
@endif
