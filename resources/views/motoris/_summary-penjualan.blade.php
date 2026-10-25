{{-- Isi "Detail EC & Penjualan" satu depo (modal Summary All Depo dan halaman penuh). Tautan & form di dalamnya
     memakai data-fragmen supaya di modal dimuat ulang di tempat, sementara di halaman penuh tetap tautan biasa. --}}
@php
    $bulat = fn ($n) => number_format((float) $n, 0, ',', '.');
    $rp = fn ($n) => 'Rp '.$bulat($n);
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d/m/Y');
@endphp
<div class="flex flex-wrap gap-1.5 mb-2">
    <x-ui.atoms.badge variant="success">Total EC: {{ $bulat($total->ec) }}</x-ui.atoms.badge>
    <x-ui.atoms.badge variant="primary">Total Sales: {{ $rp($total->sales) }}</x-ui.atoms.badge>
    <x-ui.atoms.badge variant="warning">Total SKU: {{ $bulat($total->sku) }}</x-ui.atoms.badge>
    <x-ui.atoms.badge variant="warning">Total Point: {{ $bulat($total->point) }}</x-ui.atoms.badge>
</div>

<form method="GET" action="{{ route('summary-depo.penjualan', ['depo' => $depo->id]) }}" class="flex justify-end gap-2 mb-2" role="search" data-fragmen>
    <input type="hidden" name="date_from" value="{{ $filter['date_from'] }}">
    <input type="hidden" name="date_to" value="{{ $filter['date_to'] }}">
    @if (request()->boolean('partial')) <input type="hidden" name="partial" value="1"> @endif
    <x-ui.atoms.input type="search" name="q" value="{{ $filter['q'] }}" placeholder="Cari outlet atau sales…" class="max-w-xs" aria-label="Cari outlet atau sales" />
    <x-ui.atoms.button type="submit" variant="outline-secondary">Cari</x-ui.atoms.button>
</form>

<div class="overflow-x-auto">
    <table class="data-table text-xs">
        <thead>
            <tr>
                <th scope="col">No</th>
                <th scope="col">Tanggal</th>
                <th scope="col">Kode Outlet</th>
                <th scope="col">Nama Outlet</th>
                <th scope="col">Sales</th>
                <th scope="col" class="text-center">EC?</th>
                <th scope="col" class="text-end">Total Sales</th>
                <th scope="col" class="text-end">SKU</th>
                <th scope="col" class="text-end">Point</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $i => $b)
                <tr>
                    <td>{{ $baris->firstItem() + $i }}</td>
                    <td>{{ $tgl($b->tanggal) }}</td>
                    <td>{{ $b->kode_outlet ?? '-' }}</td>
                    <td>{{ $b->nama_outlet ?? '-' }}</td>
                    <td>{{ $b->sales }}</td>
                    <td class="text-center">
                        @if ($b->is_effective_call) <x-ui.atoms.badge variant="success">EC</x-ui.atoms.badge> @else <x-ui.atoms.badge>-</x-ui.atoms.badge> @endif
                    </td>
                    <td class="text-end whitespace-nowrap">{{ $rp($b->total) }}</td>
                    <td class="text-end">{{ $bulat($b->jumlah_sku) }}</td>
                    <td class="text-end">{{ $bulat($b->poin) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-gray-500">Tidak ada transaksi pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($baris->hasPages())
    <div class="pt-2" data-fragmen><x-ui.molecules.pagination :paginator="$baris" /></div>
@endif
