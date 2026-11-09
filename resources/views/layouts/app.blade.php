<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    <meta name="description" content="Find women's college soccer programs that fit your division, major, size, and budget, using verified college data.">
    <meta name="theme-color" content="#3b1a85">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="container">
            <a class="brand" href="{{ url('/') }}" aria-label="AI College Recruiting home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8l3.8 2.76-1.45 4.48h-4.7L8.2 10.76z" fill="currentColor"/>
                        <path d="M12 8V2M15.8 10.76l5.7-1.86M14.35 15.24l3.55 4.86M9.65 15.24L6.1 20.1M8.2 10.76L2.5 8.9"/>
                    </svg>
                </span>
                <span>AI College <span class="brand-accent">Recruiting</span></span>
            </a>
            <nav class="site-nav" aria-label="Main">
                <a class="nav-text" href="{{ url('/') }}#how-it-works">How it works</a>
                <a class="nav-text" href="{{ url('/') }}#trust">Our data</a>
                <a class="button small" href="{{ route('questionnaire.create') }}" aria-label="Find my matches"><span class="label-long">Find my matches</span><span class="label-short">Get started</span></a>
            </nav>
        </div>
    </header>

    <main id="main-content" class="site-main @yield('main_class')">
        @hasSection('full_width')
            @yield('full_width')
        @else
            <div class="container @yield('container_class', 'narrow')">
                @yield('content')
            </div>
        @endif
    </main>

    <footer class="site-footer">
        <div class="container">
            <div>
                <a class="brand" href="{{ url('/') }}">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8l3.8 2.76-1.45 4.48h-4.7L8.2 10.76z" fill="currentColor"/>
                            <path d="M12 8V2M15.8 10.76l5.7-1.86M14.35 15.24l3.55 4.86M9.65 15.24L6.1 20.1M8.2 10.76L2.5 8.9"/>
                        </svg>
                    </span>
                    <span>AI College <span class="brand-accent">Recruiting</span></span>
                </a>
                <p>Helping female high school soccer players find college programs that fit their athletic, academic, financial, and personal goals.</p>
            </div>
            <div>
                <h2>Explore</h2>
                <ul>
                    <li><a href="{{ route('questionnaire.create') }}">Athlete questionnaire</a></li>
                    <li><a href="{{ url('/') }}#how-it-works">How it works</a></li>
                    <li><a href="{{ url('/') }}#trust">Where our data comes from</a></li>
                </ul>
            </div>
            <div>
                <h2>Data sources</h2>
                <ul>
                    <li>Official college athletics websites</li>
                    <li>College cost of attendance pages</li>
                    <li><a href="https://collegescorecard.ed.gov/" target="_blank" rel="noopener">U.S. Department of Education College Scorecard</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>Demo project for fictional athlete profiles only. Answers are never saved. Not affiliated with the NCAA or any college. Results do not guarantee admission, scholarships, roster spots, or recruitment.</p>
            </div>
        </div>
    </footer>
</body>
</html>
