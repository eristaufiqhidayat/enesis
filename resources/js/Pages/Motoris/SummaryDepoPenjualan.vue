<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';

const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    depo: { type: Object, required: true },
    total: { type: Object, required: true },
    baris: { type: Object, required: true },
    periode: { type: Object, required: true },
    filter: { type: Object, required: true },
});

const appName = usePage().props.appName;
const periodeText = computed(() => (props.periode.sama ? props.periode.dari : `${props.periode.dari} – ${props.periode.sampai}`));
const rupiah = (n) => `Rp ${angka(n)}`;
const tgl = (t) => { const [y, m, d] = t.slice(0, 10).split('-'); return `${d}/${m}/${y}`; };

const columns = {
    tanggal: 'Tanggal', kode_outlet: 'Kode Outlet', nama_outlet: 'Nama Outlet', sales: 'Sales',
    is_effective_call: 'EC?', total: 'Total Sales', jumlah_sku: 'SKU', poin: 'Point',
};

const cari = ref(props.filter.q);

function saring() {
    router.get(`/summary_all_depo/${props.depo.id}/penjualan`, bersihkanQuery({
        date_from: props.filter.date_from, date_to: props.filter.date_to, q: cari.value,
    }));
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="`Periode ${periodeText}`">
            <template #actions>
                <Button :tag="Link" variant="outline-secondary" :href="`/summary_all_depo?${new URLSearchParams({ date_from: filter.date_from, date_to: filter.date_to })}`">Kembali ke Summary All Depo</Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-1.5 mb-2">
            <Badge variant="success">Total EC: {{ angka(total.ec) }}</Badge>
            <Badge variant="primary">Total Sales: {{ rupiah(total.sales) }}</Badge>
            <Badge variant="warning">Total SKU: {{ angka(total.sku) }}</Badge>
            <Badge variant="warning">Total Point: {{ angka(total.point) }}</Badge>
        </div>

        <DataTable :columns="columns" :rows="baris.data" :total="baris.total" :links="baris.links" empty-text="Tidak ada transaksi pada periode ini.">
            <template #header>
                <form class="flex justify-end gap-2" role="search" @submit.prevent="saring">
                    <Input v-model="cari" type="search" placeholder="Cari outlet atau sales…" aria-label="Cari outlet atau sales" class="max-w-xs" />
                    <Button type="submit" variant="outline-secondary">Cari</Button>
                </form>
            </template>

            <template #cell-tanggal="{ row }">{{ tgl(row.tanggal) }}</template>
            <template #cell-kode_outlet="{ row }">{{ row.kode_outlet ?? '-' }}</template>
            <template #cell-nama_outlet="{ row }">{{ row.nama_outlet ?? '-' }}</template>
            <template #cell-is_effective_call="{ row }"><Badge :variant="row.is_effective_call ? 'success' : 'secondary'">{{ row.is_effective_call ? 'EC' : '-' }}</Badge></template>
            <template #cell-total="{ row }">{{ rupiah(row.total) }}</template>
            <template #cell-jumlah_sku="{ row }">{{ angka(row.jumlah_sku) }}</template>
            <template #cell-poin="{ row }">{{ angka(row.poin) }}</template>
        </DataTable>
    </AppLayout>
</template>
