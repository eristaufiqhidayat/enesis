@props([
    'headerClass' => 'card-header-compact bg-gray-50',
    'bodyClass' => 'card-body',
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @isset($header)
        <div class="card-header {{ $headerClass }}">{{ $header }}</div>
    @endisset

    <div class="{{ $bodyClass }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
