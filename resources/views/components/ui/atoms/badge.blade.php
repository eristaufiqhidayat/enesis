@props(['variant' => 'secondary'])

<span {{ $attributes->merge(['class' => "badge badge-{$variant}"]) }}>{{ $slot }}</span>
