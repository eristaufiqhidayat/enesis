@use('App\Support\Motoris\SummaryDepo')
@php
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $bulat = fn ($n) => number_format((float) $n, 0, ',', '.');
    $rp = fn ($n) => 'Rp '.$bulat($n);
    $tgl = fn ($t, $f = 'd F Y') => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat($f);
    // Warna achievement sama dengan Marketama: ≥ 100% hijau, 75–<100% kuning, < 75% merah.
    $warnaCapai = fn ($n) => $n >= 100 ? 'text-green-500' : ($n >= 75 ? 'text-yellow-600' : 'text-rose-500');
    $periode = $summary->dari === $summary->sampai ? $tgl($summary->dari, 'l, d F Y') : $tgl($summary->dari).' – '.$tgl($summary->sampai);
    $tanggal = ['date_from' => $filter['date_from'], 'date_to' => $filter['date_to']];
    $target = $summary->target;
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h5 class="mb-0 font-semibold text-lg flex items-center gap-2">
                <x-ui.atoms.icon name="chart-pie" class="w-5 h-5 text-blue-600" /> {{ $judul }}
            </h5>
            <p class="mb-0 text-xs text-gray-500">{{ $periode }}</p>
        </div>
        <form method="GET" id="filter-summary" class="flex flex-wrap items-center gap-2" role="search">
            <x-ui.atoms.input type="date" name="date_from" value="{{ $filter['date_from'] }}" aria-label="Dari tanggal" class="w-36" data-kirim-otomatis />
            <x-ui.atoms.input type="date" name="date_to" value="{{ $filter['date_to'] }}" aria-label="Sampai tanggal" class="w-36" data-kirim-otomatis />
            <x-ui.atoms.button data-modal-open="modal-grafik">
                <x-ui.atoms.icon name="chart-bar" class="w-3.5 h-3.5" /> Chart
            </x-ui.atoms.button>
            <x-ui.atoms.input type="search" name="q" value="{{ $filter['q'] }}" placeholder="Cari depo…" aria-label="Cari depo" class="w-40" id="cari-depo" />
            @if ($bolehUbah)
                <x-ui.atoms.button variant="outline-primary" data-modal-open="modal-target">
                    <x-ui.atoms.icon name="settings" class="w-3.5 h-3.5" /> Setting Target
                </x-ui.atoms.button>
            @endif
        </form>
    </div>

    <x-ui.molecules.flash />

    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6" id="daftar-kartu">
        @forelse ($kartu as $k)
            @php
                $urlDetail = route('summary-depo.detail', ['depo' => $k->id] + $tanggal);
                $urlJual = route('summary-depo.penjualan', ['depo' => $k->id] + $tanggal);
                $baris = [
                    'Personil aktif' => [
                        ['user', 'Total SK', $bulat($k->total_sk), 'text-gray-900'],
                        ['bike', 'Total Sales Motoris', $bulat($k->total_motoris), 'text-gray-900'],
                    ],
                    'Rata-rata / hari kerja' => [
                        [null, 'AVG Hari Kerja', $angka($k->avg_hari_kerja), 'text-gray-900'],
                        [null, 'AVG Call / Day', $angka($k->avg_call), 'text-sky-500'],
                        [null, 'Call Achievement', $angka($k->call_achievement).'%', $warnaCapai($k->call_achievement)],
                        [null, 'AVG EC / Day', $angka($k->avg_ec), 'text-green-500'],
                        [null, 'EC Achievement', $angka($k->ec_achievement).'%', $warnaCapai($k->ec_achievement)],
                        [null, 'AVG Sales / Day', $rp($k->avg_sales), 'text-green-500'],
                        [null, 'AVG IPT / Day', $angka($k->avg_ipt), 'text-gray-900'],
                        [null, 'IPT Achievement', $angka($k->ipt_achievement).'%', $warnaCapai($k->ipt_achievement)],
                        [null, 'AVG Point / Day', $angka($k->avg_point), 'text-orange-500'],
                    ],
                    'Kunjungan SK' => [
                        ['user-check', 'SK yang Visit', $bulat($k->sk_visit), 'text-sky-500'],
                        ['user', 'Total Visit SK', $bulat($k->visit_sk), 'text-sky-500'],
                        [null, 'AVG Visit SK / Day', $angka($k->avg_visit_sk), 'text-sky-500'],
                    ],
                ];
            @endphp
            <x-ui.molecules.card class="shadow-sm text-xs" header-class="card-header-primary" body-class="px-3 pb-1" data-nama="{{ mb_strtolower($k->nama) }}">
                <x-slot:header>
                    <a href="{{ $urlDetail }}" class="flex items-center justify-between gap-2" data-muat="{{ $urlDetail }}" data-judul="{{ $k->nama }}">
                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 font-semibold text-sm">
                                <x-ui.atoms.icon name="warehouse" class="w-4 h-4 shrink-0" /> <span class="truncate">{{ $k->nama }}</span>
                            </span>
                            <span class="block truncate text-[0.7rem] uppercase text-white/85">{{ $k->distributor ?? '-' }}</span>
                        </span>
                        <x-ui.atoms.icon name="chevron-right" class="w-4 h-4 shrink-0" />
                    </a>
                </x-slot:header>

                @foreach ($baris as $judulBagian => $isi)
                    @if ($judulBagian === 'Rata-rata / hari kerja')
                        <div class="stat-label">Aktivitas periode</div>
                        <div class="stat-row">
                            <span class="flex items-center gap-1 text-gray-600"><x-ui.atoms.icon name="banknote" class="w-3.5 h-3.5" /> Total Sales</span>
                            <a href="{{ $urlJual }}" class="font-semibold text-green-500 underline decoration-dotted underline-offset-2"
                                data-muat="{{ $urlJual }}" data-judul="Detail EC &amp; Penjualan — {{ $k->nama }}" title="Klik untuk lihat data EC & penjualan">{{ $rp($k->sales) }}</a>
                        </div>
                    @endif
                    <div class="stat-label">{{ $judulBagian }}</div>
                    @foreach ($isi as [$ikon, $label, $nilai, $warna])
                        <div class="stat-row">
                            <span class="flex items-center gap-1 text-gray-600">
                                @if ($ikon) <x-ui.atoms.icon :name="$ikon" class="w-3.5 h-3.5" /> @endif {{ $label }}
                            </span>
                            <span class="font-semibold {{ $warna }}">{{ $nilai }}</span>
                        </div>
                    @endforeach
                @endforeach

                <x-slot:footer>
                    <div class="text-end">
                        <x-ui.atoms.button tag="a" variant="outline-primary" href="{{ $urlDetail }}" data-muat="{{ $urlDetail }}" data-judul="{{ $k->nama }}">
                            <x-ui.atoms.icon name="users" class="w-3.5 h-3.5" /> Lihat Detail
                        </x-ui.atoms.button>
                    </div>
                </x-slot:footer>
            </x-ui.molecules.card>
        @empty
            <p class="text-gray-500 col-span-full">Tidak ada depo untuk filter ini.</p>
        @endforelse
    </div>
    <p class="hidden text-gray-500 mt-2" id="kartu-kosong">Tidak ada depo yang cocok.</p>

    <p class="mt-3 text-xs text-gray-500">
        Dihitung dari data aplikasi motoris, diurutkan dari EC Achievement tertinggi. Hari kerja sales = tanggal dengan absen atau kunjungan;
        AVG per hari = total ÷ hari kerja depo; AVG IPT = SKU ÷ EC; AVG Hari Kerja = hari kerja depo ÷ sales motoris aktif.
        Achievement = AVG ÷ target nasional (Call {{ $angka($target['call']) }}, EC {{ $angka($target['ec']) }}, IPT {{ $angka($target['ipt']) }}).
        AVG Visit SK/Day = join visit ÷ {{ $summary->hkKoordinator }} hari kerja koordinator (Senin–Jumat, tanpa libur).
    </p>

    {{-- Modal detail depo & detail EC/penjualan: isinya dimuat dari server (potongan HTML). --}}
    <x-ui.molecules.modal id="modal-depo" data-modal role="dialog" aria-modal="true" aria-labelledby="modal-depo-judul">
        <div class="modal-content modal-content-xl">
            <div class="modal-header modal-header-primary">
                <div>
                    <h6 id="modal-depo-judul" class="mb-0 font-semibold flex items-center gap-2"><x-ui.atoms.icon name="warehouse" class="w-4 h-4" /> <span></span></h6>
                    <div class="text-[0.7rem] text-white/80">Periode: {{ $periode }}</div>
                </div>
                <button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body text-sm" id="modal-depo-isi"></div>
        </div>
    </x-ui.molecules.modal>

    <x-ui.molecules.modal id="modal-grafik" data-modal role="dialog" aria-modal="true" aria-labelledby="modal-grafik-judul">
        <div class="modal-content modal-content-xl">
            <div class="modal-header modal-header-primary">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <h6 id="modal-grafik-judul" class="mb-0 font-semibold flex items-center gap-2"><x-ui.atoms.icon name="chart-bar" class="w-4 h-4" /> Chart Per Depo</h6>
                        <div class="text-[0.7rem] text-white/80">{{ $periode }}</div>
                    </div>
                    <x-ui.atoms.select id="metrik-grafik" aria-label="Metrik grafik" class="w-56 text-gray-900">
                        @foreach (SummaryDepo::METRIK_GRAFIK as $kunci => $label)
                            <option value="{{ $kunci }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.atoms.select>
                </div>
                <button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body"><div id="isi-grafik" class="overflow-x-auto"></div></div>
        </div>
    </x-ui.molecules.modal>

    @if ($bolehUbah)
        <x-ui.molecules.modal id="modal-target" data-modal role="dialog" aria-modal="true" aria-labelledby="modal-target-judul">
            <form method="POST" action="{{ route('summary-depo.target') }}" class="modal-content">
                @csrf
                @foreach ($filter as $k => $v)
                    @if ($v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <div class="modal-header">
                    <h6 id="modal-target-judul" class="mb-0 font-semibold">Setting Target</h6>
                    <button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body space-y-3">
                    <p class="text-xs text-gray-500">Nilai ini berlaku sebagai target nasional (sama untuk semua depo aktif).</p>
                    @foreach (['call' => 'Target Call', 'ec' => 'Target EC', 'ipt' => 'Target IPT'] as $kunci => $label)
                        <div>
                            <x-ui.atoms.label for="target_{{ $kunci }}">{{ $label }}</x-ui.atoms.label>
                            <x-ui.atoms.input type="number" id="target_{{ $kunci }}" name="{{ $kunci }}" step="0.1" min="0.1" value="{{ $target[$kunci] + 0 }}" required />
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Batal</x-ui.atoms.button>
                    <x-ui.atoms.button type="submit">Simpan</x-ui.atoms.button>
                </div>
            </form>
        </x-ui.molecules.modal>
    @endif

    <script type="application/json" id="data-grafik">@json($kartu->map(fn ($k) => ['nama' => $k->nama] + array_intersect_key((array) $k, SummaryDepo::METRIK_GRAFIK))->values())</script>
@endsection

@push('scripts')
    <script>
        (() => {
            // Tanggal berubah → muat ulang halaman dengan rentang baru.
            const form = document.getElementById('filter-summary');
            form.querySelectorAll('[data-kirim-otomatis]').forEach((el) => el.addEventListener('change', () => form.requestSubmit()));

            // Cari depo langsung di kartu yang tampil (Enter tetap mengirim ke server).
            const cari = document.getElementById('cari-depo');
            const kartu = [...document.querySelectorAll('#daftar-kartu [data-nama]')];
            const saring = () => {
                const kata = cari.value.trim().toLowerCase();
                let tampil = 0;
                kartu.forEach((k) => { const cocok = !kata || k.dataset.nama.includes(kata); k.classList.toggle('hidden', !cocok); tampil += cocok; });
                document.getElementById('kartu-kosong').classList.toggle('hidden', tampil > 0 || kartu.length === 0);
            };
            cari.addEventListener('input', saring);

            // Modal detail depo / detail EC & penjualan: muat potongan HTML; tautan & form [data-fragmen] di dalamnya dimuat di tempat.
            const modal = document.getElementById('modal-depo');
            const isi = document.getElementById('modal-depo-isi');
            const judul = modal.querySelector('#modal-depo-judul span');
            const muat = async (url) => {
                const u = new URL(url, location.href);
                u.searchParams.set('partial', '1');
                isi.innerHTML = '<div class="py-6 text-center text-gray-500"><span class="spinner"></span> Memuat…</div>';
                try {
                    const res = await fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) throw new Error(res.status);
                    isi.innerHTML = await res.text();
                } catch (e) {
                    isi.innerHTML = '<div class="alert alert-danger">Data tidak bisa dimuat. Coba lagi.</div>';
                }
            };
            document.querySelectorAll('[data-muat]').forEach((el) => el.addEventListener('click', (e) => {
                e.preventDefault();
                judul.textContent = el.dataset.judul;
                modal.classList.remove('hidden');
                muat(el.dataset.muat);
            }));
            isi.addEventListener('click', (e) => {
                const a = e.target.closest('[data-fragmen] a[href]');
                if (a) { e.preventDefault(); muat(a.href); }
            });
            isi.addEventListener('submit', (e) => {
                const f = e.target.closest('form[data-fragmen]');
                if (!f) return;
                e.preventDefault();
                const u = new URL(f.action, location.href);
                new FormData(f).forEach((v, k) => u.searchParams.set(k, v));
                muat(u.toString());
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') document.querySelectorAll('.modal-container[data-modal]').forEach((m) => m.classList.add('hidden'));
            });

            // Grafik batang per depo (SVG sederhana, tanpa pustaka).
            const data = JSON.parse(document.getElementById('data-grafik').textContent);
            const pilih = document.getElementById('metrik-grafik');
            const wadah = document.getElementById('isi-grafik');
            const fmt = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 1 });
            const gambar = () => {
                const k = pilih.value;
                const lebarBatang = 40, jarak = 14, tinggi = 320, atas = 24, bawah = 90, kiri = 8;
                const maks = Math.max(1, ...data.map((d) => Number(d[k]) || 0));
                const lebar = kiri + data.length * (lebarBatang + jarak);
                const el = (tag, attr, teks) => {
                    const n = document.createElementNS('http://www.w3.org/2000/svg', tag);
                    Object.entries(attr).forEach(([a, v]) => n.setAttribute(a, v));
                    if (teks !== undefined) n.textContent = teks;
                    return n;
                };
                const svg = el('svg', { width: lebar, height: tinggi + atas + bawah, role: 'img', 'aria-label': pilih.selectedOptions[0].text + ' per depo' });
                svg.append(el('line', { x1: 0, x2: lebar, y1: atas + tinggi, y2: atas + tinggi, stroke: '#d1d5db' }));
                data.forEach((d, i) => {
                    const v = Number(d[k]) || 0;
                    const h = Math.round((v / maks) * tinggi);
                    const x = kiri + i * (lebarBatang + jarak);
                    const bar = el('rect', { x, y: atas + tinggi - h, width: lebarBatang, height: h, rx: 3, fill: '#4ade80', stroke: '#22c55e' });
                    bar.append(el('title', {}, `${d.nama}: ${fmt(v)}`));
                    svg.append(bar);
                    svg.append(el('text', { x: x + lebarBatang / 2, y: atas + tinggi - h - 6, 'text-anchor': 'middle', 'font-size': 10, 'font-weight': 600, fill: '#374151' }, fmt(v)));
                    svg.append(el('text', { x: x + lebarBatang / 2, y: atas + tinggi + 12, 'text-anchor': 'end', 'font-size': 10, fill: '#4b5563',
                        transform: `rotate(-35 ${x + lebarBatang / 2} ${atas + tinggi + 12})` }, d.nama));
                });
                wadah.replaceChildren(data.length ? svg : Object.assign(document.createElement('p'), { textContent: 'Tidak ada depo.', className: 'text-gray-500' }));
            };
            pilih.addEventListener('change', gambar);
            gambar();
        })();
    </script>
@endpush
