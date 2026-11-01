<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import DatePicker from '@/Components/ui/atoms/DatePicker.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import CariForm from './partials/CariForm.vue';
import DetailArea from './partials/DetailArea.vue';
import DetailDepo from './partials/DetailDepo.vue';
import DetailDepoEdit from './partials/DetailDepoEdit.vue';
import DetailDepoPeta from './partials/DetailDepoPeta.vue';
import RuteHarianPeta from './partials/RuteHarianPeta.vue';
import DetailLapLoadingBarang from './partials/DetailLapLoadingBarang.vue';
import DetailLoadingBarang from './partials/DetailLoadingBarang.vue';
import DetailMuatanCanvassing from './partials/DetailMuatanCanvassing.vue';
import RiwayatDistributor from './partials/RiwayatDistributor.vue';
import TandaOutlet from './partials/TandaOutlet.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import TabsHalaman from '@/Components/ui/molecules/TabsHalaman.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { useTambahMuatan } from '@/Composables/useTambahMuatan.js';
import { useAmbilData } from '@/Support/ambilData.js';
import { tanggalSingkat, tanggalWaktuPendek } from '@/Support/format.js';
import { infoRiwayat as infoRiwayatBaris, riwayatTabel, tabelLastSeen } from '@/Support/riwayat.js';

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
    filter: { type: Object, default: () => ({ depo: null, koordinator: null, status: null }) },
    // Punya hak tambah di menu ini (tombol Tambah yang membuka modal form, saat ini hanya barang_canvassing).
    bisaTambah: { type: Boolean, default: false },
});

const search = ref(props.search);
// Baris depo yang nama-nya diklik di tabel master_depo; null = modal slider tertutup.
const depoTerpilih = ref(null);
// Baris area yang kode atau namanya diklik di tabel master_area; null = drawer tertutup.
const areaTerpilih = ref(null);
// Baris outlet yang namanya diklik di tabel mk_outlet (menu All Outlet); null = drawer Tanda Outlet tertutup.
const outletTerpilih = ref(null);
// Modal Edit depo terbuka di atas drawer detail depo.
const depoDiedit = ref(false);
// Baris depo yang petanya dibuka di drawer dari tombol Peta.
const depoPeta = ref(null);
// Baris Tracking Last Seen yang posisi terakhirnya dibuka di drawer peta dari tautan Lihat peta.
const posisiTerpilih = ref(null);
// Baris lap_loading_barang yang detailnya dibuka di drawer dari tautan Trans Code; null = tertutup.
const lapTerpilih = ref(null);
// Baris loading_barang yang detailnya dibuka di drawer dari tautan Kode Transaksi; null = tertutup.
const loadingTerpilih = ref(null);
// Baris barang_canvassing (satu muatan) yang daftar produknya dibuka di drawer dari klik baris tabel; null = tertutup.
const muatanTerpilih = ref(null);
// Muatan dibatalkan dari drawer: tutup drawer dan muat ulang tabel (muatan itu sudah tidak ada di daftar).
function muatanDibatalkan() {
    muatanTerpilih.value = null;
    muatTabelUlang();
}
// Item muatan diubah di drawer: muat ulang tabel daftar supaya ringkasan SKU/qty barisnya ikut terbarui.
function muatTabelUlang() {
    router.reload({ only: ['tables'], preserveScroll: true });
}
// Tombol Tambah di header membuka modal Atur Muatan Motoris (organisms/ModalTambahMuatan.vue, dipasang di AppLayout).
const { buka: bukaTambahMuatan } = useTambahMuatan();
// Distributor { id, nama } yang riwayat transaksinya dibuka di drawer kedua, di atas drawer detail; null = tertutup.
const distributorTerpilih = ref(null);
const kolomLapLoadingBarang = computed(() => visibleTables.value.find((table) => table.name === 'lap_loading_barang')?.columns ?? {});
const kolomLoadingBarang = computed(() => visibleTables.value.find((table) => table.name === 'loading_barang')?.columns ?? {});

// Baris tabel (Tracking Last Seen, rekap Motoris Ditandai) yang rutenya dibuka di drawer Riwayat:
// { orang, slug, idParam, tanggal }. Tujuan dan izinnya ditentukan Support/riwayat.js dari prop bersama slugRiwayat.
const riwayatTerpilih = ref(null);
const infoRiwayat = (table, row, tanggalPilih = null) => infoRiwayatBaris(table.name, row, usePage().props.slugRiwayat, tanggalPilih);

function tutupDetailDepo() {
    depoTerpilih.value = null;
    depoDiedit.value = false;
}
const tanggal = ref(props.tanggal);
const depo = ref(props.filter.depo ?? '');
const koordinator = ref(props.filter.koordinator ?? '');
const status = ref(props.filter.status ?? '');
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
    status.value = value.status ?? '';
});

// Pilihan dropdown depo/koordinator dari tabel tab aktif yang menyediakannya.
const pilihanFilter = computed(() => visibleTables.value.find((table) => table.filter_pilihan)?.filter_pilihan ?? null);
// Pilihan status (mis. kebaruan posisi di Tracking Last Seen) dari tabel tab aktif yang menyediakannya.
const pilihanStatus = computed(() => visibleTables.value.find((table) => table.pilihan_status)?.pilihan_status ?? null);
const adaPilihan = (kunci) => Object.keys(pilihanFilter.value?.[kunci] ?? {}).length > 0;

// KPI dari backend: daftar { label, value }.
function kpiItems(kpi) {
    return Array.isArray(kpi) ? kpi : [];
}

const kolomGridKpi = { 1: 'sm:grid-cols-1', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };

function formatAngka(nilai) {
    return typeof nilai === 'number' ? new Intl.NumberFormat('id-ID').format(nilai) : nilai;
}

// Warna badge kolom Terakhir Terlihat di Tracking Last Seen, menurut label status (SumberLastSeen::STATUS).
const warnaKebaruan = { 'Hari ini': 'success', '1–7 hari lalu': 'warning', 'Lebih dari 7 hari': 'danger', 'Belum ada data': 'secondary' };

// Warna badge per tabel: "Belum checkin" merah, jam checkin di luar rentang kuning.
const warnaBadge = {
    checkin_pagi: { checkin_pertama_hari_ini: { 'Belum checkin': 'danger', '*': 'warning' } },
    loading_barang: { status_retur: { 'Belum Retur': 'danger', 'Sudah Retur': 'success' } },
    barang_canvassing: { status: { Disiapkan: 'info', Diterima: 'secondary', Dikembalikan: 'warning', Selesai: 'success' } },
    tanda_motoris_rekap: { status: { Aktif: 'danger', Dicabut: 'secondary' } },
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
        status: pilihanStatus.value ? status.value : '',
    }),
    diterapkan: () => ({
        q: props.search.trim(),
        tanggal: formDateFilter.value ? props.tanggal : '',
        depo: pilihanFilter.value ? props.filter.depo : '',
        koordinator: pilihanFilter.value ? props.filter.koordinator : '',
        status: pilihanStatus.value ? props.filter.status : '',
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

    if (pilihanStatus.value && status.value) {
        query.status = status.value;
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

// Sort dan filter disimpan di query string per tabel (sort[<tabel>], arah[<tabel>], filter[<tabel>][<kolom>]).
// Query lain dan tab tetap dari URL. Mengubah sort atau filter mengembalikan tabel itu ke halaman 1.
function ubahQueryTabel(table, ubah) {
    const query = Object.fromEntries(new URLSearchParams(window.location.search));
    const awalan = [`sort[${table.name}]`, `arah[${table.name}]`, `filter[${table.name}][`, `operator[${table.name}][`];

    delete query[`page_${table.name}`];

    for (const kunci of Object.keys(query)) {
        if (awalan.some((a) => kunci.startsWith(a))) {
            delete query[kunci];
        }
    }

    ubah(query);

    router.get(window.location.pathname, query, { preserveScroll: true, only: PROPS_TABEL });
}

function urutkan(table, { kolom, arah }) {
    ubahQueryTabel(table, (query) => {
        if (arah) {
            query[`sort[${table.name}]`] = kolom;
            query[`arah[${table.name}]`] = arah;
        }
    });
}

function filterTabel(table, { nilai, operator }) {
    ubahQueryTabel(table, (query) => {
        for (const [kolom, teks] of Object.entries(nilai)) {
            if (teks) {
                query[`filter[${table.name}][${kolom}]`] = teks;
            }
        }

        // Operator '=' adalah bawaan, jadi hanya operator lain yang masuk query string.
        for (const [kolom, op] of Object.entries(operator)) {
            if (nilai[kolom] && op !== '=') {
                query[`operator[${table.name}][${kolom}]`] = op;
            }
        }
    });
}
</script>

<template>
    <Head :title="`${page.title} · ${appName}`" />
    <AppLayout>
        <PageHeader :title="page.title" :subtitle="subtitle">
            <template v-if="slug === 'barang_canvassing' && bisaTambah" #actions>
                <Button @click="bukaTambahMuatan"><Icon name="plus" class="me-1" /> Tambah</Button>
            </template>
        </PageHeader>

        <TabsHalaman />

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

        <Deferred v-if="adaTabelKonfig" data="tables">
            <template #fallback>
                <Card body-class="overflow-x-auto">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <DatePicker v-if="formDateFilter" v-model="tanggal" aria-label="Tanggal data" class="w-36" />
                            <Select v-if="adaPilihan('depo')" v-model="depo" :options="pilihanFilter.depo" placeholder="Semua depo" aria-label="Depo" class="w-auto" />
                            <Select v-if="adaPilihan('koordinator')" v-model="koordinator" :options="pilihanFilter.koordinator" placeholder="Semua koordinator" aria-label="Koordinator" class="w-auto max-w-[12rem]" />
                            <CariForm v-model="search" :loading="memuat" :disabled="!berubah" @submit="submitSearch" />
                        </div>
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
                        :tinggi-penuh="page.tinggi_penuh === true"
                        :tinggi-tetap="page.tinggi_tetap === true"
                        :baris-bisa-diklik="table.name === 'barang_canvassing'"
                        @klik-baris="muatanTerpilih = $event"
                        :sticky-columns="page.sticky_columns ?? []"
                        :title="visibleTables.length > 1 ? table.title : null"
                        :columns="table.columns"
                        :rows="table.rows.data"
                        :total="table.rows.total"
                        :links="table.rows.links"
                        :pagination-only="PROPS_TABEL"
                        :badge-columns="table.name === 'checkin_pagi' ? ['checkin_pertama_hari_ini'] : (table.name === 'loading_barang' ? ['status_retur'] : (['barang_canvassing', 'tanda_motoris_rekap'].includes(table.name) ? ['status'] : (table.aksi === 'verifikasi_outlet_baru' ? ['status'] : [])))"
                        :badge-variants="table.aksi === 'verifikasi_outlet_baru' ? warnaStatusOutletBaru : (warnaBadge[table.name] ?? {})"
                        :sortable="table.sortable_columns"
                        :sort="table.sort"
                        @sort="urutkan(table, $event)"
                        :filterable="table.filterable_columns"
                        :filter-values="table.filter_kolom"
                        :filter-angka="table.filter_angka"
                        :filter-operator="table.filter_operator"
                        :filter-tanggal="table.filter_tanggal"
                        @filter="filterTabel(table, $event)"
                    >
                        <!-- Input cari hanya di header tabel pertama: pencarian berlaku untuk semua tabel di halaman ini. -->
                        <template v-if="index === 0" #header>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span v-if="visibleTables.length > 1" class="font-semibold">{{ table.title }}</span>
                                <div class="flex flex-wrap items-center gap-2 ml-auto">
                                    <DatePicker v-if="formDateFilter" v-model="tanggal" aria-label="Tanggal data" class="w-36" />
                                    <Select v-if="adaPilihan('depo')" v-model="depo" :options="pilihanFilter.depo" placeholder="Semua depo" aria-label="Depo" class="w-auto" />
                                    <Select v-if="adaPilihan('koordinator')" v-model="koordinator" :options="pilihanFilter.koordinator" placeholder="Semua koordinator" aria-label="Koordinator" class="w-auto max-w-[12rem]" />
                                    <Select v-if="pilihanStatus" v-model="status" :options="pilihanStatus" placeholder="Semua status" aria-label="Status" class="w-auto" />
                                    <CariForm v-model="search" :loading="memuat" :disabled="!berubah" @submit="submitSearch" />
                                </div>
                            </div>
                        </template>
                        <template v-if="tabelLastSeen.includes(table.name)" #cell-terakhir_terlihat="{ row }">
                            <Badge :variant="warnaKebaruan[row.status] ?? 'secondary'" :title="row.sejak ? row.terakhir_terlihat : null">{{ row.sejak ?? row.terakhir_terlihat }}</Badge>
                        </template>
                        <template v-if="table.name in riwayatTabel" #cell-riwayat="{ row }">
                            <button v-if="infoRiwayat(table, row)" type="button" class="tautan-depo" @click="riwayatTerpilih = infoRiwayat(table, row)">Lihat riwayat</button>
                        </template>
                        <template v-if="table.name === 'tracking_last_seen_sales'" #cell-ditandai="{ row }">
                            <template v-if="row.ditandai > 0">
                                <button
                                    v-if="infoRiwayat(table, row, row.ditandai_tanggal)"
                                    type="button"
                                    class="tautan-depo"
                                    :title="`Tanda terakhir: ${tanggalSingkat(row.ditandai_tanggal)}`"
                                    @click="riwayatTerpilih = infoRiwayat(table, row, row.ditandai_tanggal)"
                                >
                                    <Badge variant="danger">{{ row.ditandai }} tanda</Badge>
                                </button>
                                <Badge v-else variant="danger" :title="`Tanda terakhir: ${tanggalSingkat(row.ditandai_tanggal)}`">{{ row.ditandai }} tanda</Badge>
                            </template>
                        </template>
                        <template v-if="table.name === 'mk_outlet'" #cell-ditandai="{ row }">
                            <button type="button" class="tautan-depo" @click="outletTerpilih = row">
                                <Badge v-if="row.ditandai > 0" variant="danger">{{ row.ditandai }} tanda</Badge>
                                <template v-else>Tandai</template>
                            </button>
                        </template>
                        <template v-if="tabelLastSeen.includes(table.name)" #cell-lokasi="{ row }">
                            <button v-if="row.latitude !== null && row.longitude !== null" type="button" class="tautan-depo" @click="posisiTerpilih = row">Lihat peta</button>
                        </template>
                        <template v-if="table.name === 'master_depo'" #cell-nama="{ row }">
                            <button type="button" class="tautan-depo" @click="depoTerpilih = row">{{ row.nama }}</button>
                        </template>
                        <template v-if="table.name === 'master_area'" #cell-kode="{ row }">
                            <button type="button" class="tautan-depo" @click="areaTerpilih = row">{{ row.kode }}</button>
                        </template>
                        <template v-if="table.name === 'master_area'" #cell-nama="{ row }">
                            <button type="button" class="tautan-depo" @click="areaTerpilih = row">{{ row.nama }}</button>
                        </template>
                        <template v-if="table.name === 'loading_barang'" #cell-kode_transaksi="{ row }">
                            <button type="button" class="tautan-depo" @click="loadingTerpilih = row">{{ row.kode_transaksi }}</button>
                        </template>
                        <template v-if="table.name === 'lap_loading_barang'" #cell-trans_code="{ row }">
                            <button type="button" class="tautan-depo" @click="lapTerpilih = row">{{ row.trans_code }}</button>
                        </template>
                        <template v-if="table.name === 'barang_canvassing'" #cell-receive_datetime="{ row }">
                            <template v-if="tanggalWaktuPendek(row.receive_datetime)">
                                {{ tanggalWaktuPendek(row.receive_datetime).tanggal }}
                                <Badge variant="light">{{ tanggalWaktuPendek(row.receive_datetime).jam }}</Badge>
                            </template>
                            <template v-else>{{ row.receive_datetime }}</template>
                        </template>
                        <template v-if="table.name === 'barang_canvassing'" #cell-return_datetime="{ row }">
                            <template v-if="tanggalWaktuPendek(row.return_datetime)">
                                {{ tanggalWaktuPendek(row.return_datetime).tanggal }}
                                <Badge variant="light">{{ tanggalWaktuPendek(row.return_datetime).jam }}</Badge>
                            </template>
                            <template v-else>{{ row.return_datetime }}</template>
                        </template>
                        <template v-if="table.name === 'lap_loading_barang'" #cell-datetime_trans="{ row }">
                            <template v-if="tanggalWaktuPendek(row.datetime_trans)">
                                {{ tanggalWaktuPendek(row.datetime_trans).tanggal }}
                                <Badge variant="light">{{ tanggalWaktuPendek(row.datetime_trans).jam }}</Badge>
                            </template>
                            <template v-else>{{ row.datetime_trans }}</template>
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
                        <template v-else-if="table.name === 'master_depo'" #aksi="{ row }">
                            <Button variant="light" @click="depoPeta = row">Peta</Button>
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
        <Modal :show="depoTerpilih !== null" :title="depoTerpilih?.nama ?? ''" placement="kanan" @close="tutupDetailDepo">
            <DetailDepo v-if="depoTerpilih" :depo="depoTerpilih" />
            <template #footer>
                <div class="flex w-full items-center justify-between gap-2">
                    <div class="flex flex-col text-xs text-gray-500">
                        <span>Diupdate oleh: {{ depoTerpilih?.diupdate_oleh || '-' }}</span>
                        <span>Diupdate pada: {{ depoTerpilih?.diupdate_pada || '-' }}</span>
                    </div>
                    <Button variant="primary" @click="depoDiedit = true">Edit</Button>
                </div>
            </template>
        </Modal>
        <!-- Drawer Edit di atas drawer detail: Modal paling akhir di DOM tampil paling depan. -->
        <Modal :show="depoDiedit" title="Edit Depo" placement="kanan" @close="depoDiedit = false">
            <DetailDepoEdit v-if="depoDiedit && depoTerpilih" :depo="depoTerpilih" @batal="depoDiedit = false" />
        </Modal>
        <!-- Drawer detail transaksi, dibuka dari tautan Trans Code di tabel lap_loading_barang. -->
        <Modal :show="lapTerpilih !== null" :title="lapTerpilih?.trans_code ?? ''" placement="kanan" @close="lapTerpilih = null">
            <DetailLapLoadingBarang v-if="lapTerpilih" :row="lapTerpilih" :columns="kolomLapLoadingBarang" @buka-distributor="distributorTerpilih = $event" />
        </Modal>
        <!-- Drawer detail loading barang, dibuka dari tautan Kode Transaksi di tabel loading_barang. -->
        <Modal :show="loadingTerpilih !== null" :title="loadingTerpilih?.kode_transaksi ?? ''" placement="kanan" @close="loadingTerpilih = null">
            <DetailLoadingBarang v-if="loadingTerpilih" :row="loadingTerpilih" :columns="kolomLoadingBarang" />
        </Modal>
        <!-- Drawer produk muatan canvassing, dibuka dari klik baris di tabel barang_canvassing. -->
        <Modal :show="muatanTerpilih !== null" :title="muatanTerpilih?.canvassing_no ?? ''" placement="kanan" size="2xl" @close="muatanTerpilih = null">
            <DetailMuatanCanvassing v-if="muatanTerpilih" :key="muatanTerpilih.source_id" :row="muatanTerpilih" @diubah="muatTabelUlang" @dibatalkan="muatanDibatalkan" />
        </Modal>
        <!-- Drawer riwayat transaksi distributor, dibuka dari Distributor Name di drawer detail (tampil di atasnya). -->
        <Modal :show="distributorTerpilih !== null" :title="`Riwayat ${distributorTerpilih?.nama ?? ''}`" placement="kanan" @close="distributorTerpilih = null">
            <RiwayatDistributor v-if="distributorTerpilih" :key="distributorTerpilih.nama" :distributor="distributorTerpilih" />
        </Modal>
        <!-- Drawer detail area, dibuka dari tautan Kode atau Nama di tabel master_area. -->
        <Modal :show="areaTerpilih !== null" :title="areaTerpilih?.nama ?? ''" placement="kanan" @close="areaTerpilih = null">
            <DetailArea v-if="areaTerpilih" :area="areaTerpilih" />
        </Modal>
        <!-- Drawer Tanda Outlet, dibuka dari kolom Ditandai di tabel mk_outlet (menu All Outlet). -->
        <Modal :show="outletTerpilih !== null" :title="`Tanda Outlet · ${outletTerpilih?.nama ?? ''}`" placement="kanan" size="sm" @close="outletTerpilih = null">
            <TandaOutlet v-if="outletTerpilih" :url="'/outlet_all/tanda'" :outlet-id="outletTerpilih.id" @berubah="muatTabelUlang" />
        </Modal>
        <!-- Drawer peta posisi terakhir motoris, dibuka dari tautan Lihat peta di tabel Tracking Last Seen. -->
        <Modal :show="posisiTerpilih !== null" :title="posisiTerpilih ? `Posisi ${posisiTerpilih.nama}` : ''" placement="kanan" @close="posisiTerpilih = null">
            <DetailDepoPeta
                v-if="posisiTerpilih"
                :depo="posisiTerpilih"
                ikon="motoris"
                :rincian="`Terakhir terlihat ${posisiTerpilih.terakhir_terlihat}${posisiTerpilih.sejak ? ` (${posisiTerpilih.sejak})` : ''}`"
                drawer
            />
        </Modal>
        <!-- Modal rute harian, dibuka dari tombol Lihat riwayat di tabel Tracking Last Seen: hampir sepenuh layar (butuh ruang lebih untuk peta + daftar kunjungan). -->
        <Modal :show="riwayatTerpilih !== null" :title="riwayatTerpilih ? `Riwayat ${riwayatTerpilih.orang.nama}` : ''" size="full" @close="riwayatTerpilih = null">
            <RuteHarianPeta
                v-if="riwayatTerpilih"
                :orang="riwayatTerpilih.orang"
                :id="riwayatTerpilih.orang.id"
                :id-param="riwayatTerpilih.idParam"
                :tanggal="riwayatTerpilih.tanggal"
                :locations-url="`/${riwayatTerpilih.slug}/locations`"
                :tanda-url="riwayatTerpilih.idParam === 'sales_id' ? '/tracking_sales/tanda' : null"
            />
        </Modal>
        <!-- Drawer peta lokasi depo, dibuka dari tombol Peta di tabel master_depo. -->
        <Modal :show="depoPeta !== null" :title="depoPeta ? `Peta ${depoPeta.nama}` : ''" placement="kanan" @close="depoPeta = null">
            <DetailDepoPeta v-if="depoPeta" :depo="depoPeta" drawer />
        </Modal>
    </AppLayout>
</template>
