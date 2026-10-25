@props(['paginator'])

{{-- Paginasi Blade, sama dengan Pagination.vue: memakai class .pagination/.page-link di app.css. --}}
@if ($paginator->hasPages())
    <nav {{ $attributes->merge(['aria-label' => 'Halaman']) }}>
        <ul class="pagination mb-0">
            @foreach ($paginator->onEachSide(1)->linkCollection() as $link)
                <li @class(['page-item', 'active' => $link['active'], 'disabled' => ! $link['url']])>
                    @if ($link['url'])
                        <a class="page-link" href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif>{!! $link['label'] !!}</a>
                    @else
                        <span class="page-link">{!! $link['label'] !!}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
