@props([
    'title' => null,
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl glass-panel shadow-sm']) }}>
    @if($title || isset($actions))
        <header class="flex items-center justify-between gap-4 border-b border-muted px-6 py-4">
            @if($title)
                <h3 class="text-base font-semibold" style="color: var(--text)">{{ $title }}</h3>
            @endif
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>
