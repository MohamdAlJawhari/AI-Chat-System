@props(['label' => null])

<label class="inline-flex items-center gap-2 text-sm" style="color: color-mix(in srgb, var(--text) 80%, transparent)">
    <input type="checkbox" {{ $attributes->merge(['class' => 'rounded border']) }} style="border-color: var(--border-muted); background: var(--surface)">
    @if($label)
        <span>{{ $label }}</span>
    @else
        {{ $slot }}
    @endif
</label>
