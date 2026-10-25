@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y');
    $tautanTab = fn ($k) => route('rekap-sku', ['tab' => $k] + array_filter($filter));
    $satuan = $tab === 'motoris' ? 'motoris' : 'depo';
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>
            Dihitung dari penjualan aplikasi motoris ·
            {{ $rekap->dari === $rekap->sampai ? $tgl($rekap->dari) : $tgl($rekap->dari).' – '.$tgl($rekap->sampai) }} ·
            Hari Kerja Standar {{ $rekap->hkStandar }} hari · Target {{ $angka($rekap->target) }} /Hari · Total {{ ucfirst($satuan) }} {{ $hasil['jumlah'] }}
        </x-slot:subtitle>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <div class="flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Bagian Rekap Pencapaian SKU">
        @foreach (\App\Http\Controllers\Admin\RekapPencapaianSkuController::TAB as $kode => $label)
            <x-ui.atoms.button tag="a" role="tab" :variant="$tab === $kode ? 'primary' : 'outline-secondary'" href="{{ $tautanTab($kode) }}" :aria-selected="$tab === $kode ? 'true' : 'false'">
                {{ $label }}
            </x-ui.atoms.button>
        @endforeach
    </div>

    <x-ui.molecules.card class="mb-3">
        <form method="GET" class="flex flex-wrap items-end gap-2" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div>
                <x-ui.atoms.label for="date_from">Dari</x-ui.atoms.label>
                <x-ui.atoms.input type="date" id="date_from" name="date_from" value="{{ $filter['date_from'] }}" />
            </div>
            <div>
                <x-ui.atoms.label for="date_to">Sampai</x-ui.atoms.label>
                <x-ui.atoms.input type="date" id="date_to" name="date_to" value="{{ $filter['date_to'] }}" />
            </div>
            <div class="min-w-[12rem]">
                <x-ui.atoms.label for="warehouse_id">Depo <span class="text-gray-500">(kosong = semua)</span></x-ui.atoms.label>
                <x-ui.atoms.select id="warehouse_id" name="warehouse_id[]" multiple size="4">
                    @foreach ($daftarDepo as $id => $nama)
                        <option value="{{ $id }}" @selected(in_array((int) $id, $filter['warehouse_id'], true))>{{ $nama }}</option>
                    @endforeach
                </x-ui.atoms.select>
            </div>
            <div class="min-w-[14rem]">
                <x-ui.atoms.label for="product_id">Produk <span class="text-gray-500">(kosong = semua)</span></x-ui.atoms.label>
                <x-ui.atoms.select id="product_id" name="product_id[]" multiple size="4">
                    @foreach ($daftarProduk as $id => $nama)
                        <option value="{{ $id }}" @selected(in_array((int) $id, $filter['product_id'], true))>{{ $nama }}</option>
                    @endforeach
                </x-ui.atoms.select>
            </div>
            <x-ui.atoms.button type="submit">Tampilkan</x-ui.atoms.button>
            <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('rekap-sku', ['tab' => $tab]) }}">Reset Filter</x-ui.atoms.button>
        </form>

        @if ($bolehUbah)
            <form method="POST" action="{{ route('rekap-sku.target') }}" class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t">
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
                @foreach (['date_from', 'date_to'] as $k)
                    <input type="hidden" name="{{ $k }}" value="{{ $filter[$k] }}">
                @endforeach
                @foreach (['warehouse_id', 'product_id'] as $k)
                    @foreach ($filter[$k] as $v) <input type="hidden" name="{{ $k }}[]" value="{{ $v }}"> @endforeach
                @endforeach
                <div>
                    <x-ui.atoms.label for="target">Setting Target (/Hari)</x-ui.atoms.label>
                    <x-ui.atoms.input type="number" id="target" name="target" step="0.01" min="0.01" value="{{ $rekap->target + 0 }}" class="w-28" />
                </div>
                <x-ui.atoms.button type="submit" variant="outline-secondary">Simpan target</x-ui.atoms.button>
                <span class="text-xs text-gray-500">Satu target untuk tab Per Motoris dan Per Depo.</span>
            </form>
        @endif
    </x-ui.molecules.card>

    <div class="grid gap-3 lg:grid-cols-2">
        @include('motoris._rekap-sku-tabel', [
            'grup' => $hasil['tercapai'], 'tercapai' => true, 'jumlah' => $hasil['jumlah'],
            'judulTabel' => ($tab === 'motoris' ? 'Pencapaian' : 'Rekap Depo – Pencapaian').' ≥ '.$angka($rekap->target).' /Hari'.($tab === 'depo' ? ' (Avg/Day/Motoris)' : ''),
        ])
        @include('motoris._rekap-sku-tabel', [
            'grup' => $hasil['belum'], 'tercapai' => false, 'jumlah' => $hasil['jumlah'],
            'judulTabel' => ($tab === 'motoris' ? 'Pencapaian' : 'Rekap Depo – Pencapaian').' < '.$angka($rekap->target).' /Hari'.($tab === 'depo' ? ' (Avg/Day/Motoris)' : ''),
        ])
    </div>

    <p class="mt-3 text-xs text-gray-500">
        Total Qty = jumlah qty produk terjual (transaksi yang tidak dibatalkan). Hari Kerja = hari kerja standar sales pada rentang (Waktu Aktif App, tanpa hari libur).
        @if ($tab === 'motoris')
            Avg/Day = Total Qty ÷ Hari Kerja. TOTAL = Σ Qty ÷ (jumlah motoris di tabel × Hari Kerja).
            Daftar berisi sales motoris aktif ditambah sales tidak aktif yang masih menjual pada rentang.
        @else
            Avg/Day/Motoris = Total Qty ÷ jumlah hari-motoris bertransaksi (satu motoris yang menjual pada satu tanggal = 1). TOTAL = Σ Qty ÷ Σ hari-motoris.
            Daftar berisi depo yang punya sales motoris aktif atau penjualan pada rentang.
        @endif
    </p>
@endsection
