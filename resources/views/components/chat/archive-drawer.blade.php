@php
    $defaultAlpha = number_format((float) config('rag.alpha', 0.80), 2, '.', '');
    $defaultBeta = number_format((float) config('rag.beta', 0.20), 2, '.', '');
@endphp

<div id="archiveDrawerDivider" class="w-1 cursor-col-resize" style="background: var(--surface)"></div>
<aside id="archiveDrawer" style="width: var(--archive-w, 22rem); background: var(--surface); border-left: 1px solid var(--border-muted); overflow-y: auto;"
    class="shrink-0 backdrop-blur p-4 flex flex-col transition-[width] duration-200 ease-in-out overflow-hidden">
    <div class="flex items-center justify-between mb-3">
        <div class="text-xs uppercase tracking-wide" style="color: color-mix(in srgb, var(--text) 60%, transparent)">Archive Controls</div>
    </div>

    <div class="space-y-4">
        <div class="chat-top-archive justify-between">
            <div class="flex flex-col leading-tight">
                <span class="text-[11px] uppercase tracking-[0.35em] text-muted">Archive</span>
                <span id="archiveModeBadge" class="text-xs font-semibold" style="color: var(--text);">Off</span>
            </div>
            <label class="relative inline-flex cursor-pointer items-center" title="When enabled, answers cite the newsroom archive - عند التفعيل، تستشهد الإجابات بأرشيف غرفة الأخبار">
                <input type="checkbox" id="archiveToggle" class="sr-only peer">
                <span class="h-6 w-11 rounded-full border transition peer-checked:bg-emerald-400/70 peer-checked:border-emerald-300/70"
                    style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.25);"></span>
                <span
                    class="pointer-events-none absolute left-0.5 top-1/2 h-5 w-5 -translate-y-1/2 rounded-full bg-white shadow transition peer-checked:translate-x-5 peer-checked:bg-white/90"></span>
            </label>
        </div>

        <details class="chat-control-panel" open>
            <summary
                class="block w-full cursor-pointer rounded-full border px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.18em] text-muted transition hover:text-white"
                style="border-color: var(--border-muted); background: color-mix(in srgb, var(--surface) 85%, transparent);">
                Filters & weights
            </summary>
            <div class="chat-control-card mt-3 w-full rounded-2xl px-4 py-4 space-y-5">
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
                <label class="flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-muted">
                    <input type="checkbox" name="auto_filters" value="1" class="accent-[var(--accent)]" checked>
                    Auto filters (AI picks)
                </label>

                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Category - الفئة</span>
                        <input type="text" name="category" placeholder="e.g. Politics - مثال: سياسة" data-filter-source="category" list="filter-category-options" autocomplete="off"
                            class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Country - الدولة</span>
                        <input type="text" name="country" placeholder="e.g. Lebanon - مثال: لبنان" data-filter-source="country" list="filter-country-options" autocomplete="off"
                            class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-[11px] uppercase tracking-[0.18em] text-muted">City - المدينة</span>
                        <input type="text" name="city" placeholder="e.g. Beirut - مثال: بيروت" data-filter-source="city" list="filter-city-options" autocomplete="off"
                            class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm" style="grid-column: 1 / -1;">
                        <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Breaking news - أخبار عاجلة</span>
                        <select name="is_breaking_news"
                            class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent">
                            <option value="">Include all - تضمين الكل</option>
                            <option value="1">Breaking only - العاجلة فقط</option>
                            <option value="0">Exclude breaking - استبعاد العاجلة</option>
                        </select>
                    </label>
                    <div class="flex flex-col gap-1 text-sm" style="grid-column: 1 / -1;">
                        <span class="text-[11px] uppercase tracking-[0.18em] text-muted">Date range - نطاق التاريخ</span>
                        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
                            <input
                                type="date"
                                name="date_from"
                                class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent" 
                                style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);"/>

                            <input
                                type="date"
                                name="date_to"
                                class="w-full rounded-lg border px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-1 focus:ring-offset-transparent"
                                style="background: rgba(8, 14, 24, 0.78); border-color: var(--border-muted); color: var(--text);" />
                        </div>
                        <p class="text-[11px] text-muted">Use either field or both to limit results by when the dispatch was sent. - استخدم أحد الحقلين أو كليهما لتقييد النتائج حسب تاريخ إرسال الخبر.</p>
                    </div>
                </div>

                <div class="flex items-center justify-between cursor-pointer">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--text)">Advanced weighting - وزن متقدم</p>
                        <p class="text-xs text-muted">Semantic vec vs. keywords & doc blending -<br> موازنة الدلالة مقابل الكلمات المفتاحية ودمج الوثائق</p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                        style="border-color: rgba(123,141,255,0.3); color: rgba(215,223,255,0.92); background: rgba(123,141,255,0.12);">
                        Alpha / Beta
                    </span>
                </div>
                <label class="flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-muted">
                    <input type="checkbox" name="auto_weights" value="1" class="accent-[var(--accent)]">
                    Auto weights (AI picks)
                </label>
                
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
                                        <div class="info-tooltip">Alpha balances semantic (embeddings) vs. lexical (keywords). Higher alpha favors semantic similarity; lower alpha leans on keyword ranking. <hr> ألفا توازن بين الدلالي (التضمينات) والمعجمي (الكلمات المفتاحية). زيادة ألفا تفضّل التشابه الدلالي، وخفضها يميل لترتيب الكلمات المفتاحية.</div>
                                    </span>
                                    <span>{{ $defaultAlpha }}</span>
                                </div>
                                <input type="range" min="0" max="1" step="0.05" name="alpha" value="{{ $defaultAlpha }}"
                                    class="w-full accent-[var(--accent)]">
                                <div class="flex justify-between text-[11px] uppercase tracking-[0.18em] text-muted">
                                    <span>Lexical</span>
                                    <span>Semantic</span>
                                </div>
                                <p class="text-xs text-muted">Raise to lean on semantic matches; lower to favor keyword ranking</p>
                            </div>

                            <div class="space-y-2 relative">
                                <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.18em] text-muted">
                                    <span class="flex items-center gap-2">
                                        Beta (doc blend)
                                        <button type="button" class="info-chip" aria-label="What is beta?">?</button>
                                        <div class="info-tooltip">Beta controls how much the average chunk score matters versus the best chunk. Higher beta rewards documents with consistent relevance. <hr> بيتا تتحكم في وزن متوسط درجات المقاطع مقابل أفضل مقطع. زيادة بيتا تكافئ الوثائق ذات الصلة المتسقة.</div>
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
    </div>
</aside>
