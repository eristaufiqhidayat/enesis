<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import DepoKoordinator from './partials/DepoKoordinator.vue';
import FilterKehadiran from './partials/FilterKehadiran.vue';
import SumberBadge from './partials/SumberBadge.vue';
import { angka } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';

const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    filter: { type: Object, required: true },
    status: { type: String, required: true },
    ringkas: { type: Object, required: true },
    labelOrang: { type: String, default: 'Sales' },
    baris: { type: Object, required: true },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarKoordinator: { type: [Object, Array], default: () => ({}) },
    daftarSumber: { type: Object, required: true },
});

const appName = usePage().props.appName;

const columns = computed(() => ({
    tanggal: 'Tanggal',
    sales: props.labelOrang,
    depo: props.labelOrang === 'Koordinator' ? 'Depo' : 'Depo · Koordinator',
    masuk: 'Masuk',
    pulang: 'Pulang',
    durasi: 'Durasi',
    lokasi: 'Lokasi & selfie',
    sumber: 'Sumber',
}));

const statusItems = computed(() => [
    { kode: 'semua', label: 'Semua', jumlah: props.ringkas.total },
    { kode: 'terlambat', label: 'Terlambat', jumlah: props.ringkas.terlambat },
    { kode: 'belum_pulang', label: 'Belum absen pulang', jumlah: null },
]);

// Tautan tab status: filter yang sedang aktif ikut dibawa, parameter page dibuang.
function tautanStatus(kode) {
    return `/${props.slug}?${new URLSearchParams(bersihkanQuery({ ...props.filter, status: kode }))}`;
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" subtitle="Data aplikasi motoris dan riwayat aplikasi lama · jam dalam WIB" />

        <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Saring status">
            <Button
                v-for="item in statusItems"
                :key="item.kode"
                :tag="Link"
                :variant="status === item.kode ? 'primary' : 'outline-secondary'"
                :href="tautanStatus(item.kode)"
                :aria-current="status === item.kode ? 'true' : null"
            >
                {{ item.label }}
                <span v-if="item.jumlah !== null" class="ms-1 opacity-75">{{ angka(item.jumlah) }}</span>
            </Button>
            <span class="self-center text-gray-500 ms-auto">{{ angka(ringkas.motoris) }} dari aplikasi motoris</span>
        </div>

        <DataTable
            :columns="columns"
            :rows="baris.data"
            :total="baris.total"
            :links="baris.links"
            empty-text="Tidak ada absensi pada rentang ini."
        >
            <template #header>
                <FilterKehadiran
                    :action="`/${slug}`"
                    :filter="filter"
                    :status="status"
                    :label-orang="labelOrang"
                    :daftar-depo="daftarDepo"
                    :daftar-koordinator="daftarKoordinator"
                    :daftar-sumber="daftarSumber"
                />
            </template>

            <template #cell-sales="{ row }">
                <span class="font-semibold">{{ row.sales }}</span>
            </template>
            <template #cell-depo="{ row }">
                <DepoKoordinator :depo="row.depo" :koordinator="row.koordinator" />
            </template>
            <template #cell-masuk="{ row }">
                <span class="whitespace-nowrap">
                    {{ row.masuk }}
                    <Badge v-if="row.terlambat" variant="warning">Terlambat</Badge>
                </span>
            </template>
            <template #cell-lokasi="{ row }">
                <span class="whitespace-nowrap">
                    <a v-if="row.peta" :href="row.peta" target="_blank" rel="noopener">Peta</a>
                    <template v-if="row.foto">
                        <span v-if="row.peta"> · </span>
                        <a :href="row.foto" target="_blank" rel="noopener">Selfie</a>
                    </template>
                    <Badge v-if="row.mock_location" variant="danger" title="HP memakai lokasi palsu">Lokasi palsu</Badge>
                    <span v-if="!row.peta && !row.foto" class="text-gray-500">—</span>
                </span>
            </template>
            <template #cell-sumber="{ row }">
                <SumberBadge :sumber="row.sumber" />
            </template>
        </DataTable>
    </AppLayout>
</template>
