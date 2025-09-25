@props([
    'role' => 'assistant',
    'content' => '',
])

@php
$isUser = $role === 'user';
@endphp

<div class="group relative flex {{ $isUser ? 'justify-end' : 'justify-start pb-6' }}">
    <div class="{{ $isUser ? 'relative max-w-[80%] rounded-2xl px-4 py-2 text-sm bg-[var(--accent)] text-white whitespace-pre-wrap' : 'relative max-w-[80%] text-sm px-0 py-0 bg-transparent' }}">
        @if($isUser)
            <div class="rich">{{ $content }}</div>
        @else
            <div class="rich">{!! nl2br(e($content)) !!}</div>
        @endif
    </div>
</div>
