@use('App\Recommendations\AffordabilityStatus')
@use('App\Recommendations\CostLabel')
@use('App\Recommendations\NetPriceComparison')

@php
    $cost = $affordability->cost === null ? null : '$'.number_format($affordability->cost);
    $rate = $affordability->rate ? " ({$affordability->rate} rate)" : '';
    $costYear = $college->cost_academic_year ?? '2026-27';
    $collegeStateName = $options['states'][$college->state] ?? $college->state;
    $netPriceYear = $college->average_net_price_year;
    $netPriceApplies = $affordability->netPrice === NetPriceComparison::Applies;
    $netPriceSource = $college->sources->firstWhere('topic', 'net_price');
    $style = match ($affordability->label) {
        CostLabel::WithinBudget => 'fits',
        CostLabel::PotentiallyAffordable => 'maybe',
        CostLabel::AboveBudget => 'over',
        default => 'neutral',
    };
@endphp

<section class="afford {{ $style }}" aria-label="Affordability">
    <p class="cost-label"><span class="pill cost-{{ $style }}">{{ $affordability->label->label() }}</span></p>

    <div class="cost-part">
        <p class="cost-year">Published cost, {{ $costYear }} (before financial aid)</p>
        @switch($affordability->status)
            @case(AffordabilityStatus::AboveBudget)
                <h3>Published cost is above your family's budget</h3>
                <p>
                    {{ $cost }} per year{{ $rate }} is about <strong>${{ number_format($affordability->gap) }} more</strong>
                    than the top of your family's estimated budget ({{ $budgetLabel }}).
                    Grants and scholarships could lower what you would actually pay, but we can't predict how much.
                </p>
                @break

            @case(AffordabilityStatus::MayExceedBudget)
                <h3>Open-ended budget: check this cost</h3>
                <p>
                    {{ $cost }} per year{{ $rate }}. You chose {{ $budgetLabel }}, an open-ended budget with no upper limit, so we can't say whether this cost is within your budget.
                    Your family will need to decide whether it is affordable. Financial aid could lower what you would actually pay.
                </p>
                @break

            @case(AffordabilityStatus::WithinBudget)
                <h3>Published cost is within your family's budget</h3>
                <p>
                    {{ $cost }} per year{{ $rate }} fits your family's estimated budget ({{ $budgetLabel }}) even before financial aid.
                    Your actual cost could still be different.
                </p>
                @break

            @case(AffordabilityStatus::NoBudget)
                <h3>Affordability not checked</h3>
                <p>
                    {{ $cost }} per year{{ $rate }}. You chose "Unsure" for your family's budget, so we did not compare this cost with a budget or confirm that it is affordable.
                    Financial aid could lower what you would actually pay.
                </p>
                @break

            @default
                <h3>Cost not verified</h3>
                <p>
                    We could not verify this college's {{ $costYear }} cost of attendance,
                    so we can't tell whether it fits your family's budget. We won't guess.
                </p>
        @endswitch

        @if ($affordability->cost !== null && $affordability->costIsComplete === false)
            <p class="fine">This published total leaves out some expenses, so the full cost is likely higher. {{ $college->cost_note }}</p>
        @endif

        @if ($affordability->rate === 'in-state')
            <p class="fine">In-state rate: you live in {{ $homeStateName }}, the same state as this public college. Each college decides who qualifies as an in-state resident.</p>
        @elseif ($affordability->rate === 'out-of-state')
            <p class="fine">Out-of-state rate: this public college is in {{ $collegeStateName }}, and you live in {{ $answers['home_state'] === 'outside_us' ? 'another country' : $homeStateName }}.</p>
        @endif
    </div>

    <div class="cost-part net-price">
        <p class="cost-year">Federal average net price{{ $netPriceYear ? ", {$netPriceYear}" : '' }} (after grants and scholarships)</p>
        @switch($affordability->netPrice)
            @case(NetPriceComparison::Applies)
                <p>
                    <strong>${{ number_format($college->average_net_price) }} per year</strong> on average.
                    @if ($affordability->label === CostLabel::PotentiallyAffordable)
                        This average is within your family's budget, so this college may be worth exploring after financial aid.
                    @elseif ($affordability->label === CostLabel::AboveBudget)
                        Even this average is above your family's budget.
                    @elseif ($affordability->label === CostLabel::NotCompared)
                        It was not compared with your budget.
                    @endif
                </p>
                <p class="fine">
                    This is the average for {{ $netPriceYear }} full-time first-year students who received federal financial aid{{ $college->is_public ? ' and paid in-state tuition' : '' }}.
                    It is not a personalized estimate or a scholarship guarantee. Students who did not receive federal aid are not included, and many families pay more than the average.
                </p>
                @break

            @case(NetPriceComparison::InStateOnly)
                <p>
                    Not compared for you. At public colleges, the federal average covers only students who pay in-state tuition,
                    and you would pay the out-of-state rate here.
                </p>
                @break

            @case(NetPriceComparison::UsAidRecipientsOnly)
                <p>
                    Not compared for you. The federal average covers students who received U.S. federal financial aid,
                    which students living outside the U.S. generally can't receive.
                </p>
                @break

            @default
                <p>Not available for this college.</p>
        @endswitch
    </div>

    <details class="cost-details">
        <summary>More cost details</summary>
        <ul>
            @if ($netPriceApplies && $college->average_net_price_high_income !== null)
                <li>
                    Families earning over $110,000 who received federal aid paid an average of
                    <strong>${{ number_format($college->average_net_price_high_income) }} per year</strong> ({{ $netPriceYear }}).
                    Families with higher incomes often pay more than the overall average.
                </li>
            @endif
            @if ($netPriceApplies && $college->average_net_price_students !== null)
                <li>The federal average is based on {{ number_format($college->average_net_price_students) }} first-year students who received federal aid.</li>
            @endif
            <li>
                For a personal estimate, use the college's own Net Price Calculator.
                @if ($college->net_price_calculator_url)
                    <a href="{{ $college->net_price_calculator_url }}" target="_blank" rel="noopener">Open {{ $college->short_name }}'s Net Price Calculator</a>
                    <span class="fine">(link the college reported to the U.S. Department of Education)</span>
                @else
                    You can find it on {{ $college->short_name }}'s financial aid website.
                @endif
            </li>
            @if ($netPriceSource)
                <li>Source: <a href="{{ $netPriceSource->url }}" target="_blank" rel="noopener">U.S. Department of Education College Scorecard ({{ $netPriceYear }} data)</a></li>
            @endif
        </ul>
    </details>
</section>
