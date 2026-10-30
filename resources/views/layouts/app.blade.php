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
        .notice {
            padding: 14px 16px;
            margin-bottom: 16px;
            border-radius: 12px;
            background: #fff8e6;
            border: 1px solid #f3d27a;
            color: #5b4100;
        }
        .notice ul, .card ul { margin: 8px 0 0 20px; display: grid; gap: 4px; }
        .match header { margin-bottom: 14px; }
        .match h2 { font-size: 1.3rem; color: #3b2a8f; line-height: 1.25; margin-bottom: 6px; }
        .match h3, .card h3 { font-size: 0.95rem; margin: 14px 0 4px; color: #1f2937; }
        .meta { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; color: #4b5563; }
        .pill { padding: 2px 10px; border-radius: 999px; background: #efecfb; color: #3b2a8f; font-weight: 700; font-size: 0.85rem; }
        .badge { display: inline-block; padding: 2px 8px; margin-right: 4px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.03em; vertical-align: 1px; }
        .badge.ai { background: #3b2a8f; color: #fff; }
        .badge.off { background: #f3f4f6; color: #4b5563; border: 1px dashed #9ca3af; }
        .fine { font-size: 0.85rem; font-weight: 400; color: #4b5563; }
        .checks li::marker { content: "✓  "; color: #15803d; }
        .facts { margin-top: 16px; padding-top: 4px; border-top: 1px solid #e5e7eb; }
        .facts dd { font-weight: 600; }
        .sources { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 12px; font-size: 0.9rem; color: #4b5563; }
        .notice.warn { background: #fff4ed; border-color: #f5b48a; color: #6b2d0b; }
        .afford { margin-top: 14px; padding: 12px 14px; border-radius: 12px; border: 1px solid #e5e7eb; background: #f9fafb; display: grid; gap: 6px; }
        .afford h3 { margin: 0; }
        .afford.over { background: #fff4ed; border-color: #f5b48a; }
        .afford.over h3 { color: #9a3412; }
        .afford.fits { background: #f0fdf4; border-color: #86efac; }
        .afford.fits h3 { color: #166534; }
        .afford.neutral h3 { color: #374151; }
        .academic { margin-top: 14px; padding: 12px 14px; border-radius: 12px; border: 1px solid #ddd6fe; background: #f5f3ff; display: grid; gap: 6px; }
        .academic h3 { margin: 0; color: #3b2a8f; }
        .academic p { margin: 0; }
        .academic .fit { font-weight: 600; }
        .academic .fine { display: block; margin-top: 2px; }
        .also-fits { margin: 28px 0 16px; }
        .also-fits > h2 { color: #3b2a8f; font-size: 1.35rem; margin-bottom: 6px; }
        .also-fits > p { margin-bottom: 8px; }
        .also-fits > .fine { margin-bottom: 14px; }
        details.also { padding: 0; border-color: #ddd6fe; }
        details.also > summary { cursor: pointer; padding: 16px 20px; display: grid; gap: 6px; list-style: none; }
        details.also > summary::-webkit-details-marker { display: none; }
        details.also > summary::after { content: "See details ▾"; font-size: 0.9rem; font-weight: 600; color: #3b2a8f; }
        details.also[open] > summary::after { content: "Hide details ▴"; }
        details.also > summary:focus-visible { outline: 3px solid #3b2a8f; outline-offset: 2px; border-radius: 16px; }
        .also-name { font-size: 1.15rem; font-weight: 700; color: #1f2937; }
        .also-body { padding: 0 20px 20px; }
        .answers summary { cursor: pointer; font-weight: 650; color: #3b2a8f; }
        .answers dl { margin-top: 8px; }
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
