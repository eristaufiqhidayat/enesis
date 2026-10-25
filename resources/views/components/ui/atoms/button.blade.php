@props([
    'variant' => 'primary',
    'size' => 'sm',
    'tag' => 'button',
    'type' => 'button',
])

@php($classes = "btn btn-{$size} btn-{$variant}")

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
