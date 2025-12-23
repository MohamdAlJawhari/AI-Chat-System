@props([
    'q' => null,
    'results' => null,
    'limit' => 10,
    'pagination' => null,
    'alpha' => 0.80,
    'beta' => 0.20,
    'filters' => [],
])

@php
    $hasQuery = filled($q ?? null);
    $resultsList = $results ?? [];
    $resultCount = $hasQuery && is_countable($resultsList) ? count($resultsList) : 0;
    $limitValue = (int) ($limit ?? 10);
    $paginationData = is_array($pagination ?? null) ? $pagination : null;
    $paginatedMode = $hasQuery && $limitValue === 0 && !empty($paginationData);
    $currentPage = $paginatedMode ? max((int) ($paginationData['page'] ?? 1), 1) : 1;
    $batchSize = $paginatedMode ? max(1, (int) ($paginationData['batch'] ?? 1)) : null;
    $hasMoreResults = $paginatedMode ? (bool) ($paginationData['has_more'] ?? false) : false;
    $hasPreviousResults = $paginatedMode && $currentPage > 1;
    $rangeStart = $paginatedMode ? ($batchSize * ($currentPage - 1)) : 0;
    $rangeEnd = $paginatedMode ? $rangeStart + $resultCount : $resultCount;
    $paginationLinks = $paginatedMode
        ? [
            'prev' => request()->fullUrlWithQuery(['page' => max(1, $currentPage - 1), 'batch' => $batchSize]),
            'next' => request()->fullUrlWithQuery(['page' => $currentPage + 1, 'batch' => $batchSize]),
        ]
        : null;
    $alphaValue = max(0.0, min(1.0, (float) ($alpha ?? 0.80)));
    $betaValue = max(0.0, min(1.0, (float) ($beta ?? 0.20)));
    $filterValues = is_array($filters ?? null) ? $filters : [];
    $filterCategory = $filterValues['category'] ?? '';
    $filterCountry = $filterValues['country'] ?? '';
    $filterCity = $filterValues['city'] ?? '';
    $filterBreakingRaw = $filterValues['is_breaking_news'] ?? null;
    $filterBreakingValue = is_bool($filterBreakingRaw) ? ($filterBreakingRaw ? '1' : '0') : '';
    $limitOptions = [
        10 => 'Top 10',
        25 => 'Top 25',
        50 => 'Top 50',
        100 => 'Top 100',
        0 => 'All results',
    ];
@endphp

<div class="relative min-h-screen overflow-hidden py-12">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(92,122,234,0.16),_transparent_65%)]"></div>

    <div class="relative mx-auto flex max-w-5xl flex-col gap-10 px-4 pb-24">
        <a
            href="{{ route('dashboard') }}"
            class="inline-flex w-fit items-center gap-2 rounded-full border px-5 py-2 text-xs font-semibold uppercase tracking-wide text-muted transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
            style="border-color: var(--border-muted);"
        >
            <span aria-hidden="true" class="text-sm">&larr;</span>
            Back to Chat
        </a>

        <section class="glass-panel rounded-3xl border px-6 py-10 shadow-2xl sm:px-12" style="border-color: var(--border-muted); background: color-mix(in srgb, var(--surface) 85%, transparent);">
            <div class="max-w-3xl space-y-3">
                <span class="text-xs font-semibold uppercase tracking-[0.28em] text-muted">Archive</span>
                <h1 class="text-3xl font-semibold sm:text-4xl" style="color: var(--text)">Search the newsroom library</h1>
                <p class="text-sm leading-6 text-muted sm:text-base">Surface reports, transcripts, and briefs from the UNews knowledge base. You can search in English or Arabic—the engine blends semantic and keyword matches.</p>
            </div>

            <form method="get" action="{{ route('search') }}" role="search" class="mt-8 space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex w-full flex-col gap-3 sm:flex-1 sm:flex-row">
                        <input
                            type="text"
                            name="q"
                            value="{{ $q ?? '' }}"
                            placeholder="Try “latest Lebanon updates” or “ملخص الانتخابات الرئاسية”"
                            class="w-full rounded-full border px-5 py-3 text-sm transition focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent sm:flex-1 sm:text-base"
                            style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                            aria-label="Search archive"
                        >
                        <select
                            name="limit"
                            class="w-full rounded-full border px-4 py-3 text-sm sm:w-48 sm:text-base"
                            style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                            aria-label="Result count"
                        >
                            @foreach($limitOptions as $value => $label)
                                <option value="{{ $value }}" @if($limitValue === (int) $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="w-full rounded-full px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-lg transition-transform duration-150 sm:w-auto sm:text-base"
                        style="background: linear-gradient(135deg, #5c7aea 0%, #3657c9 100%);"
                    >
                        Search
                    </button>
                </div>

                <div class="rounded-2xl border border-dashed px-5 py-5 sm:px-6" style="border-color: rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.02);">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-muted">Control Panel</p>
                            <p class="text-sm text-muted sm:text-base">Optionally narrow the dataset before hybrid search or adjust scoring weights.</p>
                        </div>
                        <span class="w-fit rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-muted" style="border-color: var(--border-muted); background: rgba(255, 255, 255, 0.02);">
                            Optional
                        </span>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border px-5 py-4" style="border-color: var(--border-muted); background: rgba(8, 14, 24, 0.65);">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold" style="color: var(--text)">Filter dataset</h3>
                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(92,122,234,0.25); background: rgba(92,122,234,0.14); color: rgba(227,234,255,0.85);">
                                    Pre-search
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted">Use any combination of filters to cut down the corpus before the hybrid search runs.</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <label class="flex flex-col gap-2 text-sm">
                                    <span class="text-xs uppercase tracking-[0.16em] text-muted">Category</span>
                                    <input
                                        type="text"
                                        name="category"
                                        value="{{ $filterCategory }}"
                                        placeholder="e.g. سياسة"
                                        class="w-full rounded-lg border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                        style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                                    >
                                </label>
                                <label class="flex flex-col gap-2 text-sm">
                                    <span class="text-xs uppercase tracking-[0.16em] text-muted">Country</span>
                                    <input
                                        type="text"
                                        name="country"
                                        value="{{ $filterCountry }}"
                                        placeholder="e.g. لبنان"
                                        class="w-full rounded-lg border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                        style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                                    >
                                </label>
                                <label class="flex flex-col gap-2 text-sm">
                                    <span class="text-xs uppercase tracking-[0.16em] text-muted">City</span>
                                    <input
                                        type="text"
                                        name="city"
                                        value="{{ $filterCity }}"
                                        placeholder="e.g. بيروت"
                                        class="w-full rounded-lg border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                        style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                                    >
                                </label>
                                <label class="flex flex-col gap-2 text-sm sm:col-span-2">
                                    <span class="text-xs uppercase tracking-[0.16em] text-muted">Breaking news</span>
                                    <select
                                        name="is_breaking_news"
                                        class="w-full rounded-lg border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                        style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                                    >
                                        <option value="" @if($filterBreakingValue === '') selected @endif>Include all</option>
                                        <option value="1" @if($filterBreakingValue === '1') selected @endif>Breaking only</option>
                                        <option value="0" @if($filterBreakingValue === '0') selected @endif>Exclude breaking</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-xl border px-5 py-4" style="border-color: var(--border-muted); background: rgba(8, 14, 24, 0.65);">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold" style="color: var(--text)">Advanced weighting</h3>
                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(92,122,234,0.25); background: rgba(92,122,234,0.14); color: rgba(227,234,255,0.85);">
                                    Alpha / Beta
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted">Balance semantic vectors vs. keywords and how much the average chunk score matters.</p>
                            <div class="mt-4 space-y-4">
                                <label class="block">
                                    <div class="flex items-center justify-between text-xs uppercase tracking-[0.16em] text-muted">
                                        <span>Alpha (semantic weight)</span>
                                        <span class="text-[11px] text-muted">0 = lexical · 1 = semantic</span>
                                    </div>
                                    <input
                                        type="number"
                                        name="alpha"
                                        value="{{ number_format($alphaValue, 2, '.', '') }}"
                                        min="0"
                                        max="1"
                                        step="0.05"
                                        class="mt-2 w-full rounded-lg border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                        style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                                    >
                                    <p class="mt-2 text-xs leading-5 text-muted">Raise to lean on semantic matches; lower to favor keyword ranking.</p>
                                </label>
                                <label class="block">
                                    <div class="flex items-center justify-between text-xs uppercase tracking-[0.16em] text-muted">
                                        <span>Beta (doc blend)</span>
                                        <span class="text-[11px] text-muted">0 = best chunk · 1 = average</span>
                                    </div>
                                    <input
                                        type="number"
                                        name="beta"
                                        value="{{ number_format($betaValue, 2, '.', '') }}"
                                        min="0"
                                        max="1"
                                        step="0.05"
                                        class="mt-2 w-full rounded-lg border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                        style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"
                                    >
                                    <p class="mt-2 text-xs leading-5 text-muted">Higher beta rewards documents with consistent relevance across chunks.</p>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        @if($hasQuery)
            <section class="space-y-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
                    <h2 class="text-xl font-semibold sm:text-2xl" style="color: var(--text)">Results for “{{ $q }}”</h2>
                    @if($paginatedMode)
                        <span class="text-sm text-muted">
                            Showing
                            @if($resultCount > 0)
                                {{ $rangeStart + 1 }}&ndash;{{ $rangeEnd }}
                            @else
                                0
                            @endif
                            results
                            @if($hasMoreResults)
                                (more available)
                            @endif
                        </span>
                    @else
                        <span class="text-sm text-muted">{{ $resultCount }} {{ \Illuminate\Support\Str::plural('match', $resultCount) }}</span>
                    @endif
                </div>

                @forelse($resultsList as $r)
                    <article class="glass-panel rounded-2xl border px-6 py-6 shadow-xl sm:px-8 sm:py-8" style="border-color: var(--border-muted);">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold sm:text-xl" style="color: var(--text)">{{ $r->title }}</h3>
                            <span class="rounded-full border px-3 py-1 text-xs font-medium uppercase tracking-wide" style="border-color: rgba(92,122,234,0.35); background: rgba(92,122,234,0.12); color: rgba(227,234,255,0.85);">
                                {{ $r->news_id }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3 text-xs uppercase tracking-wide text-muted sm:text-sm">
                            @if(!empty($r->introduction ?? null))
                                <span class="font-medium normal-case">{{ $r->introduction }}</span>
                            @endif
                        </div>

                        @if(!empty($r->best_snippet ?? null))
                            <div class="mt-4 text-sm leading-7 sm:text-base" style="color: rgba(245, 249, 255, 0.9);">{!! $r->best_snippet !!}</div>
                        @endif

                        @if(!empty($r->body ?? null))
                            <details class="mt-5 overflow-hidden rounded-xl border" style="border-color: rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.22);">
                                <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm font-medium text-muted transition hover:text-[var(--accent)]">
                                    <span class="chevron-icon flex h-7 w-7 items-center justify-center rounded-full border text-xs" style="border-color: rgba(255, 255, 255, 0.12); background: rgba(255, 255, 255, 0.05);">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </span>
                                    Show full article
                                </summary>
                                <div class="border-t px-4 py-4 text-sm leading-7 sm:text-base" style="border-color: rgba(255, 255, 255, 0.08); color: rgba(240, 244, 255, 0.88);">
                                    @if(!empty($r->introduction ?? null))
                                        <p class="font-semibold">{{ $r->introduction }}</p>
                                    @endif
                                    <p class="whitespace-pre-line">{{ $r->body }}</p>
                                </div>
                            </details>
                        @endif

                        <div class="mt-4 text-xs uppercase tracking-[0.22em] text-muted">Relevance score {{ number_format($r->doc_score, 3) }}</div>
                    </article>
                @empty
                    <div class="glass-panel rounded-2xl border px-8 py-12 text-center text-sm leading-7 text-muted sm:text-base" style="border-color: var(--border-muted);">
                        @if($paginatedMode && $rangeStart > 0)
                            You have reached the end of the available matches. Try going back a batch or refine the keywords for new hits.
                        @else
                            No results matched your query. Try a different phrasing, include key names, or switch languages.
                        @endif
                    </div>
                @endforelse
                @if($paginatedMode && ($hasPreviousResults || $hasMoreResults))
                    <div class="flex flex-col gap-4 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs uppercase tracking-[0.22em] text-muted">
                            Batch of {{ $batchSize }} results
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            @if($hasPreviousResults)
                                <a
                                    href="{{ $paginationLinks['prev'] }}"
                                    class="w-full rounded-full border px-5 py-3 text-center text-sm font-semibold uppercase tracking-wide transition sm:w-auto"
                                    style="border-color: var(--border-muted); color: var(--text);"
                                >
                                    Previous {{ $batchSize }}
                                </a>
                            @else
                                <span
                                    class="w-full rounded-full border px-5 py-3 text-center text-sm font-semibold uppercase tracking-wide opacity-50 sm:w-auto"
                                    style="border-color: var(--border-muted); color: var(--text); cursor: not-allowed;"
                                >
                                    Previous {{ $batchSize }}
                                </span>
                            @endif

                            @if($hasMoreResults)
                                <a
                                    href="{{ $paginationLinks['next'] }}"
                                    class="w-full rounded-full px-5 py-3 text-center text-sm font-semibold uppercase tracking-wide text-white shadow-lg transition sm:w-auto"
                                    style="background: linear-gradient(135deg, #5c7aea 0%, #3657c9 100%);"
                                >
                                    Next {{ $batchSize }}
                                </a>
                            @else
                                <span
                                    class="w-full rounded-full border px-5 py-3 text-center text-sm font-semibold uppercase tracking-wide opacity-50 sm:w-auto"
                                    style="border-color: var(--border-muted); color: var(--text); cursor: not-allowed;"
                                >
                                    Next {{ $batchSize }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @elseif(!is_null($q))
            <div class="glass-panel mx-auto max-w-xl rounded-2xl border px-8 py-10 text-center text-sm leading-7 text-muted sm:text-base" style="border-color: var(--border-muted);">
                Ready when you are—enter a search term to explore the archive.
            </div>
        @endif
    </div>
</div>
