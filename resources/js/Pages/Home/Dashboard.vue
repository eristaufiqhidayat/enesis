<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import InfoPopover from '@/Components/ui/molecules/InfoPopover.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatLabel from '@/Components/ui/atoms/StatLabel.vue';
import StatRow from '@/Components/ui/molecules/StatRow.vue';
import BarChart from '@/Components/ui/molecules/BarChart.vue';
import DashboardSkeleton from './partials/DashboardSkeleton.vue';
import FunnelOutletChart from './partials/FunnelOutletChart.vue';
import TrenHarianChart from './partials/TrenHarianChart.vue';
import { angka, angkaRingkas } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';
import { rupiah, warnaCapai } from '@/Support/summaryDepo.js';
import { useAmbilData } from '@/Support/ambilData.js';

const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    periode: { type: Object, required: true },
    filter: { type: Object, required: true },
    target: { type: Object, required: true },
    // Kosong saat masih ditunda (lihat <Deferred> di bawah); semuanya tiba bersamaan (satu grup "ringkasan").
    // kpi & topDepo digabung jadi satu prop di backend (ringkasanDepo) supaya SummaryDepo::kartu() cuma
    // dihitung sekali (lihat HomeController::ringkasanDepo).
    ringkasanDepo: { type: Object, default: null },
    funnel: { type: Array, default: null },
    tren: { type: Array, default: null },
});

const kpi = computed(() => props.ringkasanDepo?.kpi ?? null);
const topDepo = computed(() => props.ringkasanDepo?.topDepo ?? null);

const appName = usePage().props.appName;
const subtitle = computed(() => (props.periode.sama ? props.periode.dari : `${props.periode.dari} – ${props.periode.sampai}`));

const filterDari = ref(props.filter.date_from);
const filterSampai = ref(props.filter.date_to);

// Tanggal baru dimuat saat tombol Tampilkan diklik, sama dengan pola di Motoris/SummaryDepo.vue.
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ date_from: filterDari.value, date_to: filterSampai.value }),
    diterapkan: () => ({ date_from: props.filter.date_from, date_to: props.filter.date_to }),
});

function tampilkan() {
    ambil('/home', bersihkanQuery({ date_from: filterDari.value, date_to: filterSampai.value }), {
        only: ['ringkasanDepo', 'funnel', 'tren', 'periode', 'filter'],
    });
}

const topDepoChart = computed(() => (topDepo.value ?? []).map((d) => ({
    label: d.nama,
    value: d.ec_achievement,
    barClass: d.ec_achievement >= 100 ? 'fill-green-400 stroke-green-500' : (d.ec_achievement >= 75 ? 'fill-yellow-400 stroke-yellow-500' : 'fill-rose-400 stroke-rose-500'),
})));
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="subtitle">
            <template #info>
                <InfoPopover title="Tentang Beranda" class="ms-1 align-middle">
                    <ul class="list-disc ps-4 space-y-1">
                        <li>KPI nasional dihitung dari tabel motoris yang sama dengan Summary All Depo — total digabung dulu, baru dihitung AVG per hari kerja (rata-rata berbobot, bukan rata-rata dari rata-rata).</li>
                        <li><b>Funnel Outlet:</b> NOO (outlet baru pada rentang ini) → AC (dikunjungi, belum pernah EC) → EC (effective call) → RO (EC di ≥2 tanggal berbeda), sama dengan definisi Lap. Outlet.</li>
                        <li><b>OSS</b> bukan tahap funnel: ini outlet AC yang kunjungan terakhirnya tidak tercatat stok sendiri — peringatan kualitas kunjungan.</li>
                        <li><b>Depo Teratas</b> diurutkan dari EC Achievement tertinggi (8 besar), sama seperti Summary All Depo.</li>
                    </ul>
                </InfoPopover>
            </template>
            <template #actions>
                <form class="flex flex-wrap items-center gap-2" role="search" @submit.prevent="tampilkan">
                    <Input id="date_from" v-model="filterDari" type="date" aria-label="Dari tanggal" class="w-36" />
                    <Input id="date_to" v-model="filterSampai" type="date" aria-label="Sampai tanggal" class="w-36" />
                    <Button type="submit" :loading="memuat" :disabled="!berubah"><Icon name="search" class="me-1 w-3.5 h-3.5" />Tampilkan</Button>
                </form>
            </template>
        </PageHeader>

        <Deferred :data="['ringkasanDepo', 'funnel', 'tren']">
            <template #fallback>
                <DashboardSkeleton />
            </template>

            <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 mb-4">
                <Card class="shadow-sm" body-class="py-3 text-center">
                    <StatLabel>Total Sales</StatLabel>
                    <div class="text-lg font-bold text-green-600"><Icon name="banknote" class="w-4 h-4 inline -mt-1" /> {{ rupiah(kpi.sales) }}</div>
                </Card>
                <Card class="shadow-sm" body-class="py-3 text-center">
                    <StatLabel>Total EC</StatLabel>
                    <div class="text-lg font-bold text-sky-600">{{ angka(kpi.ec) }}</div>
                </Card>
                <Card class="shadow-sm" body-class="py-3 text-center">
                    <StatLabel>Total Call</StatLabel>
                    <div class="text-lg font-bold text-sky-600">{{ angka(kpi.call) }}</div>
                </Card>
                <Card class="shadow-sm" body-class="py-3 text-center">
                    <StatLabel>Sales Motoris Aktif</StatLabel>
                    <div class="text-lg font-bold text-gray-800"><Icon name="bike" class="w-4 h-4 inline -mt-1" /> {{ angka(kpi.motoris_aktif) }}</div>
                </Card>
                <Card class="shadow-sm" body-class="py-3 text-center">
                    <StatLabel>Depo Aktif</StatLabel>
                    <div class="text-lg font-bold text-gray-800"><Icon name="warehouse" class="w-4 h-4 inline -mt-1" /> {{ angka(kpi.depo_aktif) }}</div>
                </Card>
                <Card class="shadow-sm" body-class="py-3 text-center">
                    <StatLabel>SK Aktif</StatLabel>
                    <div class="text-lg font-bold text-gray-800"><Icon name="users" class="w-4 h-4 inline -mt-1" /> {{ angka(kpi.sk_aktif) }}</div>
                </Card>
            </div>

            <Card class="shadow-sm mb-4">
                <template #header>Achievement Nasional (vs Target: Call {{ angkaRingkas(target.call, 1) }}, EC {{ angkaRingkas(target.ec, 1) }}, IPT {{ angkaRingkas(target.ipt, 1) }})</template>
                <div class="grid gap-2 sm:grid-cols-3">
                    <StatRow label="AVG Call / Hari Kerja" :value-class="warnaCapai(kpi.call_achievement)">{{ angkaRingkas(kpi.avg_call, 1) }} ({{ angkaRingkas(kpi.call_achievement, 1) }}%)</StatRow>
                    <StatRow label="AVG EC / Hari Kerja" :value-class="warnaCapai(kpi.ec_achievement)">{{ angkaRingkas(kpi.avg_ec, 1) }} ({{ angkaRingkas(kpi.ec_achievement, 1) }}%)</StatRow>
                    <StatRow label="AVG IPT" :value-class="warnaCapai(kpi.ipt_achievement)">{{ angkaRingkas(kpi.avg_ipt, 1) }} ({{ angkaRingkas(kpi.ipt_achievement, 1) }}%)</StatRow>
                </div>
            </Card>

            <div class="grid gap-3 lg:grid-cols-2 mb-4">
                <Card class="shadow-sm">
                    <template #header>Funnel Outlet</template>
                    <FunnelOutletChart :funnel="funnel" />
                </Card>
                <Card class="shadow-sm">
                    <template #header>Tren Harian</template>
                    <TrenHarianChart :data="tren" />
                </Card>
            </div>

            <Card class="shadow-sm">
                <template #header>Depo Teratas · EC Achievement</template>
                <BarChart :items="topDepoChart" suffix="%" :desimal="1" />
            </Card>
        </Deferred>
    </AppLayout>
</template>
