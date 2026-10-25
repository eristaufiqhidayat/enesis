<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import KartuDepoSkeleton from './partials/KartuDepoSkeleton.vue';
import { angka, angkaRingkas } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';

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

// Grafik dibaca dari kartu, jadi tombol Chart baru aktif setelah kartu tiba (prop ditunda belum ada di page.props).
const kartuDimuat = computed(() => Object.hasOwn(usePage().props, 'kartu'));

// Warna achievement sama dengan Marketama: ≥ 100% hijau, 75–<100% kuning, < 75% merah.
const warnaCapai = (n) => (n >= 100 ? 'text-green-500' : (n >= 75 ? 'text-yellow-600' : 'text-rose-500'));
const rupiah = (n) => `Rp ${angka(n)}`;

function urlDetail(k) {
    return `/summary_all_depo/${k.id}?${new URLSearchParams(bersihkanQuery({ date_from: filterDari.value, date_to: filterSampai.value }))}`;
}

function urlPenjualan(k) {
    return `/summary_all_depo/${k.id}/penjualan?${new URLSearchParams(bersihkanQuery({ date_from: filterDari.value, date_to: filterSampai.value }))}`;
}

// Tanggal berubah → muat ulang dengan rentang baru; q ikut dibawa.
const filterDari = ref(props.filter.date_from);
const filterSampai = ref(props.filter.date_to);

// Hanya prop yang berubah oleh filter yang dimuat ulang, supaya kartu lama tetap tampil (tanpa skeleton).
function tampilkan() {
    router.get('/summary_all_depo', bersihkanQuery({ date_from: filterDari.value, date_to: filterSampai.value, q: cari.value }), {
        only: ['kartu', 'periode', 'filter'],
    });
}

// Cari depo langsung di kartu yang sedang tampil (tidak perlu round-trip ke server tiap ketik).
const cari = ref(props.filter.q);
const kartuTampil = computed(() => {
    const kata = cari.value.trim().toLowerCase();

    return kata ? props.kartu.filter((k) => k.nama.toLowerCase().includes(kata)) : props.kartu;
});

const modal = ref(null);
const tutup = () => { modal.value = null; };

// Grafik batang per depo (SVG sederhana, tanpa pustaka), dari kartu yang sedang tampil di server (sesuai filter q saat dimuat).
const LEBAR_BATANG = 40, JARAK = 14, TINGGI = 320, ATAS = 24, BAWAH = 90, KIRI = 8;
const metrik = ref(props.metrikGrafik[0]?.key ?? '');
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
            <template #actions>
                <form class="flex flex-wrap items-center gap-2" role="search" @submit.prevent="tampilkan">
                    <Input id="date_from" v-model="filterDari" type="date" aria-label="Dari tanggal" class="w-36" @change="tampilkan" />
                    <Input id="date_to" v-model="filterSampai" type="date" aria-label="Sampai tanggal" class="w-36" @change="tampilkan" />
                    <Button type="button" :disabled="!kartuDimuat" @click="modal = 'grafik'"><Icon name="chart-bar" class="w-3.5 h-3.5" /> Chart</Button>
                    <Input id="cari-depo" v-model="cari" type="search" placeholder="Cari depo…" aria-label="Cari depo" class="w-40" />
                    <Button v-if="bolehUbah" type="button" variant="outline-primary" @click="modal = 'target'"><Icon name="settings" class="w-3.5 h-3.5" /> Setting Target</Button>
                </form>
            </template>
        </PageHeader>

        <Deferred data="kartu">
        <template #fallback>
            <KartuDepoSkeleton />
        </template>
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            <Card v-for="k in kartuTampil" :key="k.id" class="shadow-sm text-xs" header-class="card-header-primary" body-class="px-3 pb-1">
                <template #header>
                    <Link :href="urlDetail(k)" class="flex items-center justify-between gap-2">
                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 font-semibold text-sm">
                                <Icon name="warehouse" class="w-4 h-4 shrink-0" /> <span class="truncate">{{ k.nama }}</span>
                            </span>
                            <span class="block truncate text-[0.7rem] uppercase text-white/85">{{ k.distributor ?? '-' }}</span>
                        </span>
                        <Icon name="chevron-right" class="w-4 h-4 shrink-0" />
                    </Link>
                </template>

                <div class="stat-label">Personil aktif</div>
                <div class="stat-row">
                    <span class="flex items-center gap-1 text-gray-600"><Icon name="user" class="w-3.5 h-3.5" /> Total SK</span>
                    <span class="font-semibold text-gray-900">{{ angka(k.total_sk) }}</span>
                </div>
                <div class="stat-row">
                    <span class="flex items-center gap-1 text-gray-600"><Icon name="bike" class="w-3.5 h-3.5" /> Total Sales Motoris</span>
                    <span class="font-semibold text-gray-900">{{ angka(k.total_motoris) }}</span>
                </div>

                <div class="stat-label">Aktivitas periode</div>
                <div class="stat-row">
                    <span class="flex items-center gap-1 text-gray-600"><Icon name="banknote" class="w-3.5 h-3.5" /> Total Sales</span>
                    <Link :href="urlPenjualan(k)" class="font-semibold text-green-500 underline decoration-dotted underline-offset-2" title="Klik untuk lihat data EC & penjualan">{{ rupiah(k.sales) }}</Link>
                </div>

                <div class="stat-label">Rata-rata / hari kerja</div>
                <div class="stat-row"><span class="text-gray-600">AVG Hari Kerja</span><span class="font-semibold text-gray-900">{{ angkaRingkas(k.avg_hari_kerja, 1) }}</span></div>
                <div class="stat-row"><span class="text-gray-600">AVG Call / Day</span><span class="font-semibold text-sky-500">{{ angkaRingkas(k.avg_call, 1) }}</span></div>
                <div class="stat-row"><span class="text-gray-600">Call Achievement</span><span class="font-semibold" :class="warnaCapai(k.call_achievement)">{{ angkaRingkas(k.call_achievement, 1) }}%</span></div>
                <div class="stat-row"><span class="text-gray-600">AVG EC / Day</span><span class="font-semibold text-green-500">{{ angkaRingkas(k.avg_ec, 1) }}</span></div>
                <div class="stat-row"><span class="text-gray-600">EC Achievement</span><span class="font-semibold" :class="warnaCapai(k.ec_achievement)">{{ angkaRingkas(k.ec_achievement, 1) }}%</span></div>
                <div class="stat-row"><span class="text-gray-600">AVG Sales / Day</span><span class="font-semibold text-green-500">{{ rupiah(k.avg_sales) }}</span></div>
                <div class="stat-row"><span class="text-gray-600">AVG IPT / Day</span><span class="font-semibold text-gray-900">{{ angkaRingkas(k.avg_ipt, 1) }}</span></div>
                <div class="stat-row"><span class="text-gray-600">IPT Achievement</span><span class="font-semibold" :class="warnaCapai(k.ipt_achievement)">{{ angkaRingkas(k.ipt_achievement, 1) }}%</span></div>
                <div class="stat-row"><span class="text-gray-600">AVG Point / Day</span><span class="font-semibold text-orange-500">{{ angkaRingkas(k.avg_point, 1) }}</span></div>

                <div class="stat-label">Kunjungan SK</div>
                <div class="stat-row">
                    <span class="flex items-center gap-1 text-gray-600"><Icon name="user-check" class="w-3.5 h-3.5" /> SK yang Visit</span>
                    <span class="font-semibold text-sky-500">{{ angka(k.sk_visit) }}</span>
                </div>
                <div class="stat-row">
                    <span class="flex items-center gap-1 text-gray-600"><Icon name="user" class="w-3.5 h-3.5" /> Total Visit SK</span>
                    <span class="font-semibold text-sky-500">{{ angka(k.visit_sk) }}</span>
                </div>
                <div class="stat-row"><span class="text-gray-600">AVG Visit SK / Day</span><span class="font-semibold text-sky-500">{{ angkaRingkas(k.avg_visit_sk, 1) }}</span></div>

                <template #footer>
                    <div class="text-end">
                        <Button :tag="Link" variant="outline-primary" :href="urlDetail(k)"><Icon name="users" class="w-3.5 h-3.5" /> Lihat Detail</Button>
                    </div>
                </template>
            </Card>
            <p v-if="!kartuTampil.length" class="text-gray-500 col-span-full">{{ kartu.length ? 'Tidak ada depo yang cocok.' : 'Tidak ada depo untuk filter ini.' }}</p>
        </div>
        </Deferred>

        <p class="mt-3 text-xs text-gray-500">
            Dihitung dari data aplikasi motoris, diurutkan dari EC Achievement tertinggi. Hari kerja sales = tanggal dengan absen atau kunjungan;
            AVG per hari = total ÷ hari kerja depo; AVG IPT = SKU ÷ EC; AVG Hari Kerja = hari kerja depo ÷ sales motoris aktif.
            Achievement = AVG ÷ target nasional (Call {{ angkaRingkas(target.call, 1) }}, EC {{ angkaRingkas(target.ec, 1) }}, IPT {{ angkaRingkas(target.ipt, 1) }}).
            AVG Visit SK/Day = join visit ÷ {{ periode.hkKoordinator }} hari kerja koordinator (Senin–Jumat, tanpa libur).
        </p>

        <Modal :show="modal === 'grafik'" title="Chart Per Depo" @close="tutup">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <Label for="metrik-grafik" class="mb-0">Metrik</Label>
                <select id="metrik-grafik" v-model="metrik" aria-label="Metrik grafik" class="form-control form-control-sm w-56">
                    <option v-for="m in metrikGrafik" :key="m.key" :value="m.key">{{ m.label }}</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <svg v-if="grafikBars.length" :width="grafikLebar" :height="TINGGI + ATAS + BAWAH" role="img" :aria-label="`${metrikLabel} per depo`">
                    <line :x1="0" :x2="grafikLebar" :y1="ATAS + TINGGI" :y2="ATAS + TINGGI" stroke="#d1d5db" />
                    <g v-for="b in grafikBars" :key="b.nama">
                        <rect :x="b.x" :y="b.y" :width="LEBAR_BATANG" :height="b.h" rx="3" fill="#4ade80" stroke="#22c55e"><title>{{ b.nama }}: {{ angkaRingkas(b.v, 1) }}</title></rect>
                        <text :x="b.x + LEBAR_BATANG / 2" :y="b.y - 6" text-anchor="middle" font-size="10" font-weight="600" fill="#374151">{{ angkaRingkas(b.v, 1) }}</text>
                        <text :x="b.x + LEBAR_BATANG / 2" :y="ATAS + TINGGI + 12" text-anchor="end" font-size="10" fill="#4b5563" :transform="`rotate(-35 ${b.x + LEBAR_BATANG / 2} ${ATAS + TINGGI + 12})`">{{ b.nama }}</text>
                    </g>
                </svg>
                <p v-else class="text-gray-500">Tidak ada depo.</p>
            </div>
        </Modal>

        <Modal v-if="bolehUbah" :show="modal === 'target'" title="Setting Target" @close="tutup">
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
