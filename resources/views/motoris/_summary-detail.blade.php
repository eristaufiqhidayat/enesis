{{-- Isi "Lihat Detail" satu depo (dipakai di modal Summary All Depo dan halaman penuh /summary_all_depo/{depo}). --}}
@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $bulat = fn ($n) => number_format((float) $n, 0, ',', '.');
    $rp = fn ($n) => 'Rp '.$bulat($n);
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
@endphp
<div class="text-xs text-gray-500 mb-2 flex items-center gap-1">
    <x-ui.atoms.icon name="calendar" class="w-3.5 h-3.5" />
    Periode: <b class="text-gray-700">{{ $summary->dari === $summary->sampai ? $tgl($summary->dari) : $tgl($summary->dari).' – '.$tgl($summary->sampai) }}</b>
</div>

<div class="rounded-md border border-blue-100 bg-blue-50/60 px-3 py-2 mb-3 text-xs">
    <div class="font-semibold text-blue-700 uppercase tracking-wide mb-1 border-l-2 border-blue-600 ps-2">Total keseluruhan depo</div>
    <div class="flex flex-wrap gap-x-5 gap-y-1 text-gray-600">
        <span>Hari Kerja: <b class="text-gray-900">{{ $bulat($total->hari_kerja) }}</b></span>
        <span>Total Call: <b class="text-gray-900">{{ $bulat($total->call) }}</b></span>
        <span>Total EC: <b class="text-green-600">{{ $bulat($total->ec) }}</b></span>
        <span>Total Sales: <b class="text-green-600">{{ $rp($total->sales) }}</b></span>
        <span>Total Point: <b class="text-orange-500">{{ $bulat($total->point) }}</b></span>
    </div>
    <div class="flex flex-wrap gap-x-5 gap-y-1 mt-1 text-gray-600">
        <span>AVG Call/Day: <b class="text-sky-500">{{ $angka($total->avg_call) }}</b></span>
        <span>AVG EC/Day: <b class="text-green-600">{{ $angka($total->avg_ec) }}</b></span>
        <span>AVG Sales/Day: <b class="text-green-600">{{ $rp($total->avg_sales) }}</b></span>
        <span>AVG IPT: <b class="text-gray-900">{{ $angka($total->avg_ipt) }}</b></span>
        <span>AVG Point/Day: <b class="text-orange-500">{{ $angka($total->avg_point) }}</b></span>
    </div>
</div>

@forelse ($tim as $t)
    <section class="mb-4">
        <div class="flex items-center gap-2 border-l-2 border-blue-600 bg-blue-50/40 px-2 py-1.5 text-sm">
            <x-ui.atoms.icon name="user" class="w-4 h-4 text-blue-600" />
            <span class="font-semibold">{{ $t->nama }}</span>
            <span class="text-xs text-gray-500">AVG Visit / Day: <b class="text-gray-700">{{ $angka($t->avg_visit) }}</b></span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table text-xs">
                <thead>
                    <tr>
                        <th scope="col" rowspan="2">No</th>
                        <th scope="col" rowspan="2">Nama Sales</th>
                        <th scope="col" rowspan="2" class="text-center">Status</th>
                        <th scope="colgroup" colspan="6" class="text-center">Total</th>
                        <th scope="colgroup" colspan="5" class="text-center">Rata-rata / Hari Kerja</th>
                    </tr>
                    <tr>
                        <th scope="col" class="text-center">Hari Kerja</th>
                        <th scope="col" class="text-center">Call</th>
                        <th scope="col" class="text-center">EC</th>
                        <th scope="col" class="text-end">Sales</th>
                        <th scope="col" class="text-end">SKU</th>
                        <th scope="col" class="text-center">Point</th>
                        <th scope="col" class="text-end">AVG Call</th>
                        <th scope="col" class="text-end">AVG EC</th>
                        <th scope="col" class="text-end">AVG Sales</th>
                        <th scope="col" class="text-center">AVG IPT</th>
                        <th scope="col" class="text-end">AVG Point</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($t->sales as $i => $s)
                        <tr @unless ($s->aktif) class="text-gray-400" @endunless>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $s->nama }}</td>
                            <td class="text-center"><x-ui.atoms.badge :variant="$s->aktif ? 'success' : 'secondary'">{{ $s->aktif ? 'Aktif' : 'Non-aktif' }}</x-ui.atoms.badge></td>
                            <td class="text-center"><x-ui.atoms.badge>{{ $s->hari_kerja }}</x-ui.atoms.badge></td>
                            <td class="text-center"><x-ui.atoms.badge variant="primary">{{ $bulat($s->call) }}</x-ui.atoms.badge></td>
                            <td class="text-center"><x-ui.atoms.badge variant="success">{{ $bulat($s->ec) }}</x-ui.atoms.badge></td>
                            <td class="text-end whitespace-nowrap">{{ $rp($s->sales) }}</td>
                            <td class="text-end">{{ $bulat($s->sku) }}</td>
                            <td class="text-center"><x-ui.atoms.badge variant="warning">{{ $bulat($s->point) }}</x-ui.atoms.badge></td>
                            <td class="text-end">{{ $angka($s->avg_call) }}</td>
                            <td class="text-end">{{ $angka($s->avg_ec) }}</td>
                            <td class="text-end whitespace-nowrap">{{ $rp($s->avg_sales) }}</td>
                            <td class="text-center"><x-ui.atoms.badge variant="warning">{{ $angka($s->avg_ipt) }}</x-ui.atoms.badge></td>
                            <td class="text-end">{{ $angka($s->avg_point) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="text-center text-gray-500">Tidak ada sales di tim ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php($a = $t->akumulasi)
                    <tr class="font-semibold bg-blue-50">
                        <th scope="row" colspan="3" class="text-center">Akumulasi</th>
                        <td class="text-center"><x-ui.atoms.badge>{{ $bulat($a->hari_kerja) }}</x-ui.atoms.badge></td>
                        <td class="text-center"><x-ui.atoms.badge variant="primary">{{ $bulat($a->call) }}</x-ui.atoms.badge></td>
                        <td class="text-center"><x-ui.atoms.badge variant="success">{{ $bulat($a->ec) }}</x-ui.atoms.badge></td>
                        <td class="text-end whitespace-nowrap">{{ $rp($a->sales) }}</td>
                        <td class="text-end">{{ $bulat($a->sku) }}</td>
                        <td class="text-center"><x-ui.atoms.badge variant="warning">{{ $bulat($a->point) }}</x-ui.atoms.badge></td>
                        <td class="text-end">{{ $angka($a->avg_call) }}</td>
                        <td class="text-end">{{ $angka($a->avg_ec) }}</td>
                        <td class="text-end whitespace-nowrap">{{ $rp($a->avg_sales) }}</td>
                        <td class="text-center"><x-ui.atoms.badge variant="warning">{{ $angka($a->avg_ipt) }}</x-ui.atoms.badge></td>
                        <td class="text-end">{{ $angka($a->avg_point) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@empty
    <p class="text-gray-500">Belum ada sales motoris di depo ini.</p>
@endforelse
