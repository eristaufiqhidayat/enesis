@php($current = request()->route('slug'))
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
                        <a class="nav-link {{ $open ? '' : 'collapsed' }}" href="#{{ $item['id'] }}" data-bs-toggle="collapse" aria-expanded="{{ $open ? 'true' : 'false' }}">
                            <span class="nav-link-icon"><i class="{{ $item['icon'] }}"></i></span>{{ $item['title'] }}
                        </a>
                        <ul class="nav flex-column collapse {{ $open ? 'show' : '' }}" id="{{ $item['id'] }}">
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
