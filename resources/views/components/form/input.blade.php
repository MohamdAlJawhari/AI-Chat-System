@props(['disabled' => false, 'type' => 'text'])

<input
    @disabled($disabled)
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'rounded-md shadow-sm px-3 py-2 outline-none']) }}
    style="background: var(--surface); border: 1px solid var(--border-muted); color: var(--text)"
>
