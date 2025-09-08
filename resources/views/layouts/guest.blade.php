@props(['wide' => false, 'hideLogo' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --bg-light: #FFFFFF; --text-light: #2E2E2E; --accent-light: #3A4D7A;
                --bg-dark: #121212; --text-dark: #E0E0E0; --accent-dark: #5C7AEA;
                --bg: var(--bg-light); --text: var(--text-light); --accent: var(--accent-light);
                --border-muted: rgba(46,46,46,0.12); --surface: rgba(0,0,0,0.04);
            }
            html.dark { --bg: var(--bg-dark); --text: var(--text-dark); --accent: var(--accent-dark); --border-muted: rgba(224,224,224,0.12); --surface: rgba(255,255,255,0.06); }
            body { background: var(--bg); color: var(--text); }
            .glass-panel { background: var(--surface); backdrop-filter: saturate(120%) blur(8px); border: 1px solid var(--border-muted); }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-10 sm:pt-0" style="background: var(--bg); color: var(--text)">
            @unless($hideLogo)
                <div>
                    <a href="/" class="inline-flex items-center gap-3">
                        <img src="/logo.svg" alt="Logo" class="h-14 w-14 object-contain" />
                    </a>
                </div>
            @endunless

            <div class="w-full {{ $wide ? 'max-w-7xl px-4 sm:px-6 lg:px-8' : 'sm:max-w-md px-6' }} mt-6 py-6 shadow-md overflow-hidden sm:rounded-2xl glass-panel">
                {{ $slot }}
            </div>
        </div>
    </body>
    </html>
