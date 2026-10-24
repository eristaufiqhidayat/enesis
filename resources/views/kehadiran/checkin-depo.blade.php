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
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>Data aplikasi motoris dan riwayat aplikasi lama · jam dalam WIB</x-slot:subtitle>
    </x-ui.molecules.page-header>

    <div class="flex flex-wrap gap-3 mb-3 text-gray-600">
        <span><strong>{{ $angka($ringkas['total']) }}</strong> checkin</span>
        <span><strong>{{ $angka($ringkas['belum_checkout']) }}</strong> belum checkout</span>
        <span><strong>{{ $angka($ringkas['motoris']) }}</strong> dari aplikasi motoris</span>
    </div>

    <x-ui.molecules.card body-class="overflow-x-auto">
        <x-slot:header>@include('kehadiran._filter')</x-slot:header>

        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Sales</th>
                    <th scope="col">Depo · Koordinator</th>
                    <th scope="col">Checkin</th>
                    <th scope="col">Checkout</th>
                    <th scope="col">Durasi</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col">Sumber</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td class="whitespace-nowrap">{{ $tgl($b->tanggal) }}</td>
                        <td class="font-semibold">{{ $b->sales ?? 'Karyawan #'.$b->karyawan_id }}</td>
                        <td>{{ $b->depo ?? '-' }} @if ($b->koordinator) <div class="text-gray-500">Koor: {{ $b->koordinator }}</div> @endif</td>
                        <td>{{ $jam($b->masuk_at) }}</td>
                        <td>{{ $jam($b->pulang_at) }}</td>
                        <td class="whitespace-nowrap">{{ $durasi($b->masuk_at, $b->pulang_at) }}</td>
                        <td class="whitespace-nowrap">
                            @if ($b->lat && $b->lng)
                                <a href="https://www.google.com/maps?q={{ $b->lat }},{{ $b->lng }}" target="_blank" rel="noopener">Peta</a>
                                @if ($b->jarak_m !== null) <span class="text-gray-500">· {{ $angka($b->jarak_m) }} m dari depo</span> @endif
                            @else
                                <span class="text-gray-500">—</span>
                            @endif
                        </td>
                        <td><x-ui.atoms.badge :variant="$b->sumber === 'motoris' ? 'primary' : 'secondary'">{{ $b->sumber === 'motoris' ? 'Motoris' : 'Aplikasi lama' }}</x-ui.atoms.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-gray-500 py-4">Tidak ada checkin depo pada rentang ini.</td></tr>
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
