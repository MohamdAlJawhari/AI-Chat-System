@props([
    'type' => 'info',
    'title' => null,
])

@php
$palette = [
    'info' => ['bg' => 'rgba(59,130,246,0.1)', 'text' => '#1d4ed8'],
    'success' => ['bg' => 'rgba(34,197,94,0.12)', 'text' => '#16a34a'],
    'warning' => ['bg' => 'rgba(245,158,11,0.15)', 'text' => '#b45309'],
    'danger' => ['bg' => 'rgba(239,68,68,0.15)', 'text' => '#dc2626'],
][$type] ?? ['bg' => 'rgba(148,163,184,0.12)', 'text' => '#475569'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-md px-4 py-3 text-sm']) }} style="background: {{ $palette['bg'] }}; color: {{ $palette['text'] }}">
    @if($title)
        <div class="font-semibold mb-1">{{ $title }}</div>
    @endif
    {{ $slot }}
</div>
