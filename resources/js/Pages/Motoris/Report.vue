<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import InfoPopover from '@/Components/ui/molecules/InfoPopover.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import Absensi from './partials/Absensi.vue';
import Coverage from './partials/Coverage.vue';
import Performance from './partials/Performance.vue';
import { useAmbilData } from '@/Support/ambilData.js';
import { bersihkanQuery } from '@/Support/query.js';

// `laporan` ({ baris, total }) kosong saat masih ditunda (lihat <Deferred> di bawah).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    tab: { type: String, required: true },
    tabs: { type: Array, required: true },
    bulan: { type: String, required: true },
    periode: { type: Object, required: true },
    filter: { type: Object, required: true },
    bolehKuota: { type: Boolean, default: false },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarKoordinator: { type: [Object, Array], default: () => ({}) },
    laporan: { type: Object, default: () => ({}) },
});

// Kunjungan partial (pindah tab, Tampilkan) mengambil ulang laporan dalam satu request, tanpa skeleton.
const PROPS_LAPORAN = ['tab', 'bulan', 'periode', 'filter', 'bolehKuota', 'daftarDepo', 'daftarKoordinator', 'laporan'];

const appName = usePage().props.appName;

const subtitle = computed(() => `Dihitung dari data aplikasi motoris dan riwayat aplikasi lama · periode ${props.periode.dari} – ${props.periode.sampai} · HK standar ${props.periode.hkStandar} hari`);

// Isian filter mengikuti query yang sedang aktif; hanya tab Absensi yang punya filter sales, depo, dan koordinator.
const filterBulan = ref(props.bulan);
const filterQ = ref(props.filter.q ?? '');
const filterDepo = ref(props.filter.depo ? String(props.filter.depo) : '');
const filterKoordinator = ref(props.filter.koordinator ? String(props.filter.koordinator) : '');

function pindahTab(kode) {
    router.get('/motoris_report', { tab: kode, bulan: props.bulan }, { only: PROPS_LAPORAN });
}

// Hanya tab Absensi yang punya filter sales, depo, dan koordinator.
const filterSaatIni = () => ({
    bulan: filterBulan.value,
    ...(props.tab === 'absensi' ? { q: filterQ.value, depo: filterDepo.value, koordinator: filterKoordinator.value } : {}),
});

// Tombol Tampilkan aktif lagi hanya saat isian filter berbeda dari filter yang sedang tampil.
const { memuat, berubah, ambil } = useAmbilData({
    form: filterSaatIni,
    diterapkan: () => ({
        bulan: props.bulan,
        ...(props.tab === 'absensi' ? { q: props.filter.q, depo: props.filter.depo, koordinator: props.filter.koordinator } : {}),
    }),
});

function saring() {
    ambil('/motoris_report', bersihkanQuery({ tab: props.tab, ...filterSaatIni() }), { only: PROPS_LAPORAN });
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="subtitle">
            <template v-if="bolehKuota && tab !== 'absensi'" #actions>
                <Button :tag="Link" variant="outline-secondary" :href="`/kuota_motoris?bulan=${bulan}`">Atur kuota motoris</Button>
            </template>
        </PageHeader>

        <Tabs :tabs="tabs" :model-value="tab" @update:model-value="pindahTab" />

        <Card body-class="overflow-x-auto">
            <template #header>
                <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="saring">
                    <div>
                        <Label for="bulan">Bulan</Label>
                        <Input id="bulan" v-model="filterBulan" type="month" />
                    </div>
                    <template v-if="tab === 'absensi'">
                        <div class="grow min-w-[12rem]">
                            <Label for="q">Nama sales</Label>
                            <Input id="q" v-model="filterQ" type="search" placeholder="Cari nama sales…" />
                        </div>
                        <div>
                            <Label for="depo">Depo</Label>
                            <Select id="depo" v-model="filterDepo">
                                <option value="">Semua depo</option>
                                <option v-for="(nama, id) in daftarDepo" :key="id" :value="String(id)">{{ nama }}</option>
                            </Select>
                        </div>
                        <div v-if="Object.keys(daftarKoordinator).length">
                            <Label for="koordinator">Koordinator</Label>
                            <Select id="koordinator" v-model="filterKoordinator">
                                <option value="">Semua koordinator</option>
                                <option v-for="(nama, id) in daftarKoordinator" :key="id" :value="String(id)">{{ nama }}</option>
                            </Select>
                        </div>
                    </template>
                    <Button type="submit" :loading="memuat" :disabled="!berubah">Tampilkan</Button>
                </form>
            </template>

            <Deferred data="laporan">
                <template #fallback>
                    <TableSkeleton />
                </template>

                <Coverage v-if="tab === 'coverage'" :baris="laporan.baris" :total="laporan.total" />
                <Performance v-else-if="tab === 'performance'" :baris="laporan.baris" :total="laporan.total" />
                <Absensi v-else :baris="laporan.baris" />
            </Deferred>
        </Card>

        <!-- Di luar Card: body Card memakai overflow-x-auto, yang memotong popover di dalamnya. -->
        <div v-if="tab !== 'absensi'" class="mt-3 flex items-center gap-1 text-xs text-gray-500">
            <span>Keterangan cara hitung</span>
            <InfoPopover title="Cara Hitung">
                <ul v-if="tab === 'coverage'" class="list-disc ps-4 space-y-1">
                    <li><strong>Quota</strong> = kuota motoris per depo (Master Data › Kuota Motoris).</li>
                    <li><strong>Already Transaction</strong> = sales yang punya minimal satu penjualan pada periode.</li>
                    <li><strong>Active End Periode</strong> = sales motoris berstatus aktif.</li>
                    <li><strong>Vacant</strong> = Quota − Active.</li>
                    <li><strong>Coverage</strong> = Active ÷ Quota.</li>
                </ul>
                <ul v-else class="list-disc ps-4 space-y-1">
                    <li><strong>HK Standar</strong> = hari kerja sales sampai hari ini (Waktu Aktif App, tanpa hari libur).</li>
                    <li><strong>Act HK</strong> = rata-rata hari hadir per motoris yang absen.</li>
                    <li><strong>Target</strong> = target call per hari depo (sama dengan aplikasi HP) × HK Standar × motoris aktif.</li>
                    <li><strong>EC</strong> = transaksi effective call.</li>
                    <li><strong>IPT</strong> = rata-rata SKU per transaksi.</li>
                    <li><strong>Value</strong> = total penjualan (tidak termasuk yang dibatalkan).</li>
                </ul>
            </InfoPopover>
        </div>
    </AppLayout>
</template>
