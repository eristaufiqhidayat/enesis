<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

const props = defineProps({
    slug: { type: String, required: true },
    page: { type: Object, required: true },
    tables: { type: Array, default: () => [] },
    activeTab: { type: String, default: null },
    search: { type: String, default: '' },
    tanggal: { type: String, default: null },
    hasDateFilter: { type: Boolean, default: false },
});

const search = ref(props.search);
const tanggal = ref(props.tanggal);
const appName = usePage().props.appName;

// Halaman bertab: backend hanya meng-query tabel grup aktif (lihat MenuController), jadi `tables` di sini
// sudah berisi tabel tab aktif saja. Daftar tab (termasuk tab yang tidak aktif) diambil dari config
// `page.tables` yang statis, bukan dari hasil query, supaya tombol tab lain tetap muncul.
const bertab = computed(() => props.page.tabs === true);
const visibleTables = computed(() => props.tables);
const tabItems = computed(() => bertab.value
    ? props.page.tables.map((grup) => ({ key: grup.tab_key, label: grup.tab_label }))
    : []);
const formDateFilter = computed(() => bertab.value ? visibleTables.value.some((table) => table.date_filter) : props.hasDateFilter);

// Komponen ini dipakai ulang saat navigasi Inertia, jadi nilai input ikut props terbaru.
watch(() => props.search, (value) => search.value = value);
watch(() => props.tanggal, (value) => tanggal.value = value);

function kpiItems(kpi) {
    return kpi ? [
        { label: 'Sudah Absen', value: kpi.sudah_absen },
        { label: 'Belum Absen', value: kpi.belum_absen },
        { label: 'Terlambat', value: kpi.terlambat },
    ] : [];
}

// Tab "Outlet Baru dari HP" (aksi verifikasi_outlet_baru): warna status dan tombol Setujui / Tolak.
const warnaStatusOutletBaru = { status: { menunggu: 'warning', disetujui: 'success', ditolak: 'danger' } };

function setujuiOutletBaru(row) {
    if (window.confirm(`Setujui outlet "${row.nama}"? Outlet akan masuk master outlet dan bisa dikunjungi semua sales di areanya.`)) {
        router.post(`/outlet_all/baru/${row.id}/setujui`, {}, { preserveScroll: true });
    }
}

function tolakOutletBaru(row) {
    const alasan = window.prompt(`Alasan menolak outlet "${row.nama}":`);

    if (alasan && alasan.trim()) {
        router.post(`/outlet_all/baru/${row.id}/tolak`, { alasan: alasan.trim() }, { preserveScroll: true });
    }
}

function submitSearch() {
    const query = { q: search.value };

    if (bertab.value) {
        query.tab = props.activeTab;
    }

    if (formDateFilter.value) {
        query.tanggal = tanggal.value;
    }

    router.get(`/${props.slug}`, query, { preserveState: true, replace: true });
}

function pindahTab(key) {
    const query = { tab: key };
    // Tabel tab tujuan belum di-query (lihat komentar di tabItems), jadi cek date_filter_column dari config.
    const grupTujuan = props.page.tables.find((grup) => grup.tab_key === key);
    const tabBaruPakaiTanggal = grupTujuan?.tables.some((tabel) => (typeof tabel === 'object' ? tabel.date_filter_column : null) !== null) ?? false;

    if (search.value) {
        query.q = search.value;
    }

    if (tabBaruPakaiTanggal && tanggal.value) {
        query.tanggal = tanggal.value;
    }

    router.get(`/${props.slug}`, query, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${page.title} · ${appName}`" />
    <AppLayout>
        <Alert v-if="page.same_as" variant="info" class="mb-3">
            Data menu ini sama dengan
            <Link :href="`/${page.same_as}`">{{ page.same_as_title }}</Link>.
        </Alert>

        <Tabs
            v-if="tabItems.length"
            :model-value="activeTab"
            :tabs="tabItems"
            @update:model-value="pindahTab"
        />

        <form v-if="tables.length" class="flex items-center justify-end gap-2 mb-3" @submit.prevent="submitSearch">
            <Input v-if="formDateFilter" v-model="tanggal" type="date" required aria-label="Tanggal data" class="w-auto" />
            <Input v-model="search" type="search" placeholder="Cari..." class="w-48 min-w-0" />
            <Button type="submit">Cari</Button>
        </form>

        <template v-if="tables.length">
            <template v-for="table in visibleTables" :key="table.name">
                <div v-if="kpiItems(table.kpi).length" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                    <Card v-for="item in kpiItems(table.kpi)" :key="item.label">
                        <div class="text-xs text-gray-500">{{ item.label }}</div>
                        <div class="text-2xl font-semibold">{{ item.value }}</div>
                    </Card>
                </div>

                <DataTable
                    class="mb-3"
                    :title="visibleTables.length > 1 ? table.title : null"
                    :columns="table.columns"
                    :rows="table.rows.data"
                    :total="table.rows.total"
                    :links="table.rows.links"
                    :badge-columns="table.name === 'checkin_pagi' ? ['checkin_pertama_hari_ini'] : (table.aksi === 'verifikasi_outlet_baru' ? ['status'] : [])"
                    :badge-variants="table.aksi === 'verifikasi_outlet_baru' ? warnaStatusOutletBaru : {}"
                >
                    <template v-if="table.aksi === 'verifikasi_outlet_baru'" #aksi="{ row }">
                        <div class="flex gap-1">
                            <Button v-if="row.foto_toko_id" tag="a" variant="light" :href="`/foto/${row.foto_toko_id}`" target="_blank" rel="noopener">Foto toko</Button>
                            <Button v-if="row.foto_rak_id" tag="a" variant="light" :href="`/foto/${row.foto_rak_id}`" target="_blank" rel="noopener">Foto rak</Button>
                            <template v-if="row.status === 'menunggu'">
                                <Button variant="success" @click="setujuiOutletBaru(row)">Setujui</Button>
                                <Button variant="outline-danger" @click="tolakOutletBaru(row)">Tolak</Button>
                            </template>
                        </div>
                    </template>
                </DataTable>
            </template>
        </template>
        <div v-else-if="!page.same_as" class="card">
            <div class="card-body text-gray-500">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </div>
        </div>
    </AppLayout>
</template>
