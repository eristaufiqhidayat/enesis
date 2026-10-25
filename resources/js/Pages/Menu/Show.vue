<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Select from '@/Components/ui/atoms/Select.vue';
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
    // Filter depo / koordinator untuk tabel dengan sumber bertanggal (lihat SumberBertanggal).
    filter: { type: Object, default: () => ({ depo: null, koordinator: null }) },
});

const search = ref(props.search);
const tanggal = ref(props.tanggal);
const depo = ref(props.filter.depo ?? '');
const koordinator = ref(props.filter.koordinator ?? '');
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
watch(() => props.filter, (value) => {
    depo.value = value.depo ?? '';
    koordinator.value = value.koordinator ?? '';
});

// Pilihan dropdown depo/koordinator dari tabel tab aktif yang menyediakannya.
const pilihanFilter = computed(() => visibleTables.value.find((table) => table.filter_pilihan)?.filter_pilihan ?? null);
const adaPilihan = (kunci) => Object.keys(pilihanFilter.value?.[kunci] ?? {}).length > 0;

// KPI dari backend: daftar { label, value }.
function kpiItems(kpi) {
    return Array.isArray(kpi) ? kpi : [];
}

const kolomGridKpi = { 1: 'sm:grid-cols-1', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };

function formatAngka(nilai) {
    return typeof nilai === 'number' ? new Intl.NumberFormat('id-ID').format(nilai) : nilai;
}

// Warna badge per tabel: "Belum checkin" merah, jam checkin di luar rentang kuning.
const warnaBadge = {
    checkin_pagi: { checkin_pertama_hari_ini: { 'Belum checkin': 'danger', '*': 'warning' } },
};

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

    if (pilihanFilter.value) {
        if (depo.value) {
            query.depo = depo.value;
        }

        if (koordinator.value) {
            query.koordinator = koordinator.value;
        }
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
            <Select v-if="adaPilihan('depo')" v-model="depo" :options="pilihanFilter.depo" placeholder="Semua depo" aria-label="Depo" class="w-auto" />
            <Select v-if="adaPilihan('koordinator')" v-model="koordinator" :options="pilihanFilter.koordinator" placeholder="Semua koordinator" aria-label="Koordinator" class="w-auto max-w-[12rem]" />
            <Input v-model="search" type="search" placeholder="Cari..." class="w-48 min-w-0" />
            <Button type="submit">Cari</Button>
        </form>

        <template v-if="tables.length">
            <template v-for="table in visibleTables" :key="table.name">
                <Alert v-if="table.keterangan" variant="warning" class="mb-3">{{ table.keterangan }}</Alert>

                <div v-if="kpiItems(table.kpi).length" :class="['grid grid-cols-1 gap-3 mb-3', kolomGridKpi[kpiItems(table.kpi).length] ?? 'sm:grid-cols-4']">
                    <Card v-for="item in kpiItems(table.kpi)" :key="item.label">
                        <div class="text-xs text-gray-500">{{ item.label }}</div>
                        <div class="text-2xl font-semibold">{{ formatAngka(item.value) }}</div>
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
                    :badge-variants="table.aksi === 'verifikasi_outlet_baru' ? warnaStatusOutletBaru : (warnaBadge[table.name] ?? {})"
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
                    <template v-else-if="table.aksi === 'foto'" #aksi="{ row }">
                        <Button v-if="row.foto_id" tag="a" variant="light" :href="`/foto/${row.foto_id}`" target="_blank" rel="noopener">Foto</Button>
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
