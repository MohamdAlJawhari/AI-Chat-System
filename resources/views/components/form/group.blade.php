@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="block text-sm font-medium" style="color: color-mix(in srgb, var(--text) 80%, transparent)">
            {{ $label }}
        </label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @if($error)
        <ul class="text-sm text-red-600 space-y-1">
            @foreach((array) $error as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
</div>
