<x-layout.page :title="'Archive Search'">
    <x-slot name="head">
        <style>
            summary::-webkit-details-marker { display: none; }
            details[open] summary .chevron-icon { transform: rotate(90deg); }
            details summary .chevron-icon { transition: transform 0.2s ease; }
        </style>
    </x-slot>

    <x-slot name="scripts">
        @php($__filter_opts_ver = @filemtime(public_path('js/filter-options.js')) ?: time())
        <script src="/js/filter-options.js?v={{ $__filter_opts_ver }}"></script>
    </x-slot>

    <x-search.page
        :q="$q"
        :query_original="$query_original ?? $q"
        :query_used="$query_used ?? $q"
        :query_rewrite="$query_rewrite ?? null"
        :query_rewrite_applied="$query_rewrite_applied ?? false"
        :results="$results"
        :limit="$limit"
        :pagination="$pagination"
        :alpha="$alpha"
        :beta="$beta"
        :filters="$filters"
    />
</x-layout.page>
