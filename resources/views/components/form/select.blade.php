@props(['disabled' => false])

<select
    @disabled($disabled)
    {{ $attributes->merge(['class' => 'w-full rounded-md border px-3 py-2 text-sm outline-none transition focus:ring-1 focus:ring-[var(--accent)]']) }}
    style="background: var(--surface); border-color: var(--border-muted); color: var(--text)"
>
    {{ $slot }}
</select>
