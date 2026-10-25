@php
    $angka = fn ($n) => number_format((float) $n, 0, ',', '.');
    $desimal = fn ($n) => number_format((float) $n, 1, ',', '.');
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y');
    $tautanTab = fn ($k) => route('motoris-report', ['tab' => $k, 'bulan' => $bulan]);
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>
            Dihitung dari data aplikasi motoris dan riwayat aplikasi lama ·
            periode {{ $tgl($laporan->dari) }} – {{ $tgl($laporan->sampai) }} · HK standar {{ $laporan->hkStandar }} hari
        </x-slot:subtitle>
        @if ($bolehKuota && $tab !== 'absensi')
            <x-slot:actions>
                <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('kuota-motoris.index', ['bulan' => $bulan]) }}">Atur kuota motoris</x-ui.atoms.button>
            </x-slot:actions>
        @endif
    </x-ui.molecules.page-header>

    <div class="flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Bagian Motoris Report">
        @foreach (\App\Http\Controllers\Admin\MotorisReportController::TAB as $kode => $label)
            <x-ui.atoms.button tag="a" role="tab" :variant="$tab === $kode ? 'primary' : 'outline-secondary'" href="{{ $tautanTab($kode) }}" :aria-selected="$tab === $kode ? 'true' : 'false'">
                {{ $label }}
            </x-ui.atoms.button>
        @endforeach
    </div>

    <x-ui.molecules.card body-class="overflow-x-auto">
        <x-slot:header>
            <form method="GET" class="flex flex-wrap items-end gap-2" role="search">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div>
                    <x-ui.atoms.label for="bulan">Bulan</x-ui.atoms.label>
                    <x-ui.atoms.input type="month" id="bulan" name="bulan" value="{{ $bulan }}" />
                </div>
                @if ($tab === 'absensi')
                    <div class="grow min-w-[12rem]">
                        <x-ui.atoms.label for="q">Nama sales</x-ui.atoms.label>
                        <x-ui.atoms.input type="search" id="q" name="q" value="{{ $filter['q'] }}" placeholder="Cari nama sales…" />
                    </div>
                    <div>
                        <x-ui.atoms.label for="depo">Depo</x-ui.atoms.label>
                        <x-ui.atoms.select id="depo" name="depo">
                            <option value="">Semua depo</option>
                            @foreach ($daftarDepo as $id => $nama)
                                <option value="{{ $id }}" @selected($filter['depo'] === (int) $id)>{{ $nama }}</option>
                            @endforeach
                        </x-ui.atoms.select>
                    </div>
                    @if ($daftarKoordinator->isNotEmpty())
                        <div>
                            <x-ui.atoms.label for="koordinator">Koordinator</x-ui.atoms.label>
                            <x-ui.atoms.select id="koordinator" name="koordinator">
                                <option value="">Semua koordinator</option>
                                @foreach ($daftarKoordinator as $id => $nama)
                                    <option value="{{ $id }}" @selected($filter['koordinator'] === (int) $id)>{{ $nama }}</option>
                                @endforeach
                            </x-ui.atoms.select>
                        </div>
                    @endif
                @endif
                <x-ui.atoms.button type="submit">Tampilkan</x-ui.atoms.button>
            </form>
        </x-slot:header>

        @if ($tab === 'coverage')
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">{{ $total->kota === 'TOTAL 0 CITY' ? 'City' : \Illuminate\Support\Str::after($total->kota, 'TOTAL ') }}</th>
                        <th scope="col" class="text-end">Quota Motoris</th>
                        <th scope="col" class="text-end">Already Transaction</th>
                        <th scope="col" class="text-end">Active End Periode</th>
                        <th scope="col" class="text-end">Vacant</th>
                        <th scope="col" class="text-end">Coverage %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $b)
                        <tr>
                            <td>{{ $b->kota }}</td>
                            <td class="text-end">{{ $angka($b->kuota) }}</td>
                            <td class="text-end">{{ $angka($b->sudah_transaksi) }}</td>
                            <td class="text-end">{{ $angka($b->aktif) }}</td>
                            <td class="text-end">{{ $angka($b->vacant) }}</td>
                            <td class="text-end">{{ $b->coverage === null ? '-' : $desimal($b->coverage).'%' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gray-500">Belum ada data depo.</td></tr>
                    @endforelse
                </tbody>
                @if ($baris->isNotEmpty())
                    <tfoot>
                        <tr class="font-semibold">
                            <th scope="row">{{ $total->kota }}</th>
                            <td class="text-end">{{ $angka($total->kuota) }}</td>
                            <td class="text-end">{{ $angka($total->sudah_transaksi) }}</td>
                            <td class="text-end">{{ $angka($total->aktif) }}</td>
                            <td class="text-end">{{ $angka($total->vacant) }}</td>
                            <td class="text-end">{{ $total->coverage === null ? '-' : $desimal($total->coverage).'%' }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            <p class="m-3 text-xs text-gray-500">
                Quota = kuota motoris per depo (Master Data › Kuota Motoris). Already Transaction = sales yang punya minimal satu penjualan pada periode.
                Active End Periode = sales motoris berstatus aktif. Vacant = Quota − Active. Coverage = Active ÷ Quota.
            </p>
        @elseif ($tab === 'performance')
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">{{ $total->kota === 'TOTAL 0 CITY' ? 'City' : \Illuminate\Support\Str::after($total->kota, 'TOTAL ') }}</th>
                        <th scope="col" class="text-end">Motoris Kuota</th>
                        <th scope="col" class="text-end">Motoris Already Transaction</th>
                        <th scope="col" class="text-end">Motoris Actual End Periode</th>
                        <th scope="col" class="text-end">HK Standar</th>
                        <th scope="col" class="text-end">Act HK</th>
                        <th scope="col" class="text-end">Target</th>
                        <th scope="col" class="text-end">Call</th>
                        <th scope="col" class="text-end">EC</th>
                        <th scope="col" class="text-end">IPT</th>
                        <th scope="col" class="text-end">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $b)
                        <tr>
                            <td>{{ $b->kota }}</td>
                            <td class="text-end">{{ $angka($b->kuota) }}</td>
                            <td class="text-end">{{ $angka($b->sudah_transaksi) }}</td>
                            <td class="text-end">{{ $angka($b->aktif) }}</td>
                            <td class="text-end">{{ $b->hk_standar }}</td>
                            <td class="text-end">{{ $desimal($b->act_hk) }}</td>
                            <td class="text-end">{{ $angka($b->target) }}</td>
                            <td class="text-end">{{ $angka($b->call) }}</td>
                            <td class="text-end">{{ $angka($b->ec) }}</td>
                            <td class="text-end">{{ $desimal($b->ipt) }}</td>
                            <td class="text-end whitespace-nowrap">Rp {{ $angka($b->value) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-gray-500">Belum ada data depo.</td></tr>
                    @endforelse
                </tbody>
                @if ($baris->isNotEmpty())
                    <tfoot>
                        <tr class="font-semibold">
                            <th scope="row">{{ $total->kota }}</th>
                            <td class="text-end">{{ $angka($total->kuota) }}</td>
                            <td class="text-end">{{ $angka($total->sudah_transaksi) }}</td>
                            <td class="text-end">{{ $angka($total->aktif) }}</td>
                            <td class="text-end">{{ $total->hk_standar }}</td>
                            <td class="text-end">{{ $desimal($total->act_hk) }}</td>
                            <td class="text-end">{{ $angka($total->target) }}</td>
                            <td class="text-end">{{ $angka($total->call) }}</td>
                            <td class="text-end">{{ $angka($total->ec) }}</td>
                            <td class="text-end">{{ $desimal($total->ipt) }}</td>
                            <td class="text-end whitespace-nowrap">Rp {{ $angka($total->value) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            <p class="m-3 text-xs text-gray-500">
                HK Standar = hari kerja sales sampai hari ini (Waktu Aktif App, tanpa hari libur). Act HK = rata-rata hari hadir per motoris yang absen.
                Target = target call per hari depo (sama dengan aplikasi HP) × HK Standar × motoris aktif. EC = transaksi effective call. IPT = rata-rata SKU per transaksi. Value = total penjualan (tidak termasuk yang dibatalkan).
            </p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Nama Sales Motoris</th>
                        <th scope="col">Nama Koordinator</th>
                        <th scope="col">Nama Depo</th>
                        <th scope="col" class="text-end">Hari Kerja</th>
                        <th scope="col" class="text-end">Total Kehadiran</th>
                        <th scope="col" class="text-end">Total Tidak Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $i => $b)
                        <tr>
                            <td>{{ $baris->firstItem() + $i }}</td>
                            <td>
                                {{ $b->nama }}
                                @unless ($b->aktif) <x-ui.atoms.badge>Tidak aktif</x-ui.atoms.badge> @endunless
                            </td>
                            <td>{{ $b->koordinator ?? '-' }}</td>
                            <td>{{ $b->depo ?? '-' }}</td>
                            <td class="text-end">{{ $b->hari_kerja }}</td>
                            <td class="text-end">{{ $b->hadir }}</td>
                            <td class="text-end">{{ $b->tidak_hadir }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-gray-500">Tidak ada sales untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($baris->hasPages())
                <div class="p-3"><x-ui.molecules.pagination :paginator="$baris" /></div>
            @endif
        @endif
    </x-ui.molecules.card>
@endsection
