@props(['items', 'current'])

@foreach ($items as $item)
    @if (isset($item['children']))
        @php($open = in_array($current, array_column($item['children'], 'slug'), true))
        <li class="nav-item">
            <a class="nav-link {{ $open ? 'is-open' : '' }}" href="#" data-collapse-toggle="{{ $item['id'] }}" aria-expanded="{{ $open ? 'true' : 'false' }}" title="{{ $item['title'] }}">
                <span class="nav-link-text">{{ $item['title'] }}</span>
                <x-ui.atoms.icon name="chevron-down" class="nav-chevron" />
            </a>
            <ul class="nav flex flex-col list-none m-0 p-0 {{ $open ? '' : 'hidden' }}" id="{{ $item['id'] }}">
                @foreach ($item['children'] as $child)
                    <li class="nav-item">
                        <a class="nav-link {{ $current === $child['slug'] ? 'active' : '' }}" href="{{ route('menu', $child['slug']) }}" title="{{ $child['title'] }}">
                            <span class="nav-link-text">{{ $child['title'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </li>
    @else
        <li class="nav-item">
            <a class="nav-link {{ $current === $item['slug'] ? 'active' : '' }}" href="{{ route('menu', $item['slug']) }}" title="{{ $item['title'] }}">
                <span class="nav-link-text">{{ $item['title'] }}</span>
            </a>
        </li>
    @endif
@endforeach
