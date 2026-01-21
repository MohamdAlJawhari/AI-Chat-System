@once
    <style>
        .chat-search-button {
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .chat-search-button::after {
            content: '';
            position: absolute;
            inset: -120%;
            background: radial-gradient(circle,
                    rgba(0, 102, 255, 0.7) 0%,
                    /* vivid blue center */
                    rgba(92, 122, 234, 0.5) 40%,
                    /* soft fade */
                    rgba(92, 122, 234, 0) 80%
                    /* transparent edge */
                );
            opacity: 0;
            transform: scale(0.3);
            animation: chat-search-wave 3s ease-out infinite;
            z-index: 0;
        }

        .chat-search-button>* {
            position: relative;
            z-index: 1;
        }

        @keyframes chat-search-wave {
            0% {
                opacity: 0.8;
                transform: scale(0.35);
            }

            60% {
                opacity: 0.4;
                transform: scale(1.15);
            }

            100% {
                opacity: 0;
                transform: scale(1.15);
            }
        }

        .chat-control-panel summary::-webkit-details-marker {
            display: none;
        }

        .chat-control-card {
            background: radial-gradient(circle at 20% 20%, rgba(125, 145, 255, 0.14), transparent 45%),
                        radial-gradient(circle at 80% 10%, rgba(88, 127, 255, 0.18), transparent 40%),
                        rgba(12, 18, 34, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(12px);
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 150ms ease, transform 150ms ease;
            pointer-events: none;
        }

        .chat-control-panel[open] .chat-control-card {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .chat-control-card input[type="text"],
        .chat-control-card select {
            background: rgba(10, 15, 28, 0.82);
            border-color: rgba(255, 255, 255, 0.08);
            color: var(--text);
        }

        .chat-control-card input[type="range"]::-webkit-slider-thumb {
            background: #7b8dff;
            border: 1px solid #a8b5ff;
            height: 14px;
            width: 14px;
            border-radius: 9999px;
            box-shadow: 0 0 0 6px rgba(123, 141, 255, 0.18);
        }

        .chat-control-card input[type="range"]::-webkit-slider-runnable-track {
            background: linear-gradient(90deg, rgba(123, 141, 255, 0.8), rgba(255, 255, 255, 0.2));
            height: 4px;
            border-radius: 9999px;
        }

        .advanced-list summary {
            list-style: none;
        }

        .info-chip {
            height: 18px;
            width: 18px;
            border-radius: 9999px;
            border: 1px solid rgba(220, 228, 255, 0.45);
            color: rgba(220, 228, 255, 0.9);
            background: rgba(255, 255, 255, 0.06);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 120ms ease;
            position: relative;
        }

        .info-chip:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 6px rgba(123, 141, 255, 0.15);
        }

        .info-chip + .info-tooltip {
            opacity: 0;
            transform: translateY(-4px);
            transition: opacity 120ms ease, transform 120ms ease;
            pointer-events: none;
        }

        .info-chip:focus + .info-tooltip,
        .info-chip:hover + .info-tooltip {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .info-tooltip {
            position: absolute;
            right: 0;
            top: 10%;
            z-index: 10;
            width: 240px;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 12px;
            line-height: 1.5;
            color: rgba(235, 240, 255, 0.92);
            background: rgba(12, 18, 34, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
            text-transform: none ;
        }

        /* Keep top-row controls vertically centered and aligned */
        .chat-top-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .chat-top-controls select,
        .chat-top-controls button,
        .chat-top-controls label {
            align-self: center;
        }
        .chat-top-archive {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
    </style>
@endonce

@php
    $personaOptions = config('llm.personas.allowed', []);
    $defaultPersona = config('llm.default_persona', 'assistant');
    $personaLabels = [
        'auto' => 'Auto (AI picks)',
        'assistant' => 'Assistant - مساعد',
        'author' => 'Author - كاتب',
        'reporter' => 'Reporter - مراسل',
        'summarizer' => 'Summarizer - المختصر',
    ];
@endphp

<header class="relative z-40 p-3 border-b backdrop-blur flex items-center justify-between gap-3"
    style="border-color: var(--border-muted); background: var(--surface)">
    <div class="flex items-center gap-2">
        <button id="sidebarToggle" class="rounded-md px-2 py-1 hover:brightness-110"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Toggle sidebar">
            <i id="sidebarIcon" class="fa-solid fa-chevron-left"></i>
        </button>
    </div>

    <div class="flex-1 flex justify-center">
        <form method="get" action="{{ route('search') }}" class="hidden xl:flex items-center gap-4 relative">
            <a href="/search"
            class="chat-search-button rounded-full px-4 py-2 flex items-center gap-2 text-sm font-medium hover:brightness-110 transition pointer-events-auto"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Open search page with filters - فتح صفحة البحث مع عوامل التصفية">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Search in Archive</span>
            </a>

        </form>

        <a href="{{ url('/search') }}"
            class="chat-search-button rounded-full px-4 py-2 flex items-center gap-2 text-sm font-medium hover:brightness-110 transition pointer-events-auto xl:hidden"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Open search page">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span>Search in Archive</span>
        </a>
    </div>

    <div class="chat-top-controls">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm" style="color: color-mix(in srgb, var(--text) 70%, transparent)">Persona</label>
                <select id="personaSelect" data-default-persona="{{ $defaultPersona }}"
                    class="rounded-md px-3 py-1 text-sm outline-none focus:ring-1 focus:ring-[var(--accent)]"
                    style="background: var(--surface); border: 1px solid var(--border-muted); color: var(--text);">
                    @foreach($personaOptions as $persona)
                        <option value="{{ $persona }}" @if($persona === $defaultPersona) selected @endif>
                            {{ $personaLabels[$persona] ?? ucfirst(str_replace('_', ' ', $persona)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <label class="text-sm" style="color: color-mix(in srgb, var(--text) 70%, transparent)">Model</label>
            <select id="modelSelect"
                class="rounded-md px-3 py-1 text-sm outline-none focus:ring-1 focus:ring-[var(--accent)]"
                style="background: var(--surface); border: 1px solid var(--border-muted); color: var(--text);"></select>
            <button id="themeToggle" class="rounded-md px-2 py-1 hover:brightness-110"
                style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
                title="Toggle theme - تبديل السمة">
                <i id="themeIcon" class="fa-solid fa-moon"></i>
            </button>
            <div class="relative">
                <button id="userBtn" class="rounded-md px-2 py-1 hover:brightness-110"
                    style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
                    title="Account - الحساب">
                    <i class="fa-regular fa-user"></i>
                </button>
                <div id="userMenu"
                    class="hidden absolute right-0 mt-2 w-56 rounded-md border backdrop-blur shadow-lg py-1 text-sm z-50"
                    style="border-color: var(--border-muted); background: var(--bg);"></div>
            </div>
            <button id="archiveDrawerToggle" class="rounded-md px-2 py-1 hover:brightness-110"
                style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
                title="Toggle archive drawer">
                <i id="archiveDrawerIcon" class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</header>
