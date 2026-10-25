<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import RekapSkuTabel from './partials/RekapSkuTabel.vue';
import { angkaRingkas } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';

// `hasil` kosong saat masih ditunda (lihat <Deferred> di bawah).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    tab: { type: String, required: true },
    tabs: { type: Array, required: true },
    periode: { type: Object, required: true },
    hasil: { type: Object, default: () => ({}) },
    filter: { type: Object, required: true },
    daftarDepo: { type: [Object, Array], default: () => ({}) },
    daftarProduk: { type: [Object, Array], default: () => ({}) },
    bolehUbah: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const perMotoris = computed(() => props.tab === 'motoris');

// Total jumlah baru tampil setelah `hasil` tiba.
const subtitle = computed(() => {
    const tgl = props.periode.sama ? props.periode.dari : `${props.periode.dari} – ${props.periode.sampai}`;
    const satuan = perMotoris.value ? 'Motoris' : 'Depo';
    const total = props.hasil.jumlah !== undefined ? ` · Total ${satuan} ${props.hasil.jumlah}` : '';

    return `Dihitung dari penjualan aplikasi motoris · ${tgl} · Hari Kerja Standar ${props.periode.hkStandar} hari · `
        + `Target ${angkaRingkas(props.periode.target)} /Hari${total}`;
});

// Kunjungan partial (tampilkan, pindah tab) mengambil ulang hasil dalam satu request, tanpa skeleton.
const PROPS_REKAP = ['tab', 'periode', 'hasil', 'filter'];

const filterDariTanggal = ref(props.filter.date_from);
const filterSampaiTanggal = ref(props.filter.date_to);
const filterDepo = ref(props.filter.warehouse_id.map(String));
const filterProduk = ref(props.filter.product_id.map(String));

function query(tambahan = {}) {
    return bersihkanQuery({
        tab: props.tab, date_from: filterDariTanggal.value, date_to: filterSampaiTanggal.value,
        ...tambahan,
    });
}

function tampilkan() {
    router.get('/rekap_pencapaian_sku', { ...query(), warehouse_id: filterDepo.value, product_id: filterProduk.value }, { only: PROPS_REKAP });
}

function pindahTab(kode) {
    router.get('/rekap_pencapaian_sku', { ...query({ tab: kode }), warehouse_id: filterDepo.value, product_id: filterProduk.value }, { only: PROPS_REKAP });
}

const formTarget = useForm({ target: props.periode.target });

function simpanTarget() {
    formTarget.transform((data) => ({
        ...data, tab: props.tab, date_from: filterDariTanggal.value, date_to: filterSampaiTanggal.value,
        warehouse_id: filterDepo.value, product_id: filterProduk.value,
    })).post('/rekap_pencapaian_sku/target', { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="subtitle" />

        <Tabs :tabs="tabs" :model-value="tab" @update:model-value="pindahTab" />

        <Card class="mb-3">
            <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="tampilkan">
                <div>
                    <Label for="date_from">Dari</Label>
                    <Input id="date_from" v-model="filterDariTanggal" type="date" />
                </div>
                <div>
                    <Label for="date_to">Sampai</Label>
                    <Input id="date_to" v-model="filterSampaiTanggal" type="date" />
                </div>
                <div class="min-w-48">
                    <Label for="warehouse_id">Depo <span class="text-gray-500">(kosong = semua)</span></Label>
                    <Combobox id="warehouse_id" v-model="filterDepo" multiple :options="daftarDepo" placeholder="Cari depo..." />
                </div>
                <div class="min-w-56">
                    <Label for="product_id">Produk <span class="text-gray-500">(kosong = semua)</span></Label>
                    <Combobox id="product_id" v-model="filterProduk" multiple :options="daftarProduk" placeholder="Cari produk..." />
                </div>
                <Button type="submit">Tampilkan</Button>
                <Button :tag="Link" variant="outline-secondary" :href="`/rekap_pencapaian_sku?tab=${tab}`">Reset Filter</Button>
            </form>

            <form v-if="bolehUbah" class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t" @submit.prevent="simpanTarget">
                <div>
                    <Label for="target">Setting Target (/Hari)</Label>
                    <Input id="target" v-model="formTarget.target" type="number" step="0.01" min="0.01" class="w-28" />
                    <FieldError v-if="formTarget.errors.target">{{ formTarget.errors.target }}</FieldError>
                </div>
                <Button type="submit" variant="outline-secondary" :disabled="formTarget.processing">Simpan target</Button>
                <span class="text-xs text-gray-500">Satu target untuk tab Per Motoris dan Per Depo.</span>
            </form>
        </Card>

        <Deferred data="hasil">
            <template #fallback>
                <div class="grid gap-3 lg:grid-cols-2">
                    <Card body-class="overflow-x-auto"><TableSkeleton /></Card>
                    <Card body-class="overflow-x-auto"><TableSkeleton /></Card>
                </div>
            </template>

            <div class="grid gap-3 lg:grid-cols-2">
                <RekapSkuTabel
                    :judul="(perMotoris ? 'Pencapaian' : 'Rekap Depo – Pencapaian') + ' ≥ ' + angkaRingkas(periode.target) + ' /Hari' + (perMotoris ? '' : ' (Avg/Day/Motoris)')"
                    :tercapai="true"
                    :jumlah="hasil.jumlah"
                    :per-motoris="perMotoris"
                    :baris="hasil.tercapai.baris"
                    :total="hasil.tercapai.total"
                />
                <RekapSkuTabel
                    :judul="(perMotoris ? 'Pencapaian' : 'Rekap Depo – Pencapaian') + ' < ' + angkaRingkas(periode.target) + ' /Hari' + (perMotoris ? '' : ' (Avg/Day/Motoris)')"
                    :tercapai="false"
                    :jumlah="hasil.jumlah"
                    :per-motoris="perMotoris"
                    :baris="hasil.belum.baris"
                    :total="hasil.belum.total"
                />
            </div>
        </Deferred>

        <p class="mt-3 text-xs text-gray-500">
            Total Qty = jumlah qty produk terjual (transaksi yang tidak dibatalkan). Hari Kerja = hari kerja standar sales pada rentang (Waktu Aktif App, tanpa hari libur).
            <template v-if="perMotoris">
                Avg/Day = Total Qty ÷ Hari Kerja. TOTAL = Σ Qty ÷ (jumlah motoris di tabel × Hari Kerja).
                Daftar berisi sales motoris aktif ditambah sales tidak aktif yang masih menjual pada rentang.
            </template>
            <template v-else>
                Avg/Day/Motoris = Total Qty ÷ jumlah hari-motoris bertransaksi (satu motoris yang menjual pada satu tanggal = 1). TOTAL = Σ Qty ÷ Σ hari-motoris.
                Daftar berisi depo yang punya sales motoris aktif atau penjualan pada rentang.
            </template>
        </p>
    </AppLayout>
</template>
