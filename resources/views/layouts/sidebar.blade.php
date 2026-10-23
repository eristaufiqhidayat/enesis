@php($current = request()->route('slug') ?? request()->path())
<nav class="sidebar">
    <div class="brand"><i class="fas fa-leaf me-1"></i> {{ config('app.name') }}</div>
    <ul class="nav flex-column">
        @foreach (config('marketama.menu') as $section)
            @if ($section['label'])
                <li class="section-label">{{ $section['label'] }}</li>
            @endif
            @foreach ($section['items'] as $item)
                @if (isset($item['children']))
                    @php($open = in_array($current, array_column($item['children'], 'slug'), true))
                    <li class="nav-item">
                        <a class="nav-link flex items-center justify-between {{ $open ? 'is-open' : '' }}" href="#" data-collapse-toggle="{{ $item['id'] }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                            <span class="flex items-center"><span class="nav-link-icon"><i class="{{ $item['icon'] }}"></i></span>{{ $item['title'] }}</span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <ul class="nav flex-column {{ $open ? '' : 'hidden' }}" id="{{ $item['id'] }}">
                            @foreach ($item['children'] as $child)
                                <li class="nav-item">
                                    <a class="nav-link {{ $current === $child['slug'] ? 'active' : '' }}" href="{{ route('menu', $child['slug']) }}">
                                        @if ($child['icon'])<span class="nav-link-icon"><i class="{{ $child['icon'] }}"></i></span>@endif{{ $child['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ $current === $item['slug'] ? 'active' : '' }}" href="{{ route('menu', $item['slug']) }}">
                            <span class="nav-link-icon"><i class="{{ $item['icon'] }}"></i></span>{{ $item['title'] }}
                        </a>
                    </li>
                @endif
            @endforeach
        @endforeach
    </ul>
</nav>
