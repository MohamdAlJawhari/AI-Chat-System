@props([
    'title' => 'Untitled chat',
    'active' => false,
])

<div class="group flex items-center gap-2 px-2 py-1 rounded-lg {{ $active ? 'bg-slate-200 dark:bg-neutral-800' : 'hover:bg-slate-100 dark:hover:bg-neutral-800' }}">
    <button type="button" class="flex-1 text-left px-1 py-1" {{ $attributes }}>
        {{ $title }}
    </button>
    <span class="opacity-0 group-hover:opacity-60 text-xs px-1 py-0.5"><i class="fa-solid fa-pen-to-square"></i></span>
    <span class="opacity-0 group-hover:opacity-60 text-xs px-1 py-0.5"><i class="fa-solid fa-trash"></i></span>
</div>
