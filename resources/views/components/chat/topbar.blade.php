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
    </style>
@endonce

<header class="relative z-40 p-3 border-b backdrop-blur flex items-center justify-between gap-3"
    style="border-color: var(--border-muted); background: var(--surface)">
    <div class="flex items-center gap-2">
        <button id="sidebarToggle" class="rounded-md px-2 py-1 hover:brightness-110"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Toggle sidebar">
            <i id="sidebarIcon" class="fa-solid fa-chevron-left"></i>
        </button>
    </div>

    <div class="absolute left-1/2 -translate-x-1/2 flex justify-center">
        <a href="{{ url('/search') }}"
            class="chat-search-button rounded-full px-4 py-2 flex items-center gap-2 text-sm font-medium hover:brightness-110 transition pointer-events-auto"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Open search page">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span>Search in Archive</span>
        </a>
    </div>

    <div class="flex items-center gap-2">
        <label class="text-sm" style="color: color-mix(in srgb, var(--text) 70%, transparent)">Model</label>
        <select id="modelSelect"
            class="rounded-md px-3 py-1 text-sm outline-none focus:ring-1 focus:ring-[var(--accent)]"
            style="background: var(--surface); border: 1px solid var(--border-muted); color: var(--text);"></select>
        <button id="themeToggle" class="rounded-md px-2 py-1 hover:brightness-110"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Toggle theme">
            <i id="themeIcon" class="fa-solid fa-moon"></i>
        </button>
        <div class="relative">
            <button id="userBtn" class="rounded-md px-2 py-1 hover:brightness-110"
                style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
                title="Account">
                <i class="fa-regular fa-user"></i>
            </button>
            <div id="userMenu"
                class="hidden absolute right-0 mt-2 w-56 rounded-md border backdrop-blur shadow-lg py-1 text-sm z-50"
                style="border-color: var(--border-muted); background: var(--bg);"></div>
        </div>
    </div>
</header>