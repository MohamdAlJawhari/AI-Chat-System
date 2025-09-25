@props(['value' => null])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium']) }} style="color: color-mix(in srgb, var(--text) 80%, transparent)">
    {{ $value ?? $slot }}
</label>
