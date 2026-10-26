<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            padding: 24px 16px 48px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f7f7fb;
            color: #1f2937;
            line-height: 1.5;
        }
        main { max-width: 720px; margin: 0 auto; }
        a { color: #3b2a8f; }
        .brand {
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 700;
            color: #3b2a8f;
            text-decoration: none;
        }
        h1 { font-size: clamp(1.75rem, 5vw, 2.5rem); color: #3b2a8f; line-height: 1.2; margin-bottom: 12px; }
        .intro { font-size: 1.05rem; margin-bottom: 20px; }
        .demo-banner {
            padding: 14px 16px;
            margin-bottom: 24px;
            border: 2px dashed #3b2a8f;
            border-radius: 12px;
            background: #efecfb;
            font-size: 0.95rem;
        }
        .demo-banner strong { color: #3b2a8f; }
        .errors {
            padding: 14px 16px;
            margin-bottom: 24px;
            border-radius: 12px;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        .errors ul { margin: 6px 0 0 20px; }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }
        fieldset { border: 0; }
        legend, .question {
            display: block;
            font-weight: 650;
            font-size: 1.05rem;
            margin-bottom: 12px;
        }
        .step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            margin-right: 8px;
            border-radius: 999px;
            background: #3b2a8f;
            color: #fff;
            font-size: 0.85rem;
        }
        .hint { font-size: 0.9rem; color: #4b5563; margin: -4px 0 12px; }
        .field-error { font-size: 0.9rem; color: #b91c1c; margin-top: 8px; }
        .choices { display: flex; flex-wrap: wrap; gap: 10px; }
        .choice { position: relative; }
        .choice input { position: absolute; opacity: 0; inset: 0; }
        .choice span {
            display: inline-block;
            padding: 10px 16px;
            border: 2px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            cursor: pointer;
            font-weight: 500;
        }
        .choice input:checked + span { border-color: #3b2a8f; background: #3b2a8f; color: #fff; }
        .choice input:focus-visible + span { outline: 3px solid #a78bfa; outline-offset: 2px; }
        select, input[type="number"] {
            width: 100%;
            max-width: 360px;
            padding: 12px;
            border: 2px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            font: inherit;
        }
        select:focus, input[type="number"]:focus { outline: none; border-color: #3b2a8f; }
        .scores { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 16px; }
        .scores label { flex: 1 1 160px; font-weight: 500; }
        .scores input { margin-top: 6px; }
        .button {
            display: inline-block;
            padding: 14px 32px;
            border: 0;
            border-radius: 999px;
            background: #3b2a8f;
            color: #fff;
            font: inherit;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-decoration: none;
            cursor: pointer;
        }
        .button:hover { background: #2e2170; }
        .button.secondary { background: #fff; color: #3b2a8f; border: 2px solid #3b2a8f; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        dl { display: grid; grid-template-columns: minmax(0, 1fr); }
        dl div { padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
        dl div:last-child { border-bottom: 0; }
        dt { font-size: 0.9rem; color: #4b5563; }
        dd { font-weight: 600; }
        dd small { display: block; font-weight: 400; color: #4b5563; }
        @media (max-width: 480px) {
            .choice { flex: 1 1 100%; }
            .choice span { display: block; text-align: center; }
            .button { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <main>
        <a class="brand" href="{{ url('/') }}">AI College Recruiting</a>
        @yield('content')
    </main>
</body>
</html>
