@props(['items', 'current', 'topLevel' => false])

{{-- Item ber-children dirender sebagai flyout: panel submenu muncul di kanan parent (desktop) atau accordion inline (mobile). Rekursif, jadi children di dalam children memakai mekanisme yang sama. --}}
@php($hasActiveDescendant = function (array $items) use (&$hasActiveDescendant, $current) {
    foreach ($items as $item) {
        if (isset($item['children'])) {
            if ($hasActiveDescendant($item['children'])) {
                return true;
            }
        } elseif ($item['slug'] === $current) {
            return true;
        }
    }

    return false;
})

@foreach ($items as $item)
    @if (isset($item['children']))
        @php($open = $hasActiveDescendant($item['children']))
        <li class="nav-item has-flyout">
            <a class="nav-link {{ $open ? 'is-open has-active-child' : '' }}" href="#" data-flyout-toggle aria-haspopup="true" aria-expanded="{{ $open ? 'true' : 'false' }}">
                <span class="nav-link-text">{{ $item['title'] }}</span>
                <x-ui.atoms.icon name="chevron-right" class="nav-chevron" />
            </a>
            <span class="nav-flyout-arrow" data-flyout-arrow aria-hidden="true"></span>
            <ul class="nav flex flex-col list-none m-0 p-0 nav-flyout {{ $open ? 'is-open' : '' }}" id="{{ $item['id'] }}" data-flyout-panel>
                <li class="nav-flyout-header">
                    @if (! empty($item['icon']))
                        <x-ui.atoms.icon :name="$item['icon']" class="section-label-icon" />
                    @endif
                    <span>{{ $item['title'] }}</span>
                </li>
                <x-ui.organisms.partials.sidebar-nav-items :items="$item['children']" :current="$current" />
            </ul>
        </li>
    @else
        <li class="nav-item">
            <a class="nav-link {{ $current === $item['slug'] ? 'active' : '' }}" href="{{ route('menu', $item['slug']) }}" @if ($topLevel) data-tooltip-menu="{{ $item['title'] }}" @endif>
                @if ($topLevel && ! empty($item['icon']))
                    <span class="nav-link-icon">
                        <x-ui.atoms.icon :name="$item['icon']" class="nav-link-icon-svg" />
                    </span>
                @endif
                <span class="nav-link-text">{{ $item['title'] }}</span>
            </a>
        </li>
    @endif
@endforeach
