{{-- Satu tabel Rekap Pencapaian SKU (Tercapai atau Belum) untuk tab Per Motoris / Per Depo. --}}
@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $perMotoris = $tab === 'motoris';
    $kolom = $perMotoris ? 7 : 6;
@endphp
<x-ui.molecules.card body-class="overflow-x-auto">
    <x-slot:header>
        <div class="font-semibold {{ $tercapai ? 'text-green-700' : 'text-red-700' }}">
            {{ $judulTabel }}
        </div>
        <div class="text-xs text-gray-500">{{ $grup['baris']->count() }} dari {{ $jumlah }} total {{ $perMotoris ? 'motoris' : 'depo' }}</div>
    </x-slot:header>
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">No</th>
                <th scope="col">Depo</th>
                @if ($perMotoris) <th scope="col">Motoris</th> @endif
                <th scope="col" class="text-end">Total Qty</th>
                <th scope="col" class="text-end">Hari Kerja</th>
                <th scope="col" class="text-end">{{ $perMotoris ? 'Avg/Day' : 'Avg/Day/Motoris' }}</th>
                <th scope="col">Kategori</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grup['baris'] as $i => $b)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    @if ($perMotoris)
                        <td>{{ $b->depo ?? '-' }}</td>
                        <td>
                            {{ $b->nama }}
                            @unless ($b->aktif) <x-ui.atoms.badge>Tidak aktif</x-ui.atoms.badge> @endunless
                        </td>
                    @else
                        <td>{{ $b->nama }}</td>
                    @endif
                    <td class="text-end">{{ $angka($b->qty) }}</td>
                    <td class="text-end">{{ $b->hari_kerja }}</td>
                    <td class="text-end" @unless ($perMotoris) title="{{ $b->hari_motoris }} hari-motoris bertransaksi" @endunless>{{ $angka($b->avg) }}</td>
                    <td><x-ui.atoms.badge :variant="$tercapai ? 'success' : 'danger'">{{ $tercapai ? 'Tercapai' : 'Belum' }}</x-ui.atoms.badge></td>
                </tr>
            @empty
                <tr><td colspan="{{ $kolom }}" class="text-center text-gray-500">Tidak ada data</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-semibold">
                <th scope="row" colspan="{{ $perMotoris ? 3 : 2 }}">TOTAL</th>
                <td class="text-end">{{ $angka($grup['total']->qty) }}</td>
                <td class="text-end">{{ $grup['total']->hari_kerja }}</td>
                <td class="text-end">{{ $angka($grup['total']->avg) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</x-ui.molecules.card>
