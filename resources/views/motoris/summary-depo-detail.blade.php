@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $bulat = fn ($n) => number_format((float) $n, 0, ',', '.');
    $rp = fn ($n) => 'Rp '.$bulat($n);
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$depo->nama">
        <x-slot:subtitle>
            {{ $depo->distributor ? $depo->distributor.' · ' : '' }}Periode
            {{ $summary->dari === $summary->sampai ? $tgl($summary->dari) : $tgl($summary->dari).' – '.$tgl($summary->sampai) }}
        </x-slot:subtitle>
        <x-slot:actions>
            <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('summary-depo', $filter) }}">Kembali ke Summary All Depo</x-ui.atoms.button>
        </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.card class="mb-3" body-class="card-body text-sm">
        <x-slot:header><div class="font-semibold">Total keseluruhan depo</div></x-slot:header>
        <div class="flex flex-wrap gap-x-6 gap-y-1">
            <span>Hari Kerja: <b>{{ $bulat($total->hari_kerja) }}</b></span>
            <span>Total Call: <b>{{ $bulat($total->call) }}</b></span>
            <span>Total EC: <b>{{ $bulat($total->ec) }}</b></span>
            <span>Total Sales: <b>{{ $rp($total->sales) }}</b></span>
            <span>Total Point: <b>{{ $bulat($total->point) }}</b></span>
        </div>
        <div class="flex flex-wrap gap-x-6 gap-y-1 mt-1">
            <span>AVG Call/Day: <b>{{ $angka($total->avg_call) }}</b></span>
            <span>AVG EC/Day: <b>{{ $angka($total->avg_ec) }}</b></span>
            <span>AVG Sales/Day: <b>{{ $rp($total->avg_sales) }}</b></span>
            <span>AVG IPT: <b>{{ $angka($total->avg_ipt) }}</b></span>
            <span>AVG Point/Day: <b>{{ $angka($total->avg_point) }}</b></span>
        </div>
    </x-ui.molecules.card>

    @forelse ($tim as $t)
        <x-ui.molecules.card class="mb-3" body-class="overflow-x-auto">
            <x-slot:header>
                <span class="font-semibold">{{ $t->nama }}</span>
                <span class="text-xs text-gray-500 ms-2">AVG Visit / Day: {{ $angka($t->avg_visit) }}</span>
            </x-slot:header>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Nama Sales</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Hari Kerja</th>
                        <th scope="col" class="text-end">Call</th>
                        <th scope="col" class="text-end">EC</th>
                        <th scope="col" class="text-end">Sales</th>
                        <th scope="col" class="text-end">SKU</th>
                        <th scope="col" class="text-end">Point</th>
                        <th scope="col" class="text-end">AVG Call</th>
                        <th scope="col" class="text-end">AVG EC</th>
                        <th scope="col" class="text-end">AVG Sales</th>
                        <th scope="col" class="text-end">AVG IPT</th>
                        <th scope="col" class="text-end">AVG Point</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($t->sales as $i => $s)
                        <tr @unless ($s->aktif) class="text-gray-500" @endunless>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $s->nama }}</td>
                            <td><x-ui.atoms.badge :variant="$s->aktif ? 'success' : 'secondary'">{{ $s->aktif ? 'Aktif' : 'Non-aktif' }}</x-ui.atoms.badge></td>
                            <td class="text-end">{{ $s->hari_kerja }}</td>
                            <td class="text-end">{{ $bulat($s->call) }}</td>
                            <td class="text-end">{{ $bulat($s->ec) }}</td>
                            <td class="text-end whitespace-nowrap">{{ $rp($s->sales) }}</td>
                            <td class="text-end">{{ $bulat($s->sku) }}</td>
                            <td class="text-end">{{ $bulat($s->point) }}</td>
                            <td class="text-end">{{ $angka($s->avg_call) }}</td>
                            <td class="text-end">{{ $angka($s->avg_ec) }}</td>
                            <td class="text-end whitespace-nowrap">{{ $rp($s->avg_sales) }}</td>
                            <td class="text-end">{{ $angka($s->avg_ipt) }}</td>
                            <td class="text-end">{{ $angka($s->avg_point) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="text-center text-gray-500">Tidak ada sales di tim ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php($a = $t->akumulasi)
                    <tr class="font-semibold">
                        <th scope="row" colspan="3">Akumulasi</th>
                        <td class="text-end">{{ $bulat($a->hari_kerja) }}</td>
                        <td class="text-end">{{ $bulat($a->call) }}</td>
                        <td class="text-end">{{ $bulat($a->ec) }}</td>
                        <td class="text-end whitespace-nowrap">{{ $rp($a->sales) }}</td>
                        <td class="text-end">{{ $bulat($a->sku) }}</td>
                        <td class="text-end">{{ $bulat($a->point) }}</td>
                        <td class="text-end">{{ $angka($a->avg_call) }}</td>
                        <td class="text-end">{{ $angka($a->avg_ec) }}</td>
                        <td class="text-end whitespace-nowrap">{{ $rp($a->avg_sales) }}</td>
                        <td class="text-end">{{ $angka($a->avg_ipt) }}</td>
                        <td class="text-end">{{ $angka($a->avg_point) }}</td>
                    </tr>
                </tfoot>
            </table>
        </x-ui.molecules.card>
    @empty
        <p class="text-gray-500">Belum ada sales motoris di depo ini.</p>
    @endforelse
@endsection
