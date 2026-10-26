<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f7f7fb;
            color: #1f2937;
            text-align: center;
        }
        main { max-width: 640px; }
        h1 { font-size: clamp(2rem, 6vw, 3rem); color: #3b2a8f; margin-bottom: 16px; }
        p.description { font-size: 1.15rem; line-height: 1.6; margin-bottom: 32px; }
        a.get-started {
            display: inline-block;
            padding: 14px 32px;
            border-radius: 999px;
            background: #3b2a8f;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-decoration: none;
        }
        a.get-started:hover { background: #2e2170; }
        a.get-started:focus-visible { outline: 3px solid #a78bfa; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <h1>AI College Recruiting</h1>
        <p class="description">Helping female high school athletes find colleges that fit their athletic, academic, financial, and personal goals.</p>
        <a class="get-started" href="{{ route('questionnaire.create') }}">Get Started</a>
    </main>
</body>
</html>
