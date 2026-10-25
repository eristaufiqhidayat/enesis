<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import CariForm from './partials/CariForm.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { useAmbilData } from '@/Support/ambilData.js';

const props = defineProps({
    slug: { type: String, required: true },
    page: { type: Object, required: true },
    subtitle: { type: String, default: null },
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
// `tables` ditunda (Inertia defer), jadi form dan tab dihitung dari config, bukan dari hasil query.
const visibleTables = computed(() => props.tables);
const tabItems = computed(() => bertab.value
    ? props.page.tables.map((grup) => ({ key: grup.tab_key, label: grup.tab_label }))
    : []);
const grupAktif = computed(() => bertab.value ? props.page.tables.find((grup) => grup.tab_key === props.activeTab) : null);
const adaTabelKonfig = computed(() => (bertab.value ? (grupAktif.value?.tables.length ?? 0) : props.page.tables.length) > 0);
const formDateFilter = computed(() => bertab.value ? (grupAktif.value?.tables ?? []).some(tabelPakaiTanggal) : props.hasDateFilter);

// Kunjungan partial (Cari, pindah tab, pagination) mengambil ulang `tables` dalam satu request,
// sehingga tidak memunculkan skeleton. Daftar prop ini harus memuat semua prop yang bisa berubah.
const PROPS_MENU = ['tables', 'activeTab', 'search', 'tanggal', 'hasDateFilter', 'filter'];
const PROPS_TABEL = ['tables'];

function tabelPakaiTanggal(tabel) {
    return typeof tabel === 'object' && Boolean(tabel.date_filter_column);
}

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

// Tombol Cari aktif lagi hanya saat isian berbeda dari pencarian yang sedang tampil.
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({
        q: search.value.trim(),
        tanggal: formDateFilter.value ? tanggal.value : '',
        depo: pilihanFilter.value ? depo.value : '',
        koordinator: pilihanFilter.value ? koordinator.value : '',
    }),
    diterapkan: () => ({
        q: props.search.trim(),
        tanggal: formDateFilter.value ? props.tanggal : '',
        depo: pilihanFilter.value ? props.filter.depo : '',
        koordinator: pilihanFilter.value ? props.filter.koordinator : '',
    }),
});

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

    ambil(`/${props.slug}`, query, { preserveState: true, replace: true, only: PROPS_MENU });
}

function pindahTab(key) {
    const query = { tab: key };
    // Tabel tab tujuan belum di-query (lihat komentar di tabItems), jadi cek date_filter_column dari config.
    const grupTujuan = props.page.tables.find((grup) => grup.tab_key === key);
    const tabBaruPakaiTanggal = (grupTujuan?.tables ?? []).some(tabelPakaiTanggal);

    if (search.value) {
        query.q = search.value;
    }

    if (tabBaruPakaiTanggal && tanggal.value) {
        query.tanggal = tanggal.value;
    }

    router.get(`/${props.slug}`, query, { preserveScroll: true, only: PROPS_MENU });
}
</script>

<template>
    <Head :title="`${page.title} · ${appName}`" />
    <AppLayout>
        <PageHeader :title="page.title" :subtitle="subtitle" />

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

        <div v-if="adaTabelKonfig && (formDateFilter || adaPilihan('depo') || adaPilihan('koordinator'))" class="flex flex-wrap items-center justify-end gap-2 mb-3">
            <Input v-if="formDateFilter" v-model="tanggal" type="date" required aria-label="Tanggal data" class="w-auto" />
            <Select v-if="adaPilihan('depo')" v-model="depo" :options="pilihanFilter.depo" placeholder="Semua depo" aria-label="Depo" class="w-auto" />
            <Select v-if="adaPilihan('koordinator')" v-model="koordinator" :options="pilihanFilter.koordinator" placeholder="Semua koordinator" aria-label="Koordinator" class="w-auto max-w-[12rem]" />
        </div>

        <Deferred v-if="adaTabelKonfig" data="tables">
            <template #fallback>
                <Card body-class="overflow-x-auto">
                    <template #header>
                        <CariForm v-model="search" class="justify-end" :loading="memuat" :disabled="!berubah" @submit="submitSearch" />
                    </template>
                    <TableSkeleton />
                </Card>
            </template>

            <template v-if="tables.length">
                <template v-for="(table, index) in visibleTables" :key="table.name">
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
                        :pagination-only="PROPS_TABEL"
                        :badge-columns="table.name === 'checkin_pagi' ? ['checkin_pertama_hari_ini'] : (table.aksi === 'verifikasi_outlet_baru' ? ['status'] : [])"
                        :badge-variants="table.aksi === 'verifikasi_outlet_baru' ? warnaStatusOutletBaru : (warnaBadge[table.name] ?? {})"
                    >
                        <!-- Input cari hanya di header tabel pertama: pencarian berlaku untuk semua tabel di halaman ini. -->
                        <template v-if="index === 0" #header>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span v-if="visibleTables.length > 1" class="font-semibold">{{ table.title }}</span>
                                <CariForm v-model="search" class="ml-auto" :loading="memuat" :disabled="!berubah" @submit="submitSearch" />
                            </div>
                        </template>
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
            <Card v-else-if="!page.same_as" body-class="card-body text-gray-500">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </Card>
        </Deferred>
        <Card v-else-if="!page.same_as" body-class="card-body text-gray-500">
            Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
        </Card>
    </AppLayout>
</template>
