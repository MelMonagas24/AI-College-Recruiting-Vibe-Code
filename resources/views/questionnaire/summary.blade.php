@extends('layouts.app')

@section('title', 'Your Answers')

@section('content')
    <h1>Thanks! Here's your athlete profile</h1>
    <p class="intro">Take a look and make sure everything looks right.</p>

    <div class="demo-banner" role="note">
        <strong>Demo only:</strong> this profile is for fictional athletes. Your answers are shown here once and are not saved anywhere.
    </div>

    <div class="card">
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
                <dd>
                    {{ $league->label() }}
                    <small>Competitive grouping: {{ $league->tier()->label() }} (an approximate grouping, not an official ranking or a prediction of which division you qualify for)</small>
                </dd>
            </div>
            <div>
                <dt>GPA</dt>
                <dd>{{ number_format((float) $answers['gpa'], 2) }}</dd>
            </div>
            <div>
                <dt>SAT / ACT</dt>
                <dd>
                    @if (! empty($answers['sat_score']) || ! empty($answers['act_score']))
                        @if (! empty($answers['sat_score']))
                            SAT {{ $answers['sat_score'] }}
                        @endif
                        @if (! empty($answers['sat_score']) && ! empty($answers['act_score']))
                            ·
                        @endif
                        @if (! empty($answers['act_score']))
                            ACT {{ $answers['act_score'] }}
                        @endif
                    @else
                        No scores entered
                    @endif
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
    </div>

    <div class="card">
        <p><strong>College matches are coming soon.</strong> We're building a verified list of real women's soccer programs first, so every recommendation is based on accurate information.</p>
    </div>

    <div class="actions">
        <a class="button" href="{{ route('questionnaire.create') }}">Start Over</a>
        <a class="button secondary" href="{{ url('/') }}">Back to Home</a>
    </div>
@endsection
