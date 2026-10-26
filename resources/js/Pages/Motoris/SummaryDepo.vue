<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import InfoPopover from '@/Components/ui/molecules/InfoPopover.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import StatLabel from '@/Components/ui/atoms/StatLabel.vue';
import StatRow from '@/Components/ui/molecules/StatRow.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import DetailDepoTim from './partials/DetailDepoTim.vue';
import KartuDepoSkeleton from './partials/KartuDepoSkeleton.vue';
import SummaryDepoTabel from './partials/SummaryDepoTabel.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import { angka, angkaRingkas, tanggalPendek } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';
import { rupiah, warnaCapai } from '@/Support/summaryDepo.js';
import { useAmbilData } from '@/Support/ambilData.js';

const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    periode: { type: Object, required: true },
    // Kosong saat masih ditunda (lihat <Deferred> di bawah).
    kartu: { type: Array, default: () => [] },
    target: { type: Object, required: true },
    metrikGrafik: { type: Array, required: true },
    filter: { type: Object, required: true },
    bolehUbah: { type: Boolean, default: false },
});

const appName = usePage().props.appName;

const subtitle = computed(() => (props.periode.sama ? props.periode.dari : `${props.periode.dari} – ${props.periode.sampai}`));

// Grafik dibaca dari kartu, jadi tombol Grafik baru aktif setelah kartu tiba (prop ditunda belum ada di page.props).
const kartuDimuat = computed(() => Object.hasOwn(usePage().props, 'kartu'));

// Tampilan kartu (desain asli Marketama) atau tabel; dibawa di query ?tampilan= supaya bisa di-bookmark.
const TAB_TAMPILAN = [{ key: 'kartu', label: 'Kartu' }, { key: 'tabel', label: 'Tabel' }];
const tampilan = ref(props.filter.tampilan);

// Ganti tampilan hanya mengubah query tampilan; tanggal yang belum dikirim lewat tombol Tampilkan tidak ikut terkirim.
function pindahTampilan(key) {
    tampilan.value = key;
    router.get('/summary_all_depo', bersihkanQuery({
        date_from: props.filter.date_from, date_to: props.filter.date_to, q: props.filter.q,
        tampilan: key === 'tabel' ? 'tabel' : null,
    }), { only: ['filter'], preserveState: true, preserveScroll: true, replace: true });
}

function queryFilter() {
    return bersihkanQuery({
        date_from: filterDari.value, date_to: filterSampai.value, q: cari.value,
        tampilan: tampilan.value === 'tabel' ? 'tabel' : null,
    });
}

function urlDetail(k) {
    return `/summary_all_depo/${k.id}?${new URLSearchParams(bersihkanQuery({ date_from: filterDari.value, date_to: filterSampai.value }))}`;
}

function urlPenjualan(k) {
    return `/summary_all_depo/${k.id}/penjualan?${new URLSearchParams(bersihkanQuery({ date_from: filterDari.value, date_to: filterSampai.value }))}`;
}

// "Lihat Detail" dan "Total Sales" dibuka sebagai modal (isi dimuat lewat fetch JSON ke rute yang sama
// dengan halaman penuhnya - lihat SummaryDepoController@detail / penjualan), bukan navigasi Inertia, supaya
// tidak pindah halaman. Halaman penuh tetap ada untuk kunjungan langsung/bookmark.
async function ambilJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!res.ok) {
        throw new Error(`Gagal memuat data (${res.status}).`);
    }

    return res.json();
}

const detailData = ref(null);
const detailMemuat = ref(false);

async function bukaDetail(k) {
    modal.value = 'detail';
    detailData.value = null;
    detailMemuat.value = true;
    detailData.value = await ambilJson(urlDetail(k));
    detailMemuat.value = false;
}

const penjualanData = ref(null);
const penjualanMemuat = ref(false);
const penjualanCari = ref('');

async function bukaPenjualan(k) {
    modal.value = 'penjualan';
    penjualanData.value = null;
    penjualanCari.value = '';
    penjualanMemuat.value = true;
    penjualanData.value = await ambilJson(urlPenjualan(k));
    penjualanMemuat.value = false;
}

async function muatPenjualan(url) {
    if (!url) {
        return;
    }

    penjualanMemuat.value = true;
    penjualanData.value = await ambilJson(url);
    penjualanMemuat.value = false;
}

function cariPenjualan() {
    const q = `&q=${encodeURIComponent(penjualanCari.value.trim())}`;

    muatPenjualan(`${urlPenjualan(penjualanData.value.depo)}${q}`);
}

const periodeTeks = (p) => (p.sama ? p.dari : `${p.dari} – ${p.sampai}`);

// Tanggal dan pencarian baru dimuat saat tombol Tampilkan diklik; filter yang sedang tampil dibawa lewat props.filter.
const filterDari = ref(props.filter.date_from);
const filterSampai = ref(props.filter.date_to);

// Hanya prop yang berubah oleh filter yang dimuat ulang, supaya kartu lama tetap tampil (tanpa skeleton).
// Tombol Tampilkan aktif lagi hanya saat tanggal atau pencarian berbeda dari yang sedang tampil (seperti Rekap SKU).
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ date_from: filterDari.value, date_to: filterSampai.value, q: cari.value }),
    diterapkan: () => ({ date_from: props.filter.date_from, date_to: props.filter.date_to, q: props.filter.q }),
});

function tampilkan() {
    ambil('/summary_all_depo', queryFilter(), {
        only: ['kartu', 'periode', 'filter'],
    });
}

// Cari depo langsung di kartu yang sedang tampil (tidak perlu round-trip ke server tiap ketik).
const cari = ref(props.filter.q);
const kartuTampil = computed(() => {
    const kata = cari.value.trim().toLowerCase();

    return kata ? props.kartu.filter((k) => `${k.kode ?? ''} ${k.nama}`.toLowerCase().includes(kata)) : props.kartu;
});

const modal = ref(null);
const tutup = () => { modal.value = null; detailData.value = null; penjualanData.value = null; };

// Grafik batang per depo (SVG sederhana, tanpa pustaka), dari kartu yang sedang tampil di server (sesuai filter q saat dimuat).
const LEBAR_BATANG = 40, JARAK = 14, TINGGI = 320, ATAS = 24, BAWAH = 90, KIRI = 8;
const metrik = ref(props.metrikGrafik[0]?.key ?? '');
const opsiMetrik = computed(() => props.metrikGrafik.map((m) => ({ value: m.key, label: m.label })));
const metrikLabel = computed(() => props.metrikGrafik.find((m) => m.key === metrik.value)?.label ?? '');
const grafikLebar = computed(() => KIRI + props.kartu.length * (LEBAR_BATANG + JARAK));
const grafikBars = computed(() => {
    const maks = Math.max(1, ...props.kartu.map((d) => Number(d[metrik.value]) || 0));

    return props.kartu.map((d, i) => {
        const v = Number(d[metrik.value]) || 0;
        const h = Math.round((v / maks) * TINGGI);

        return { nama: d.nama, v, x: KIRI + i * (LEBAR_BATANG + JARAK), y: ATAS + TINGGI - h, h };
    });
});

const formTarget = useForm({ call: props.target.call, ec: props.target.ec, ipt: props.target.ipt });

function simpanTarget() {
    formTarget.transform((data) => ({ ...data, date_from: filterDari.value, date_to: filterSampai.value, q: cari.value }))
        .post('/summary_all_depo/target', { preserveScroll: true, onSuccess: tutup });
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="subtitle">
            <template #info>
                <InfoPopover title="Cara Hitung" class="ms-1 align-middle">
                    <ul class="list-disc ps-4 space-y-1">
                        <li>Dihitung dari data aplikasi motoris, diurutkan dari EC Achievement tertinggi.</li>
                        <li><strong>Hari Kerja</strong> sales = tanggal dengan absen atau kunjungan.</li>
                        <li><strong>AVG</strong> per hari = total ÷ hari kerja depo.</li>
                        <li><strong>AVG IPT</strong> = SKU ÷ EC.</li>
                        <li><strong>AVG Hari Kerja</strong> = hari kerja depo ÷ sales motoris aktif.</li>
                        <li><strong>Achievement</strong> = AVG ÷ target nasional (Call {{ angkaRingkas(target.call, 1) }}, EC {{ angkaRingkas(target.ec, 1) }}, IPT {{ angkaRingkas(target.ipt, 1) }}).</li>
                        <li><strong>AVG Visit SK/Day</strong> = join visit ÷ {{ periode.hkKoordinator }} hari kerja koordinator (Senin–Jumat, tanpa libur).</li>
                    </ul>
                </InfoPopover>
            </template>
            <template #actions>
                <form class="flex flex-wrap items-center gap-2" role="search" @submit.prevent="tampilkan">
                    <Input id="date_from" v-model="filterDari" type="date" aria-label="Dari tanggal" class="w-36" />
                    <Input id="date_to" v-model="filterSampai" type="date" aria-label="Sampai tanggal" class="w-36" />
                    <Input id="cari-depo" v-model="cari" type="search" placeholder="Cari depo…" aria-label="Cari depo" class="w-40" />
                    <Button type="submit" :loading="memuat" :disabled="!kartuDimuat || !berubah"><Icon name="search" class="me-1 w-3.5 h-3.5" />Tampilkan</Button>
                </form>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <Tabs :tabs="TAB_TAMPILAN" :model-value="tampilan" @update:model-value="pindahTampilan" />
            <div class="mb-3 flex items-center gap-2">
                <Button type="button" variant="outline-secondary" :disabled="!kartuDimuat" @click="modal = 'grafik'"><Icon name="chart-bar" class="me-1 w-3.5 h-3.5" />Grafik</Button>
                <Button v-if="bolehUbah" type="button" variant="outline-secondary" @click="modal = 'target'"><Icon name="settings" class="me-1 w-3.5 h-3.5" />Atur Target</Button>
            </div>
        </div>

        <Deferred data="kartu">
        <template #fallback>
            <KartuDepoSkeleton />
        </template>
        <SummaryDepoTabel
            v-if="tampilan === 'tabel'"
            :kartu="kartuTampil" :target="target" :hk-koordinator="periode.hkKoordinator"
            :buka-detail="bukaDetail" :buka-penjualan="bukaPenjualan"
        />
        <div v-else class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            <Card v-for="k in kartuTampil" :key="k.id" class="shadow-sm text-xs" header-class="card-header-primary" body-class="px-3 pb-1">
                <template #header>
                    <button type="button" class="flex items-center justify-between gap-2 w-full text-left" @click="bukaDetail(k)">
                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 font-semibold text-sm">
                                <Icon name="warehouse" class="w-4 h-4 shrink-0" /> <span class="truncate">{{ k.nama }}</span>
                            </span>
                            <span class="block truncate text-[0.7rem] uppercase text-white/85">{{ k.distributor ?? '-' }}</span>
                        </span>
                        <Icon name="chevron-right" class="w-4 h-4 shrink-0" />
                    </button>
                </template>

                <StatLabel>Personil aktif</StatLabel>
                <StatRow>
                    <template #label><Icon name="user" class="me-1 w-3.5 h-3.5" />Total SK</template>
                    {{ angka(k.total_sk) }}
                </StatRow>
                <StatRow>
                    <template #label><Icon name="bike" class="me-1 w-3.5 h-3.5" />Total Sales Motoris</template>
                    {{ angka(k.total_motoris) }}
                </StatRow>

                <StatLabel>Aktivitas periode</StatLabel>
                <StatRow value-class="text-green-500">
                    <template #label><Icon name="banknote" class="me-1 w-3.5 h-3.5" />Total Sales</template>
                    <button type="button" class="underline decoration-dotted underline-offset-2" title="Klik untuk lihat data EC & penjualan" @click="bukaPenjualan(k)">{{ rupiah(k.sales) }}</button>
                </StatRow>

                <StatLabel>Rata-rata / hari kerja</StatLabel>
                <StatRow label="AVG Hari Kerja">{{ angkaRingkas(k.avg_hari_kerja, 1) }}</StatRow>
                <StatRow label="AVG Call / Day" value-class="text-sky-500">{{ angkaRingkas(k.avg_call, 1) }}</StatRow>
                <StatRow label="Call Achievement" :value-class="warnaCapai(k.call_achievement)">{{ angkaRingkas(k.call_achievement, 1) }}%</StatRow>
                <StatRow label="AVG EC / Day" value-class="text-green-500">{{ angkaRingkas(k.avg_ec, 1) }}</StatRow>
                <StatRow label="EC Achievement" :value-class="warnaCapai(k.ec_achievement)">{{ angkaRingkas(k.ec_achievement, 1) }}%</StatRow>
                <StatRow label="AVG Sales / Day" value-class="text-green-500">{{ rupiah(k.avg_sales) }}</StatRow>
                <StatRow label="AVG IPT / Day">{{ angkaRingkas(k.avg_ipt, 1) }}</StatRow>
                <StatRow label="IPT Achievement" :value-class="warnaCapai(k.ipt_achievement)">{{ angkaRingkas(k.ipt_achievement, 1) }}%</StatRow>
                <StatRow label="AVG Point / Day" value-class="text-orange-500">{{ angkaRingkas(k.avg_point, 1) }}</StatRow>

                <StatLabel>Kunjungan SK</StatLabel>
                <StatRow value-class="text-sky-500">
                    <template #label><Icon name="user-check" class="me-1 w-3.5 h-3.5" />SK yang Visit</template>
                    {{ angka(k.sk_visit) }}
                </StatRow>
                <StatRow value-class="text-sky-500">
                    <template #label><Icon name="user" class="me-1 w-3.5 h-3.5" />Total Visit SK</template>
                    {{ angka(k.visit_sk) }}
                </StatRow>
                <StatRow label="AVG Visit SK / Day" value-class="text-sky-500">{{ angkaRingkas(k.avg_visit_sk, 1) }}</StatRow>

                <template #footer>
                    <div class="text-end">
                        <Button variant="outline-primary" @click="bukaDetail(k)"><Icon name="users" class="me-1 w-3.5 h-3.5" />Lihat Detail</Button>
                    </div>
                </template>
            </Card>
            <p v-if="!kartuTampil.length" class="text-gray-500 col-span-full">{{ kartu.length ? 'Tidak ada depo yang cocok.' : 'Tidak ada depo untuk filter ini.' }}</p>
        </div>
        </Deferred>

        <Modal :show="modal === 'detail'" size="xl" :title="detailData ? detailData.judul : 'Detail Depo'" @close="tutup">
            <TableSkeleton v-if="detailMemuat" />
            <template v-else-if="detailData">
                <div class="text-xs text-gray-500 mb-2">Periode: <b class="text-gray-700">{{ periodeTeks(detailData.periode) }}</b></div>
                <DetailDepoTim :total="detailData.total" :tim="detailData.tim" />
            </template>
        </Modal>

        <Modal :show="modal === 'penjualan'" size="xl" :title="penjualanData ? penjualanData.judul : 'Detail EC & Penjualan'" @close="tutup">
            <TableSkeleton v-if="penjualanMemuat && !penjualanData" />
            <template v-else-if="penjualanData">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="text-xs text-gray-500">Periode: <b class="text-gray-700">{{ periodeTeks(penjualanData.periode) }}</b></div>
                    <form class="flex gap-2" role="search" @submit.prevent="cariPenjualan">
                        <Input v-model="penjualanCari" type="search" placeholder="Cari outlet atau sales…" aria-label="Cari outlet atau sales" class="max-w-xs" />
                        <Button type="submit" variant="outline-secondary" :loading="penjualanMemuat">Cari</Button>
                    </form>
                </div>
                <div class="flex flex-wrap gap-1.5 mb-2">
                    <Badge variant="success">Total EC: {{ angka(penjualanData.total.ec) }}</Badge>
                    <Badge variant="primary">Total Sales: {{ rupiah(penjualanData.total.sales) }}</Badge>
                    <Badge variant="warning">Total SKU: {{ angka(penjualanData.total.sku) }}</Badge>
                    <Badge variant="warning">Total Point: {{ angka(penjualanData.total.point) }}</Badge>
                </div>
                <div class="overflow-auto" style="max-height: 50vh;">
                    <table class="data-table text-xs">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Kode Outlet</th>
                                <th scope="col">Nama Outlet</th>
                                <th scope="col">Sales</th>
                                <th scope="col">EC?</th>
                                <th scope="col" class="text-end">Total Sales</th>
                                <th scope="col" class="text-end">SKU</th>
                                <th scope="col" class="text-end">Point</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(b, i) in penjualanData.baris.data" :key="i">
                                <td>{{ tanggalPendek(b.tanggal) }}</td>
                                <td>{{ b.kode_outlet ?? '-' }}</td>
                                <td>{{ b.nama_outlet ?? '-' }}</td>
                                <td>{{ b.sales }}</td>
                                <td><Badge :variant="b.is_effective_call ? 'success' : 'secondary'">{{ b.is_effective_call ? 'EC' : '-' }}</Badge></td>
                                <td class="text-end">{{ rupiah(b.total) }}</td>
                                <td class="text-end">{{ angka(b.jumlah_sku) }}</td>
                                <td class="text-end">{{ angka(b.poin) }}</td>
                            </tr>
                            <tr v-if="!penjualanData.baris.data.length">
                                <td colspan="8" class="text-center text-gray-500 py-4">Tidak ada transaksi pada periode ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <small class="text-gray-500">Total {{ angka(penjualanData.baris.total) }} data</small>
                    <nav>
                        <ul class="pagination mb-0">
                            <li v-for="(link, i) in penjualanData.baris.links" :key="i" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                                <button v-if="link.url" type="button" class="page-link" v-html="link.label" @click="muatPenjualan(link.url)"></button>
                                <span v-else class="page-link" v-html="link.label"></span>
                            </li>
                        </ul>
                    </nav>
                </div>
            </template>
        </Modal>

        <Modal :show="modal === 'grafik'" title="Grafik Per Depo" @close="tutup">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <Label for="metrik-grafik" class="mb-0">Metrik</Label>
                <Select id="metrik-grafik" v-model="metrik" :options="opsiMetrik" aria-label="Metrik grafik" class="w-56" />
            </div>
            <div class="overflow-x-auto">
                <svg v-if="grafikBars.length" :width="grafikLebar" :height="TINGGI + ATAS + BAWAH" role="img" :aria-label="`${metrikLabel} per depo`">
                    <line :x1="0" :x2="grafikLebar" :y1="ATAS + TINGGI" :y2="ATAS + TINGGI" class="stroke-gray-300" />
                    <g v-for="b in grafikBars" :key="b.nama">
                        <rect :x="b.x" :y="b.y" :width="LEBAR_BATANG" :height="b.h" rx="3" class="fill-green-400 stroke-green-500"><title>{{ b.nama }}: {{ angkaRingkas(b.v, 1) }}</title></rect>
                        <text :x="b.x + LEBAR_BATANG / 2" :y="b.y - 6" text-anchor="middle" font-size="10" font-weight="600" class="fill-gray-700">{{ angkaRingkas(b.v, 1) }}</text>
                        <text :x="b.x + LEBAR_BATANG / 2" :y="ATAS + TINGGI + 12" text-anchor="end" font-size="10" class="fill-gray-600" :transform="`rotate(-35 ${b.x + LEBAR_BATANG / 2} ${ATAS + TINGGI + 12})`">{{ b.nama }}</text>
                    </g>
                </svg>
                <p v-else class="text-gray-500">Tidak ada depo.</p>
            </div>
        </Modal>

        <Modal v-if="bolehUbah" :show="modal === 'target'" title="Atur Target" @close="tutup">
            <form @submit.prevent="simpanTarget">
                <p class="text-xs text-gray-500">Nilai ini berlaku sebagai target nasional (sama untuk semua depo aktif).</p>
                <div v-for="(label, kunci) in { call: 'Target Call', ec: 'Target EC', ipt: 'Target IPT' }" :key="kunci" class="mb-3">
                    <Label :for="`target_${kunci}`">{{ label }}</Label>
                    <Input :id="`target_${kunci}`" v-model="formTarget[kunci]" type="number" step="0.1" min="0.1" />
                    <FieldError v-if="formTarget.errors[kunci]">{{ formTarget.errors[kunci] }}</FieldError>
                </div>
                <div class="flex justify-end gap-2">
                    <Button variant="light" @click="tutup">Batal</Button>
                    <Button type="submit" :disabled="formTarget.processing">Simpan</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
