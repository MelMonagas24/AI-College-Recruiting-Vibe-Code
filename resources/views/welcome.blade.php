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
        p.coming-soon {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 999px;
            background: #3b2a8f;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <main>
        <h1>AI College Recruiting</h1>
        <p class="description">Helping female high school athletes find colleges that fit their athletic, academic, financial, and personal goals.</p>
        <p class="coming-soon">Coming Soon</p>
    </main>
</body>
</html>
