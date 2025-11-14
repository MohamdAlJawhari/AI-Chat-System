<x-layout.page :title="'Archive Search'">
    <x-slot name="head">
        <style>
            summary::-webkit-details-marker { display: none; }
            details[open] summary .chevron-icon { transform: rotate(90deg); }
            details summary .chevron-icon { transition: transform 0.2s ease; }
        </style>
    </x-slot>

    <x-search.page :q="$q" :results="$results" :limit="$limit" :pagination="$pagination" />
</x-layout.page>
