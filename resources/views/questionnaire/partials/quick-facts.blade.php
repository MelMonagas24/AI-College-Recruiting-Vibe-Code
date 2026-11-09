<dl class="quick-facts" aria-label="Key facts">
    <div>
        <dt>Division</dt>
        <dd>NCAA {{ $divisionNames[$match->college->ncaa_division] }}
            @if ($match->college->conference)<small>{{ $match->college->conference }}</small>@endif
        </dd>
    </div>
    <div>
        <dt>Your major</dt>
        <dd class="{{ $majorStyle }}">{{ $majorFact }}<small>{{ $majorLabel }}</small></dd>
    </div>
    <div>
        <dt>Size</dt>
        <dd>
            @if ($match->college->undergraduate_enrollment)
                {{ $match->college->size->label() }}
                <small>{{ number_format($match->college->undergraduate_enrollment) }} undergraduates</small>
            @else
                Not verified
            @endif
        </dd>
    </div>
    <div>
        <dt>Published cost</dt>
        <dd>
            @if ($match->affordability->cost === null)
                Not verified
            @else
                ${{ number_format($match->affordability->cost) }}
            @endif
            <small>per year, before aid{{ $match->affordability->rate ? " ({$match->affordability->rate})" : '' }}</small>
        </dd>
    </div>
    <div>
        <dt>Avg. net price</dt>
        <dd>{{ $netPriceFact }}<small>after grants, federal data</small></dd>
    </div>
</dl>
