@extends('layouts.app')

@section('title', 'Athlete Questionnaire')

@section('content')
    <div class="page-head">
        <p class="eyebrow">Athlete questionnaire</p>
        <h1>Let's get to know you</h1>
        <p class="intro">Answer 13 quick questions about your soccer, school, and what you want from college. It takes about 3 minutes.</p>
    </div>

    <div class="demo-banner" role="note">
        <p>
            <strong>Demo only:</strong> this questionnaire is for testing with <strong>fictional athlete profiles</strong>.
            Please don't enter real personal information. No account is needed, and your answers are not saved.
        </p>
    </div>

    @if ($errors->any())
        <div class="errors" role="alert">
            <strong>Please check the highlighted questions below.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <nav class="progress-panel" aria-label="Questionnaire progress">
        <div class="progress-top">
            <span class="progress-count"><span data-progress-count>0</span> of 13 answered</span>
            <span class="fine">Jump to a section</span>
        </div>
        <div class="progress-track" aria-hidden="true"><div class="progress-fill" data-progress-fill></div></div>
        <ul class="section-links">
            <li><a href="#section-soccer">1. Your soccer</a></li>
            <li><a href="#section-academics">2. Academics</a></li>
            <li><a href="#section-cost">3. Cost &amp; location</a></li>
            <li><a href="#section-campus">4. Campus fit</a></li>
        </ul>
    </nav>

    <form method="POST" action="{{ route('questionnaire.store') }}" novalidate>
        @csrf

        <section class="form-section" id="section-soccer" aria-labelledby="section-soccer-heading">
            <div class="form-section-head">
                <span class="form-section-icon" aria-hidden="true">1</span>
                <div>
                    <h2 id="section-soccer-heading">Your soccer</h2>
                    <p>Questions 1–5 of 13</p>
                </div>
            </div>

            <fieldset class="card question-card">
                <legend><span class="step">1</span>What sport do you want to play in college?</legend>
                <x-choice-group name="sport" :options="$options['sports']" default="womens_soccer" />
                <p class="hint after">More sports are coming soon.</p>
            </fieldset>

            <div class="card question-card">
                <label class="question" for="graduation_year"><span class="step">2</span>What year will you graduate high school?</label>
                <select id="graduation_year" name="graduation_year" required>
                    <option value="">Select a year</option>
                    @foreach ($graduationYears as $year)
                        <option value="{{ $year }}" @selected((int) old('graduation_year') === $year)>{{ $year }}</option>
                    @endforeach
                </select>
                @error('graduation_year')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="card question-card">
                <legend><span class="step">3</span>What NCAA division are you interested in?</legend>
                <x-choice-group name="division" :options="$options['divisions']" />
            </fieldset>

            <fieldset class="card question-card">
                <legend><span class="step">4</span>What position do you play?</legend>
                <x-choice-group name="position" :options="$options['positions']" />
            </fieldset>

            <div class="card question-card">
                <label class="question" for="league"><span class="step">5</span>What is the highest level of soccer you have competed at?</label>
                <p class="hint">Choose the league you play in now or the highest one you've played in.</p>
                <select id="league" name="league" required>
                    <option value="">Select your league</option>
                    @foreach ($leagues as $league)
                        <option value="{{ $league->value }}" @selected(old('league') === $league->value)>{{ $league->label() }}</option>
                    @endforeach
                </select>
                @error('league')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <section class="form-section" id="section-academics" aria-labelledby="section-academics-heading">
            <div class="form-section-head">
                <span class="form-section-icon" aria-hidden="true">2</span>
                <div>
                    <h2 id="section-academics-heading">Academics</h2>
                    <p>Questions 6–8 of 13</p>
                </div>
            </div>

            <div class="card question-card">
                <label class="question" for="gpa"><span class="step">6</span>What is your GPA?</label>
                <p class="hint">Use a 4.0 scale (for example, 3.60).</p>
                <input id="gpa" name="gpa" type="number" inputmode="decimal" min="0" max="4" step="0.01" placeholder="3.60" value="{{ old('gpa') }}" required>
                @error('gpa')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="card question-card">
                <legend><span class="step">7</span>Have you taken the SAT or ACT?</legend>
                <p class="hint">Optional. Test scores are not required.</p>
                <x-choice-group name="test_taken" :options="$options['tests']" default="none" />
                <div class="scores">
                    <label data-score="sat">
                        SAT score (400–1600)
                        <input name="sat_score" type="number" inputmode="numeric" min="400" max="1600" step="10" placeholder="1200" value="{{ old('sat_score') }}">
                    </label>
                    <label data-score="act">
                        ACT score (1–36)
                        <input name="act_score" type="number" inputmode="numeric" min="1" max="36" step="1" placeholder="26" value="{{ old('act_score') }}">
                    </label>
                </div>
                @error('sat_score')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                @error('act_score')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <fieldset class="card question-card">
                <legend><span class="step">8</span>What are you interested in studying?</legend>
                <x-choice-group name="major" :options="$options['majors']" />
            </fieldset>
        </section>

        <section class="form-section" id="section-cost" aria-labelledby="section-cost-heading">
            <div class="form-section-head">
                <span class="form-section-icon" aria-hidden="true">3</span>
                <div>
                    <h2 id="section-cost-heading">Cost &amp; location</h2>
                    <p>Questions 9–10 of 13</p>
                </div>
            </div>

            <fieldset class="card question-card">
                <legend><span class="step">9</span>What is your family's estimated annual college budget?</legend>
                <p class="hint">This is the estimated amount your family could pay each year <strong>after</strong> grants and scholarships.</p>
                <x-choice-group name="budget" :options="$options['budgets']" />
            </fieldset>

            <div class="card question-card">
                <label class="question" for="home_state"><span class="step">10</span>What U.S. state do you live in?</label>
                <p class="hint">Public colleges charge less to students from their own state. We use this only to show in-state or out-of-state prices. For this demo, pick the state of your fictional athlete.</p>
                <select id="home_state" name="home_state" required>
                    <option value="">Select a state</option>
                    @foreach ($options['states'] as $code => $state)
                        <option value="{{ $code }}" @selected(old('home_state') === $code)>{{ $state }}</option>
                    @endforeach
                </select>
                @error('home_state')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <section class="form-section" id="section-campus" aria-labelledby="section-campus-heading">
            <div class="form-section-head">
                <span class="form-section-icon" aria-hidden="true">4</span>
                <div>
                    <h2 id="section-campus-heading">Campus fit</h2>
                    <p>Questions 11–13 of 13</p>
                </div>
            </div>

            <fieldset class="card question-card">
                <legend><span class="step">11</span>What type of college environment do you prefer?</legend>
                <x-choice-group name="setting" :options="$options['settings']" />
            </fieldset>

            <fieldset class="card question-card">
                <legend><span class="step">12</span>What size college do you prefer?</legend>
                <x-choice-group name="size" :options="$options['sizes']" />
            </fieldset>

            <fieldset class="card question-card">
                <legend><span class="step">13</span>What matters most to you when choosing a college?</legend>
                <x-choice-group name="priority" :options="$options['priorities']" />
            </fieldset>
        </section>

        <div class="form-submit">
            <p>You'll see your Strong Matches and Other Options to Consider right away. Nothing is saved.</p>
            <button type="submit" class="button large">Review My Answers</button>
        </div>
    </form>

    <script>
        (function () {
            const form = document.querySelector('form[action$="/questionnaire"]');
            const radios = document.querySelectorAll('input[name="test_taken"]');
            const fields = document.querySelectorAll('[data-score]');
            const radioQuestions = ['sport', 'division', 'position', 'test_taken', 'major', 'budget', 'setting', 'size', 'priority'];
            const fieldQuestions = ['graduation_year', 'league', 'gpa', 'home_state'];
            const count = document.querySelector('[data-progress-count]');
            const fill = document.querySelector('[data-progress-fill]');

            function updateScores() {
                const selected = document.querySelector('input[name="test_taken"]:checked');
                const choice = selected ? selected.value : 'none';
                fields.forEach(function (field) {
                    const visible = choice === 'both' || choice === field.dataset.score;
                    field.hidden = !visible;
                    field.querySelector('input').disabled = !visible;
                });
            }

            function updateProgress() {
                let answered = 0;
                radioQuestions.forEach(function (name) {
                    if (form.querySelector('input[name="' + name + '"]:checked')) { answered++; }
                });
                fieldQuestions.forEach(function (name) {
                    if (form.elements[name] && form.elements[name].value.trim() !== '') { answered++; }
                });
                count.textContent = answered;
                fill.style.width = Math.round(answered / 13 * 100) + '%';
            }

            radios.forEach(function (radio) { radio.addEventListener('change', updateScores); });
            form.addEventListener('change', updateProgress);
            form.addEventListener('input', updateProgress);
            updateScores();
            updateProgress();
        })();
    </script>
@endsection
