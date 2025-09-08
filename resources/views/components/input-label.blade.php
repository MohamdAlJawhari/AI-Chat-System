@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm']) }} style="color: color-mix(in srgb, var(--text) 80%, transparent)">
    {{ $value ?? $slot }}
</label>
