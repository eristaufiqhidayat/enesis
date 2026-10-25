@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
    $persen = fn ($n) => $angka($n).'%';
    $warna = fn ($n) => $n >= 90 ? 'success' : ($n >= 70 ? 'warning' : 'danger');
    $target = $summary->target;
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>
            Dihitung dari data aplikasi motoris ·
            {{ $summary->dari === $summary->sampai ? $tgl($summary->dari) : $tgl($summary->dari).' – '.$tgl($summary->sampai) }} ·
            Target nasional Call {{ $angka($target['call']) }} · EC {{ $angka($target['ec']) }} · IPT {{ $angka($target['ipt']) }}
        </x-slot:subtitle>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <x-ui.molecules.card class="mb-3">
        <form method="GET" class="flex flex-wrap items-end gap-2" role="search">
            <div>
                <x-ui.atoms.label for="date_from">Dari</x-ui.atoms.label>
                <x-ui.atoms.input type="date" id="date_from" name="date_from" value="{{ $filter['date_from'] }}" />
            </div>
            <div>
                <x-ui.atoms.label for="date_to">Sampai</x-ui.atoms.label>
                <x-ui.atoms.input type="date" id="date_to" name="date_to" value="{{ $filter['date_to'] }}" />
            </div>
            <div class="grow min-w-[12rem]">
                <x-ui.atoms.label for="q">Depo</x-ui.atoms.label>
                <x-ui.atoms.input type="search" id="q" name="q" value="{{ $filter['q'] }}" placeholder="Cari depo…" />
            </div>
            <x-ui.atoms.button type="submit">Tampilkan</x-ui.atoms.button>
        </form>

        @if ($bolehUbah)
            <form method="POST" action="{{ route('summary-depo.target') }}" class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t">
                @csrf
                @foreach ($filter as $k => $v)
                    @if ($v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                @foreach (['call' => 'Target Call', 'ec' => 'Target EC', 'ipt' => 'Target IPT'] as $k => $label)
                    <div>
                        <x-ui.atoms.label for="target_{{ $k }}">{{ $label }}</x-ui.atoms.label>
                        <x-ui.atoms.input type="number" id="target_{{ $k }}" name="{{ $k }}" step="0.1" min="0.1" value="{{ $target[$k] + 0 }}" class="w-24" />
                    </div>
                @endforeach
                <x-ui.atoms.button type="submit" variant="outline-secondary">Simpan target</x-ui.atoms.button>
                <span class="text-xs text-gray-500">Target nasional: sama untuk semua depo.</span>
            </form>
        @endif
    </x-ui.molecules.card>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @forelse ($kartu as $k)
            <x-ui.molecules.card body-class="card-body text-sm">
                <x-slot:header>
                    <div class="font-semibold">{{ $k->nama }}</div>
                    @if ($k->distributor) <div class="text-xs text-gray-500">{{ $k->distributor }}</div> @endif
                </x-slot:header>

                @php
                    $bagian = [
                        'Personil aktif' => [
                            ['Total SK', $k->total_sk],
                            ['Total Sales Motoris', $k->total_motoris],
                        ],
                        'Aktivitas periode' => [
                            ['Total Sales', $rp($k->sales)],
                        ],
                        'Rata-rata / hari kerja' => [
                            ['AVG Hari Kerja', $angka($k->avg_hari_kerja)],
                            ['AVG Call / Day', $angka($k->avg_call)],
                            ['Call Achievement', $k->call_achievement],
                            ['AVG EC / Day', $angka($k->avg_ec)],
                            ['EC Achievement', $k->ec_achievement],
                            ['AVG Sales / Day', $rp($k->avg_sales)],
                            ['AVG IPT / Day', $angka($k->avg_ipt)],
                            ['IPT Achievement', $k->ipt_achievement],
                            ['AVG Point / Day', $angka($k->avg_point)],
                        ],
                        'Kunjungan SK' => [
                            ['SK yang Visit', $k->sk_visit],
                            ['Total Visit SK', number_format($k->visit_sk, 0, ',', '.')],
                            ['AVG Visit SK / Day', $angka($k->avg_visit_sk)],
                        ],
                    ];
                @endphp

                @foreach ($bagian as $judulBagian => $baris)
                    <div class="text-xs uppercase tracking-wide text-gray-500 {{ $loop->first ? '' : 'mt-3' }}">{{ $judulBagian }}</div>
                    <dl class="divide-y">
                        @foreach ($baris as [$label, $nilai])
                            <div class="flex justify-between gap-2 py-1">
                                <dt>{{ $label }}</dt>
                                <dd class="font-medium text-end">
                                    @if (str_ends_with($label, 'Achievement'))
                                        <x-ui.atoms.badge :variant="$warna($nilai)">{{ $persen($nilai) }}</x-ui.atoms.badge>
                                    @else
                                        {{ $nilai }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endforeach

                <div class="mt-3 text-end">
                    <x-ui.atoms.button tag="a" variant="outline-secondary"
                        href="{{ route('summary-depo.detail', ['depo' => $k->id, 'date_from' => $filter['date_from'], 'date_to' => $filter['date_to']]) }}">
                        Lihat Detail
                    </x-ui.atoms.button>
                </div>
            </x-ui.molecules.card>
        @empty
            <p class="text-gray-500">Tidak ada depo untuk filter ini.</p>
        @endforelse
    </div>

    <p class="mt-3 text-xs text-gray-500">
        Diurutkan dari EC Achievement tertinggi. Hari kerja sales = tanggal dengan absen atau kunjungan; hari kerja depo = jumlah hari kerja semua sales depo.
        AVG Call/EC/Sales/Point = total ÷ hari kerja depo. AVG IPT = SKU ÷ EC. AVG Hari Kerja = hari kerja depo ÷ sales motoris aktif.
        Achievement = AVG ÷ target nasional. AVG Visit SK/Day = join visit koordinator ÷ {{ $summary->hkKoordinator }} hari kerja koordinator (Senin–Jumat, tanpa libur).
    </p>
@endsection
