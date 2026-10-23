@props([])

<select {{ $attributes->merge(['class' => 'form-select form-select-sm']) }}>{{ $slot }}</select>
