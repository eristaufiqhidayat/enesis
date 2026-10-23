@props([])

<textarea {{ $attributes->merge(['class' => 'form-control form-control-sm']) }}>{{ $slot }}</textarea>
