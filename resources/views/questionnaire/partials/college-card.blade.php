@use('App\Recommendations\AffordabilityStatus')
@use('App\Recommendations\NetPriceComparison')

@php
    $tradeoffCount = count($match->tradeoffs);
    $categoryLabel = $match->isStrongMatch() ? 'Strong Match' : ($tradeoffCount === 1 ? '1 tradeoff' : "{$tradeoffCount} tradeoffs");
    $categoryStyle = $match->isStrongMatch() ? 'strong' : 'tradeoff';
    $budgetPill = match (true) {
        ! $match->isStrongMatch() => null,
        $answers['budget'] === 'unsure' => 'Budget not checked',
        $match->affordability->status === AffordabilityStatus::MayExceedBudget => 'Open-ended budget',
        default => null,
    };
    $majorLabel = $options['majors'][$answers['major']];
    [$majorFact, $majorStyle] = match (true) {
        isset($match->matchedOn['major']) => ['Offered', 'yes'],
        isset($match->tradeoffs['major']) => ['Not confirmed', 'no'],
        default => ['Not checked', ''],
    };
    $netPriceFact = match ($match->affordability->netPrice) {
        NetPriceComparison::Applies => '$'.number_format($match->college->average_net_price).' average',
        NetPriceComparison::InStateOnly => 'In-state students only',
        NetPriceComparison::UsAidRecipientsOnly => 'Not compared',
        default => 'Not available',
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
                <span class="pill division">NCAA {{ $divisionNames[$match->college->ncaa_division] }}</span>
                <span>{{ $match->college->city }}, {{ $match->college->state }}</span>
            </span>
        </summary>

        @include('questionnaire.partials.quick-facts')
        <div class="match-body">
            @include('questionnaire.partials.match-details')
        </div>
    </details>
@else
    <article class="card match">
        <header class="match-header">
            <h2>{{ $match->college->name }}</h2>
            <p class="location">{{ $match->college->city }}, {{ $match->college->state }}@if ($match->college->conference) · {{ $match->college->conference }}@endif</p>
            <p class="meta">
                <span class="pill {{ $categoryStyle }}">{{ $categoryLabel }}</span>
                @if ($budgetPill)
                    <span class="pill unchecked">{{ $budgetPill }}</span>
                @endif
                <span class="pill division">NCAA {{ $divisionNames[$match->college->ncaa_division] }}</span>
            </p>
        </header>

        @include('questionnaire.partials.quick-facts')
        <div class="match-body">
            @include('questionnaire.partials.match-details')
        </div>
    </article>
@endif
