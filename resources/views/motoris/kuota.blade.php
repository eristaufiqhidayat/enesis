@php
    $namaBulan = \App\Http\Controllers\Admin\KuotaMotorisController::namaBulan($bulan);
    $sumber = ['enesis' => ['Isian admin', 'primary'], 'marketama' => ['Master Depo Marketama', 'secondary'], 'kosong' => ['Belum ada', 'warning']];
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>Kuota sales motoris per depo untuk Motoris Report (Coverage &amp; Performance). Kuota berlaku mulai bulan yang dipilih sampai diubah lagi; depo yang belum diisi memakai kuota dari Master Depo Marketama.</x-slot:subtitle>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />
    @if (session('galat'))
        <x-ui.molecules.alert variant="danger" class="mb-3" role="alert">{{ session('galat') }}</x-ui.molecules.alert>
    @endif

    <x-ui.molecules.card body-class="overflow-x-auto" class="mb-3">
        <x-slot:header>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-ui.atoms.label for="bulan">Berlaku mulai bulan</x-ui.atoms.label>
                    <x-ui.atoms.input type="month" id="bulan" name="bulan" value="{{ $bulan }}" />
                </div>
                <x-ui.atoms.button type="submit" variant="outline-secondary">Tampilkan</x-ui.atoms.button>
            </form>
        </x-slot:header>

        <form method="POST" action="{{ route('kuota-motoris.simpan') }}">
            @csrf
            <input type="hidden" name="bulan" value="{{ $bulan }}">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Kota (City Group)</th>
                        <th scope="col">Depo</th>
                        <th scope="col" class="text-end">Kuota Marketama</th>
                        <th scope="col" class="text-end">Kuota {{ $namaBulan }}</th>
                        <th scope="col">Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($depo as $d)
                        <tr @class(['opacity-60' => ! $d->aktif])>
                            <td>{{ $d->kota ?? '-' }}</td>
                            <td>
                                {{ $d->nama }}
                                @unless ($d->aktif) <x-ui.atoms.badge>Tidak aktif</x-ui.atoms.badge> @endunless
                            </td>
                            <td class="text-end">{{ $d->kuota_marketama ?? '-' }}</td>
                            <td class="text-end">
                                @if ($bolehUbah)
                                    <x-ui.atoms.input type="number" min="0" step="1" class="w-24 text-end ms-auto"
                                        name="kuota[{{ $d->id }}]" value="{{ old('kuota.'.$d->id, $d->kuota) }}"
                                        aria-label="Kuota {{ $d->nama }}" />
                                @else
                                    {{ $d->kuota ?? '-' }}
                                @endif
                            </td>
                            <td>
                                <x-ui.atoms.badge :variant="$sumber[$d->sumber][1]">{{ $sumber[$d->sumber][0] }}</x-ui.atoms.badge>
                                @if ($d->sumber === 'enesis' && $d->berlaku_mulai !== $bulan)
                                    <span class="text-gray-500">sejak {{ \App\Http\Controllers\Admin\KuotaMotorisController::namaBulan($d->berlaku_mulai) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gray-500">Belum ada data depo.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($bolehUbah && $depo->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-2 p-3">
                    <p class="mb-0 text-xs text-gray-500">Yang disimpan hanya depo yang nilainya berubah. Kosongkan isian untuk menghapus kuota bulan ini.</p>
                    <x-ui.atoms.button type="submit">Simpan kuota mulai {{ $namaBulan }}</x-ui.atoms.button>
                </div>
            @endif
        </form>
    </x-ui.molecules.card>

    @if ($riwayat->isNotEmpty())
        <x-ui.molecules.card body-class="overflow-x-auto">
            <x-slot:header><span class="font-semibold">Riwayat isian kuota</span></x-slot:header>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Depo</th>
                        <th scope="col">Berlaku mulai</th>
                        <th scope="col" class="text-end">Kuota</th>
                        <th scope="col">Diubah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($riwayat as $r)
                        <tr>
                            <td>{{ $r->depo ?? '-' }}</td>
                            <td>{{ \App\Http\Controllers\Admin\KuotaMotorisController::namaBulan($r->berlaku_mulai) }}</td>
                            <td class="text-end">{{ $r->kuota }}</td>
                            <td class="text-gray-500">{{ $r->updated_at ? \Illuminate\Support\Carbon::parse($r->updated_at)->timezone($zona)->format('d M Y H:i') : '-' }}{{ $r->oleh ? ' · '.$r->oleh : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.molecules.card>
    @endif
@endsection
