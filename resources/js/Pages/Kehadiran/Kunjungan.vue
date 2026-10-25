<script setup>
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
    cariOutlet: { type: String, default: '' },
    ringkas: { type: Object, required: true },
    baris: { type: Object, required: true },
    daftarStatus: { type: Object, required: true },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarKoordinator: { type: [Object, Array], default: () => ({}) },
    daftarSumber: { type: Object, required: true },
});

const appName = usePage().props.appName;

const columns = {
    tanggal: 'Tanggal',
    sales: 'Sales',
    outlet: 'Outlet',
    masuk: 'Checkin',
    pulang: 'Checkout',
    durasi: 'Durasi',
    hasil: 'Hasil',
    lokasi: 'Lokasi & foto',
    sumber: 'Sumber',
};

// Jumlah per tab: "semua" memakai total, selebihnya kunci yang sama dengan kode status.
const jumlah = (kode) => props.ringkas[kode === 'semua' ? 'total' : kode];

// Tautan tab status: filter yang sedang aktif ikut dibawa, parameter page dibuang.
function tautanStatus(kode) {
    return `/${props.slug}?${new URLSearchParams(bersihkanQuery({
        ...props.filter,
        outlet: props.cariOutlet,
        status: kode === 'semua' ? null : kode,
    }))}`;
}

const kosong = (row) => !row.peta && !row.foto && !row.foto_lama && !row.di_luar_rute && !row.mock_location;
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" subtitle="Kunjungan sales ke outlet · data aplikasi motoris dan riwayat aplikasi lama · jam dalam WIB" />

        <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Saring status kunjungan">
            <Button
                v-for="(label, kode) in daftarStatus"
                :key="kode"
                :tag="Link"
                :variant="status === kode ? 'primary' : 'outline-secondary'"
                :href="tautanStatus(kode)"
                :aria-current="status === kode ? 'true' : null"
            >
                {{ label }} <span class="ms-1 opacity-75">{{ angka(jumlah(kode)) }}</span>
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :rows="baris.data"
            :total="baris.total"
            :links="baris.links"
            empty-text="Tidak ada kunjungan pada rentang ini."
        >
            <template #header>
                <FilterKehadiran
                    :action="`/${slug}`"
                    :filter="filter"
                    :status="status === 'semua' ? null : status"
                    :cari-outlet="cariOutlet"
                    :daftar-depo="daftarDepo"
                    :daftar-koordinator="daftarKoordinator"
                    :daftar-sumber="daftarSumber"
                />
            </template>

            <template #cell-sales="{ row }">
                <span class="font-semibold">{{ row.sales }}</span>
                <DepoKoordinator :depo="row.depo" :koordinator="row.koordinator" />
            </template>
            <template #cell-outlet="{ row }">
                {{ row.outlet }}
                <div v-if="row.outlet_kode" class="text-gray-500">{{ row.outlet_kode }}</div>
            </template>
            <template #cell-hasil="{ row }">
                <template v-if="row.total_jual !== null">
                    <Badge variant="success">Beli</Badge> <span class="whitespace-nowrap">Rp {{ angka(row.total_jual) }}</span>
                </template>
                <template v-else-if="row.tidak_beli">
                    <Badge variant="secondary">Tidak beli</Badge>
                    <div class="text-gray-500">{{ row.tidak_beli }}</div>
                </template>
                <span v-else-if="row.keterangan_lama" class="text-gray-500" title="Kategori dan keterangan dari aplikasi lama">{{ row.keterangan_lama }}</span>
                <span v-else class="text-gray-500">—</span>
            </template>
            <template #cell-lokasi="{ row }">
                <span class="whitespace-nowrap">
                    <template v-if="row.peta">
                        <a :href="row.peta" target="_blank" rel="noopener">Peta</a>
                        <span v-if="row.jarak_m !== null" class="text-gray-500"> · {{ angka(row.jarak_m) }} m dari outlet</span>
                    </template>
                    <div v-if="row.foto"><a :href="row.foto" target="_blank" rel="noopener">Foto</a></div>
                    <div v-else-if="row.foto_lama">
                        <a :href="row.foto_lama" target="_blank" rel="noopener noreferrer" title="Foto di server aplikasi lama">Foto (aplikasi lama)</a>
                    </div>
                    <Badge v-if="row.di_luar_rute" variant="warning">Di luar rute</Badge>
                    <Badge v-if="row.mock_location" variant="danger" title="HP memakai lokasi palsu">Lokasi palsu</Badge>
                    <span v-if="kosong(row)" class="text-gray-500">—</span>
                </span>
            </template>
            <template #cell-sumber="{ row }">
                <SumberBadge :sumber="row.sumber" />
            </template>
        </DataTable>
    </AppLayout>
</template>
