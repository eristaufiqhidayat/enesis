@props(['slug' => null])

@php
    $current = $slug ?? request()->route('slug') ?? request()->path();
    $trail = [];

    foreach (config('marketama.menu') as $section) {
        foreach ($section['items'] as $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (($child['slug'] ?? null) === $current) {
                        $trail = array_values(array_filter([$section['label'], $item['title'], $child['title']]));
                        break 3;
                    }
                }
            } elseif (($item['slug'] ?? null) === $current) {
                $trail = array_values(array_filter([$section['label'], $item['title']]));
                break 2;
            }
        }
    }
@endphp

@if (! empty($trail))
    <nav {{ $attributes->merge(['class' => 'breadcrumb']) }}>
        @foreach ($trail as $index => $label)
            @if ($index > 0)
                <span class="breadcrumb-separator">/</span>
            @endif
            <span class="breadcrumb-item">{{ $label }}</span>
        @endforeach
    </nav>
@endif
