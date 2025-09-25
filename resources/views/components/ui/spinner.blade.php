<div {{ $attributes->merge(['class' => 'three-body-wrap']) }}>
    <div class="three-body" aria-hidden="true">
        <div class="three-body__dot"></div>
        <div class="three-body__dot"></div>
        <div class="three-body__dot"></div>
    </div>
    @isset($slot)
        <span class="three-body-timer">{{ $slot }}</span>
    @endisset
</div>
