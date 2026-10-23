@props(['variant' => 'info'])

<div {{ $attributes->merge(['class' => "alert alert-{$variant}"]) }}>{{ $slot }}</div>
