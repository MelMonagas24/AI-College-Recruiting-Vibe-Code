@use('App\Recommendations\AffordabilityStatus')

@php
    $cost = $affordability->cost === null ? null : '$'.number_format($affordability->cost);
    $rate = $affordability->rate ? " ({$affordability->rate} rate)" : '';
    $collegeStateName = $options['states'][$college->state] ?? $college->state;
    $style = match ($affordability->status) {
        AffordabilityStatus::AboveBudget => 'over',
        AffordabilityStatus::WithinBudget => 'fits',
        default => 'neutral',
    };
@endphp

<section class="afford {{ $style }}" aria-label="Affordability">
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
                We could not verify this college's {{ $college->cost_academic_year ?? '2026-27' }} cost of attendance,
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

    @if ($affordability->cost !== null)
        <p class="fine">For a personal estimate after aid, use the Net Price Calculator on the college's financial aid website.</p>
    @endif
</section>
