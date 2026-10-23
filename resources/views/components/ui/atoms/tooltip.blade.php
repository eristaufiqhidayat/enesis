@props(['label', 'position' => 'bottom', 'align' => 'center'])

<span {{ $attributes->merge(['class' => "tooltip-wrapper tooltip-{$position} tooltip-align-{$align}"]) }} data-tooltip="{{ $label }}">
    {{ $slot }}
</span>
