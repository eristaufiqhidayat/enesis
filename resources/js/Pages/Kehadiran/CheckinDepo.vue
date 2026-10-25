<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import DepoKoordinator from './partials/DepoKoordinator.vue';
import FilterKehadiran from './partials/FilterKehadiran.vue';
import SumberBadge from './partials/SumberBadge.vue';
import { angka } from '@/Support/format.js';

defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    filter: { type: Object, required: true },
    ringkas: { type: Object, required: true },
    baris: { type: Object, required: true },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarKoordinator: { type: [Object, Array], default: () => ({}) },
    daftarSumber: { type: Object, required: true },
});

const appName = usePage().props.appName;

const columns = {
    tanggal: 'Tanggal',
    sales: 'Sales',
    depo: 'Depo · Koordinator',
    masuk: 'Checkin',
    pulang: 'Checkout',
    durasi: 'Durasi',
    lokasi: 'Lokasi',
    sumber: 'Sumber',
};
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" subtitle="Data aplikasi motoris dan riwayat aplikasi lama · jam dalam WIB" />

        <div class="flex flex-wrap gap-3 mb-3 text-gray-600">
            <span><strong>{{ angka(ringkas.total) }}</strong> checkin</span>
            <span><strong>{{ angka(ringkas.belum_checkout) }}</strong> belum checkout</span>
            <span><strong>{{ angka(ringkas.motoris) }}</strong> dari aplikasi motoris</span>
        </div>

        <DataTable
            :columns="columns"
            :rows="baris.data"
            :total="baris.total"
            :links="baris.links"
            empty-text="Tidak ada checkin depo pada rentang ini."
        >
            <template #header>
                <FilterKehadiran
                    :action="`/${slug}`"
                    :filter="filter"
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
            <template #cell-lokasi="{ row }">
                <span class="whitespace-nowrap">
                    <template v-if="row.peta">
                        <a :href="row.peta" target="_blank" rel="noopener">Peta</a>
                        <span v-if="row.jarak_m !== null" class="text-gray-500"> · {{ angka(row.jarak_m) }} m dari depo</span>
                    </template>
                    <span v-else class="text-gray-500">—</span>
                </span>
            </template>
            <template #cell-sumber="{ row }">
                <SumberBadge :sumber="row.sumber" />
            </template>
        </DataTable>
    </AppLayout>
</template>
