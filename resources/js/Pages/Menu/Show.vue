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

// Halaman bertab hanya menampilkan tabel aktif; halaman lain tetap menumpuk semua tabel.
const bertab = computed(() => props.page.tabs === true);
const visibleTables = computed(() => bertab.value ? props.tables.filter((table) => table.name === props.activeTab) : props.tables);
const tabItems = computed(() => props.tables.map((table) => ({ key: table.name, label: table.tab_label })));
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
    const tabBaru = props.tables.find((table) => table.name === key);

    if (search.value) {
        query.q = search.value;
    }

    if (tabBaru?.date_filter && tanggal.value) {
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
            v-if="bertab && tables.length"
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
                    :badge-columns="table.name === 'checkin_pagi' ? ['checkin_pertama_hari_ini'] : []"
                />
            </template>
        </template>
        <div v-else-if="!page.same_as" class="card">
            <div class="card-body text-gray-500">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </div>
        </div>
    </AppLayout>
</template>
