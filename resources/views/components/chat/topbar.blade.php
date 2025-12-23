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
    </style>
@endonce

@php
    $defaultAlpha = number_format((float) config('rag.alpha', 0.80), 2, '.', '');
    $defaultBeta = number_format((float) config('rag.beta', 0.20), 2, '.', '');
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

    <div class="absolute left-1/2 -translate-x-1/2 flex justify-center">
        <form method="get" action="{{ route('search') }}" class="hidden xl:flex items-center gap-6 relative">
            <details class="chat-control-panel relative" closed>
                <summary
                    class="cursor-pointer rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-muted hover:text-white transition"
                    style="border-color: var(--border-muted); background: color-mix(in srgb, var(--surface) 85%, transparent);">
                    Filters & weights
                </summary>
                <div class="chat-control-card absolute left-0 top-[115%] mt-2 w-[420px] rounded-2xl px-5 py-5 space-y-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--text)">Filter dataset</p>
                            <p class="text-xs text-muted">Narrow the corpus before the hybrid search runs.</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                            style="border-color: rgba(123,141,255,0.3); color: rgba(215,223,255,0.92); background: rgba(123,141,255,0.12);">
                            Pre-search
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Category</span>
                            <input type="text" name="category" placeholder="e.g. Politics"
                                class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" />
                        </label>
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Country</span>
                            <input type="text" name="country" placeholder="e.g. Lebanon"
                                class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" />
                        </label>
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-[11px] uppercase tracking-[0.18em] text-muted">City</span>
                            <input type="text" name="city" placeholder="e.g. Beirut"
                                class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" />
                        </label>
                        <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                            <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Breaking news</span>
                            <select name="is_breaking_news"
                                class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent">
                                <option value="">Include all</option>
                                <option value="1">Breaking only</option>
                                <option value="0">Exclude breaking</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--text)">Advanced weighting</p>
                            <p class="text-xs text-muted">Semantic vec vs. keywords & doc blending.</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                            style="border-color: rgba(123,141,255,0.3); color: rgba(215,223,255,0.92); background: rgba(123,141,255,0.12);">
                            Alpha / Beta
                        </span>
                    </div>
                    
                    <div class="rounded-xl border px-4 py-3 space-y-3" style="border-color: rgba(255,255,255,0.08); background: rgba(7, 12, 24, 0.55);">
                        <details class="advanced-list" closed>
                            {{-- <summary class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--text)">Advanced weighting</p>
                                    <p class="text-xs text-muted">Balance semantic vectors vs. keywords and doc blending.</p>
                                </div>
                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    style="border-color: rgba(123,141,255,0.3); color: rgba(215,223,255,0.92); background: rgba(123,141,255,0.12);">
                                    Alpha / Beta
                                </span>
                            </summary> --}}

                            <div class="mt-4 space-y-4">
                                <div class="space-y-2 relative">
                                    <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.18em] text-muted">
                                        <span class="flex items-center gap-2">
                                            Alpha (semantic weight)
                                            <button type="button" class="info-chip" aria-label="What is alpha?">?</button>
                                            <div class="info-tooltip">Alpha balances semantic (embeddings) vs. lexical (keywords). Higher alpha favors semantic similarity; lower alpha leans on keyword ranking.</div>
                                        </span>
                                        <span>{{ $defaultAlpha }}</span>
                                    </div>
                                    <input type="range" min="0" max="1" step="0.05" name="alpha" value="{{ $defaultAlpha }}"
                                        class="w-full accent-[var(--accent)]">
                                    <div class="flex justify-between text-[11px] uppercase tracking-[0.18em] text-muted">
                                        <span>Lexical</span>
                                        <span>Semantic</span>
                                    </div>
                                    <p class="text-xs text-muted">Raise to lean on semantic matches; lower to favor keyword ranking.</p>
                                </div>

                                <div class="space-y-2 relative">
                                    <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.18em] text-muted">
                                        <span class="flex items-center gap-2">
                                            Beta (doc blend)
                                            <button type="button" class="info-chip" aria-label="What is beta?">?</button>
                                            <div class="info-tooltip">Beta controls how much the average chunk score matters versus the best chunk. Higher beta rewards documents with consistent relevance.</div>
                                        </span>
                                        <span>{{ $defaultBeta }}</span>
                                    </div>
                                    <input type="range" min="0" max="1" step="0.05" name="beta" value="{{ $defaultBeta }}"
                                        class="w-full accent-[var(--accent)]">
                                    <div class="flex justify-between text-[11px] uppercase tracking-[0.18em] text-muted">
                                        <span>Best chunk</span>
                                        <span>Avg chunks</span>
                                    </div>
                                    <p class="text-xs text-muted">Higher beta rewards documents with consistent relevance across chunks.</p>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </details>

            <button type="submit"
                class="chat-search-button rounded-full px-4 py-2 flex items-center gap-2 text-sm font-medium hover:brightness-110 transition pointer-events-auto"
                style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
                title="Open search page with filters">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Search in Archive</span>
            </button>
        </form>

        <a href="{{ url('/search') }}"
            class="chat-search-button rounded-full px-4 py-2 flex items-center gap-2 text-sm font-medium hover:brightness-110 transition pointer-events-auto xl:hidden"
            style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted);"
            title="Open search page">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span>Search in Archive</span>
        </a>
    </div>

    <div class="flex items-center gap-4">
        <div class="flex items-center gap-3 pr-4 border-r" style="border-color: var(--border-muted);">
            <div class="flex flex-col leading-tight">
                <span class="text-[11px] uppercase tracking-[0.35em] text-muted">Archive</span>
                <span id="archiveModeBadge" class="text-xs font-semibold" style="color: var(--text);">Off</span>
            </div>
            <label class="relative inline-flex cursor-pointer items-center" title="When enabled, answers cite the newsroom archive">
                <input type="checkbox" id="archiveToggle" class="sr-only peer">
                <span class="h-6 w-11 rounded-full border transition peer-checked:bg-emerald-400/70 peer-checked:border-emerald-300/70"
                    style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.25);"></span>
                <span
                    class="pointer-events-none absolute left-0.5 top-1/2 h-5 w-5 -translate-y-1/2 rounded-full bg-white shadow transition peer-checked:translate-x-5 peer-checked:bg-white/90"></span>
            </label>
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
    </div>
</header>
