@php
    $jam = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t, 'UTC')->timezone($zona)->format('H:i') : '—';
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('D, d M Y');
    $durasi = function ($a, $b) {
        if (! $a || ! $b) {
            return '—';
        }
        $menit = (int) \Illuminate\Support\Carbon::parse($a)->diffInMinutes(\Illuminate\Support\Carbon::parse($b));

        return intdiv($menit, 60).' j '.($menit % 60).' m';
    };
    $angka = fn ($n) => number_format($n, 0, ',', '.');
    $tautan = fn ($s) => route('kehadiran.' . $menu, array_filter(['status' => $s === 'semua' ? null : $s] + request()->except(['status', 'page'])));
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>Data aplikasi motoris dan riwayat aplikasi lama · jam dalam WIB</x-slot:subtitle>
    </x-ui.molecules.page-header>

    <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Saring status">
        @foreach (['semua' => ['Semua', $ringkas['total']], 'terlambat' => ['Terlambat', $ringkas['terlambat']], 'belum_pulang' => ['Belum absen pulang', null]] as $kode => [$label, $n])
            <x-ui.atoms.button tag="a" :variant="$status === $kode ? 'primary' : 'outline-secondary'" href="{{ $tautan($kode) }}" :aria-current="$status === $kode ? 'true' : null">
                {{ $label }} @if ($n !== null) <span class="ms-1 opacity-75">{{ $angka($n) }}</span> @endif
            </x-ui.atoms.button>
        @endforeach
        <span class="self-center text-gray-500 ms-auto">{{ $angka($ringkas['motoris']) }} dari aplikasi motoris</span>
    </div>

    <x-ui.molecules.card body-class="overflow-x-auto">
        <x-slot:header>@include('kehadiran._filter')</x-slot:header>

        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">{{ $labelOrang }}</th>
                    <th scope="col">{{ $labelOrang === 'Koordinator' ? 'Depo' : 'Depo · Koordinator' }}</th>
                    <th scope="col">Masuk</th>
                    <th scope="col">Pulang</th>
                    <th scope="col">Durasi</th>
                    <th scope="col">Lokasi &amp; selfie</th>
                    <th scope="col">Sumber</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td class="whitespace-nowrap">{{ $tgl($b->tanggal) }}</td>
                        <td class="font-semibold">{{ $b->sales ?? 'Karyawan #'.$b->karyawan_id }}</td>
                        <td>{{ $b->depo ?? '-' }} @if ($b->koordinator) <div class="text-gray-500">Koor: {{ $b->koordinator }}</div> @endif</td>
                        <td class="whitespace-nowrap">
                            {{ $jam($b->masuk_at) }}
                            @if ($b->terlambat) <x-ui.atoms.badge variant="warning">Terlambat</x-ui.atoms.badge> @endif
                        </td>
                        <td>{{ $jam($b->pulang_at) }}</td>
                        <td class="whitespace-nowrap">{{ $durasi($b->masuk_at, $b->pulang_at) }}</td>
                        <td class="whitespace-nowrap">
                            @if ($b->lat && $b->lng)
                                <a href="https://www.google.com/maps?q={{ $b->lat }},{{ $b->lng }}" target="_blank" rel="noopener">Peta</a>
                            @endif
                            @if ($b->foto_id)
                                · <a href="{{ route('kehadiran.foto', $b->foto_id) }}" target="_blank" rel="noopener">Selfie</a>
                            @endif
                            @if ($b->mock_location) <x-ui.atoms.badge variant="danger" title="HP memakai lokasi palsu">Lokasi palsu</x-ui.atoms.badge> @endif
                            @if (! $b->lat && ! $b->foto_id) <span class="text-gray-500">—</span> @endif
                        </td>
                        <td><x-ui.atoms.badge :variant="$b->sumber === 'motoris' ? 'primary' : 'secondary'">{{ $b->sumber === 'motoris' ? 'Motoris' : 'Aplikasi lama' }}</x-ui.atoms.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-gray-500 py-4">Tidak ada absensi pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <small class="text-gray-500">Menampilkan {{ $baris->firstItem() ?? 0 }}–{{ $baris->lastItem() ?? 0 }} dari {{ $angka($baris->total()) }}</small>
                <x-ui.molecules.pagination :paginator="$baris" />
            </div>
        </x-slot:footer>
    </x-ui.molecules.card>
@endsection
