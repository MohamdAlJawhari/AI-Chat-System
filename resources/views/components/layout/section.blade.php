@props([
    'title' => null,
    'description' => null,
    'actions' => null,
])

<section {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @if($title || $description || $actions)
        <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @if($title)
                    <h2 class="text-lg font-semibold">{{ $title }}</h2>
                @endif
                @if($description)
                    <p class="text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            @if($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endif
        </header>
    @endif
    <div class="rounded-xl glass-panel p-6">
        {{ $slot }}
    </div>
</section>
