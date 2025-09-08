<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 rounded-md font-semibold text-xs uppercase tracking-widest disabled:opacity-25 transition ease-in-out duration-150']) }} style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted)">
    {{ $slot }}
</button>
