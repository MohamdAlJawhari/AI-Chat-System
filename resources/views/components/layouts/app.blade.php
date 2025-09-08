<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-status" content="{{ auth()->check() ? 'auth' : 'guest' }}">
    @auth
        <meta name="auth-email" content="{{ auth()->user()->email }}">
        <meta name="auth-role" content="{{ auth()->user()->role }}">
    @else
        <meta name="auth-email" content="">
        <meta name="auth-role" content="">
    @endauth
    <title>{{ $title ?? config('app.name', 'UChat') }}</title>
    <!-- Tailwind (CDN for dev) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg: { DEFAULT: '#0b0b0d', soft: '#111113' },
                        panel: '#0f1012',
                    }
                }
            }
        }
    </script>
    <script>
        // Apply preferred theme before paint to avoid FOUC
        (function(){
            try{
                const t = localStorage.getItem('theme');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (t === 'dark' || (!t && prefersDark)) document.documentElement.classList.add('dark');
            }catch(e){}
        })();
    </script>
    <!-- Markdown renderer + sanitizer -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <!-- Mermaid (diagrams) -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>window.mermaid && mermaid.initialize({ startOnLoad: false, theme: 'dark' });</script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <!-- Favicon/logo -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <style id="three-body-style">
      /* From Uiverse.io by dovatgabriel (adapted + themed) */
      :root { --accent: #5D3FD3; }
      html.dark { --accent: #7C5CFF; }
      .three-body { --uib-size: 32px; --uib-speed: 0.8s; --uib-color: var(--accent); position: relative; display: inline-block; height: var(--uib-size); width: var(--uib-size); animation: spin78236 calc(var(--uib-speed) * 2.5) infinite linear; pointer-events: none; }
      .three-body__dot { position: absolute; height: 100%; width: 30%; }
      .three-body__dot:after { content: ''; position: absolute; height: 0%; width: 100%; padding-bottom: 100%; background-color: var(--uib-color); border-radius: 50%; }
      .three-body__dot:nth-child(1) { bottom: 5%; left: 0; transform: rotate(60deg); transform-origin: 50% 85%; }
      .three-body__dot:nth-child(1)::after { bottom: 0; left: 0; animation: wobble1 var(--uib-speed) infinite ease-in-out; animation-delay: calc(var(--uib-speed) * -0.3); }
      .three-body__dot:nth-child(2) { bottom: 5%; right: 0; transform: rotate(-60deg); transform-origin: 50% 85%; }
      .three-body__dot:nth-child(2)::after { bottom: 0; left: 0; animation: wobble1 var(--uib-speed) infinite calc(var(--uib-speed) * -0.15) ease-in-out; }
      .three-body__dot:nth-child(3) { bottom: -5%; left: 0; transform: translateX(116.666%); }
      .three-body__dot:nth-child(3)::after { top: 0; left: 0; animation: wobble2 var(--uib-speed) infinite ease-in-out; }
      .three-body-wrap { position: relative; display: inline-block; pointer-events: none; }
      .three-body-timer { position: absolute; left: -6px; bottom: -6px; font-size: 10px; line-height: 1; color: rgba(148,163,184,0.85); text-shadow: 0 1px 2px rgba(0,0,0,0.25); }
      @keyframes spin78236 { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
      @keyframes wobble1 { 0%, 100% { transform: translateY(0%) scale(1); opacity: 1; } 50% { transform: translateY(-66%) scale(0.65); opacity: 0.8; } }
      @keyframes wobble2 { 0%, 100% { transform: translateY(0%) scale(1); opacity: 1; } 50% { transform: translateY(66%) scale(0.65); opacity: 0.8; } }
      @media (prefers-reduced-motion: reduce) {
        .three-body { animation: none; }
        .three-body__dot:after { animation: none; }
      }
    </style>
    {{ $head ?? '' }}
    @stack('styles')
</head>
<body class="h-screen bg-white text-slate-900 dark:bg-bg dark:text-gray-100">
    {{ $slot }}
    {{ $scripts ?? '' }}
    @stack('scripts')
</body>
</html>
