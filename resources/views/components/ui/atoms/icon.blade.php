@props(['name'])

<i {{ $attributes->merge(['class' => "fas fa-{$name}"]) }}></i>
