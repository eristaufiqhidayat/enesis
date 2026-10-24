@props(['title'])

{{-- Judul halaman + keterangan + tombol aksi di kanan (dipakai halaman kelola akun & role). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-3 mb-3']) }}>
    <div>
        <h5 class="mb-0 font-semibold">{{ $title }}</h5>
        @isset($subtitle)
            <p class="mb-0 text-xs text-gray-500">{{ $subtitle }}</p>
        @endisset
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
