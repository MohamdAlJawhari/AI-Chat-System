<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased auth-guest">
        <style>
            :root {
                --guest-bg-light: #f6f8fc;
                --guest-bg-accent: rgba(92, 122, 234, 0.12);
                --guest-bg-dark: #0f172a;
                --guest-bg-dark-accent: rgba(92, 122, 234, 0.25);
                --guest-card-border: rgba(148, 163, 184, 0.25);
                --guest-text-light: #1f2937;
                --guest-text-dark: #f8fafc;
                --surface: rgba(255, 255, 255, 0.94);
                --border-muted: var(--guest-card-border);
                --text: var(--guest-text-light);
                --accent: #3a4d7a;
            }

            body.auth-guest {
                --surface: rgba(255, 255, 255, 0.94);
                --border-muted: var(--guest-card-border);
                --text: var(--guest-text-light);
                --accent: #3a4d7a;
                background: radial-gradient(1100px 620px at 50% 0%, var(--guest-bg-accent), transparent 70%), var(--guest-bg-light);
                color: #1f2937;
                min-height: 100vh;
            }

            html.dark body.auth-guest {
                --surface: rgba(15, 23, 42, 0.85);
                --border-muted: rgba(148, 163, 184, 0.25);
                --text: var(--guest-text-dark);
                --accent: #5c7aea;
                background: radial-gradient(1100px 620px at 50% 0%, var(--guest-bg-dark-accent), transparent 70%), var(--guest-bg-dark);
                color: #e2e8f0;
            }

            body.auth-guest a {
                color: #fdfdfdff;
            }

            body.auth-guest a:hover {
                color: #b7d7fbff;
            }

            html.dark body.auth-guest a {
                color: #93c5fd;
            }

            .auth-card {
                background: var(--surface);
                border: 1px solid var(--guest-card-border);
                backdrop-filter: blur(16px);
                box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
                color: inherit;
            }

            html.dark .auth-card {
                background: var(--surface);
                box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.65);
            }

            .auth-muted {
                color: color-mix(in srgb, var(--text) 70%, transparent);
            }
        </style>
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="mb-8">
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-slate-500" />
                </a>
            </div>

            <div class="auth-card w-full sm:max-w-md mt-0 px-6 py-6 sm:px-8 sm:py-8 rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
