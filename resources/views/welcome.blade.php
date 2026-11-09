@extends('layouts.app')

@section('main_class', 'flush')

@section('full_width')
    <section class="hero" aria-labelledby="hero-heading">
        <div class="container">
            <div>
                <p class="eyebrow">Women's college soccer</p>
                <h1 id="hero-heading">Find the college soccer program that <em>fits you.</em></h1>
                <p class="hero-lead">
                    Answer 13 quick questions about your game, your grades, your budget, and the campus you want.
                    We match you with NCAA women's soccer programs using verified college data, and explain every result honestly.
                </p>
                <div class="hero-actions">
                    <a class="button on-dark large" href="{{ route('questionnaire.create') }}">Get Started</a>
                    <a class="button ghost-on-dark large" href="#how-it-works">How it works</a>
                </div>
                <p class="hero-note">Free · No account · About 3 minutes · Answers are never saved</p>
            </div>

            <aside class="hero-panel" aria-label="What your results include">
                <h2>What your results show</h2>
                <p>Every college card is built from verified facts, so you can compare programs side by side.</p>
                <div class="sample-card">
                    <p class="sample-name">Strong Matches</p>
                    <div class="sample-meta">
                        <span class="pill strong">Division</span>
                        <span class="pill strong">Major</span>
                        <span class="pill strong">Size</span>
                        <span class="pill strong">Budget</span>
                    </div>
                </div>
                <div class="sample-card">
                    <p class="sample-name">Other Options to Consider</p>
                    <div class="sample-meta">
                        <span class="pill tradeoff">Tradeoffs listed</span>
                        <span class="pill cost-maybe">Potentially affordable after aid</span>
                    </div>
                </div>
                <div class="sample-card">
                    <p class="sample-name">On every card</p>
                    <div class="sample-meta">
                        <span class="pill division">NCAA division</span>
                        <span class="pill">Published cost</span>
                        <span class="pill">Average net price</span>
                        <span class="pill soft">AI explanation</span>
                    </div>
                </div>
                <p class="fine">Labels shown here are examples of the result layout, not real results.</p>
            </aside>
        </div>
    </section>

    <div class="container">
        <div class="stats">
            <div class="stat-tile"><strong>Division I, II &amp; III</strong><span>Only colleges with an active women's soccer program</span></div>
            <div class="stat-tile"><strong>13 questions</strong><span>About 3 minutes, no account needed</span></div>
            <div class="stat-tile"><strong>Real costs</strong><span>Published cost and federal average net price</span></div>
            <div class="stat-tile"><strong>Fact-checked AI</strong><span>Explanations checked against verified data</span></div>
        </div>
    </div>

    <section class="section" id="how-it-works" aria-labelledby="how-heading">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">How it works</p>
                <h2 id="how-heading">From questionnaire to college list in three steps</h2>
                <p>No guesswork and no hype. You see exactly why each college made your list, and what doesn't fit.</p>
            </div>
            <ol class="steps">
                <li class="step-card">
                    <span class="step-number" aria-hidden="true">1</span>
                    <h3>Tell us about you</h3>
                    <p>Your preferred NCAA division, position, club league, GPA, planned major, family budget, home state, and the campus size and setting you want.</p>
                </li>
                <li class="step-card">
                    <span class="step-number" aria-hidden="true">2</span>
                    <h3>We match with verified data</h3>
                    <p>Colleges are filtered by division and major, then checked against your size and budget. Your home state decides whether in-state prices apply.</p>
                </li>
                <li class="step-card">
                    <span class="step-number" aria-hidden="true">3</span>
                    <h3>Compare honest results</h3>
                    <p><strong>Strong Matches</strong> meet all of your key criteria. <strong>Other Options to Consider</strong> list every tradeoff, so you can decide what matters.</p>
                </li>
            </ol>
        </div>
    </section>

    <section class="section alt" id="trust" aria-labelledby="trust-heading">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Built on trust</p>
                <h2 id="trust-heading">Verified data, honest costs, careful AI</h2>
                <p>Recruiting decisions are big decisions. Every number on your results page comes from a cited source.</p>
            </div>
            <div class="features">
                <div class="feature">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
                    </span>
                    <h3>Verified college data</h3>
                    <p>Soccer programs and divisions come from official athletics websites. Majors, enrollment, and admissions data come from the U.S. Department of Education. Each card links to its sources.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg>
                    </span>
                    <h3>Honest about cost</h3>
                    <p>See each college's published cost before aid next to the federal average net price after aid. If a cost can't be verified, we say so instead of guessing.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 12h4"/></svg>
                    </span>
                    <h3>AI with guardrails</h3>
                    <p>Claude writes short explanations using only verified facts. Each one is checked automatically, and it never discusses cost, predicts admission, or promises a roster spot.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section closing" aria-labelledby="cta-heading">
        <div class="container">
            <div class="cta-band">
                <div>
                    <h2 id="cta-heading">Ready to find your college fit?</h2>
                    <p>This demo is for fictional athlete profiles. Please don't enter real personal information.</p>
                </div>
                <a class="button on-dark large" href="{{ route('questionnaire.create') }}">Start the questionnaire</a>
            </div>
        </div>
    </section>
@endsection
