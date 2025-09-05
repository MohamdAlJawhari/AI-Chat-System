<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-status" content="{{ auth()->check() ? 'auth' : 'guest' }}">
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
    {{ $head ?? '' }}
    @stack('styles')
</head>
<body class="h-screen bg-white text-slate-900 dark:bg-bg dark:text-gray-100">
    {{ $slot }}
    {{ $scripts ?? '' }}
    @stack('scripts')
</body>
</html>
