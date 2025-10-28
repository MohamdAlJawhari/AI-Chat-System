@props([
    'q' => null,
    'results' => null,
    'limit' => 10,
])

@php
    $hasQuery = filled($q ?? null);
    $resultsList = $results ?? [];
    $resultCount = $hasQuery && is_countable($resultsList) ? count($resultsList) : 0;
    $limitValue = (int) ($limit ?? 10);
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

            <form method="get" action="{{ route('search') }}" role="search" class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full flex-col gap-3 sm:flex-1 sm:flex-row">
                    <input
                        type="text"
                        name="q"
                        value="{{ $q ?? '' }}"
                        placeholder="Try “latest Lebanon updates” or “ملخص الانتخابات العراقية”…"
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
            </form>
        </section>

        @if($hasQuery)
            <section class="space-y-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
                    <h2 class="text-xl font-semibold sm:text-2xl" style="color: var(--text)">Results for “{{ $q }}”</h2>
                    <span class="text-sm text-muted">{{ $resultCount }} {{ \Illuminate\Support\Str::plural('match', $resultCount) }}</span>
                </div>

                @forelse($resultsList as $r)
                    <article class="glass-panel rounded-2xl border px-6 py-6 shadow-xl sm:px-8 sm:py-8" style="border-color: var(--border-muted);">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold sm:text-xl" style="color: var(--text)">{{ $r->title }}</h3>
                            <span class="rounded-full border px-3 py-1 text-xs font-medium uppercase tracking-wide" style="border-color: rgba(92,122,234,0.35); background: rgba(92,122,234,0.12); color: rgba(227,234,255,0.85);">
                                {{ $r->news_item_id }}
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
                        No results matched your query. Try a different phrasing, include key names, or switch languages.
                    </div>
                @endforelse
            </section>
        @elseif(!is_null($q))
            <div class="glass-panel mx-auto max-w-xl rounded-2xl border px-8 py-10 text-center text-sm leading-7 text-muted sm:text-base" style="border-color: var(--border-muted);">
                Ready when you are—enter a search term to explore the archive.
            </div>
        @endif
    </div>
</div>
