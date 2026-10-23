<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

const props = defineProps({
    slug: { type: String, required: true },
    page: { type: Object, required: true },
    tables: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    tanggal: { type: String, default: null },
    hasDateFilter: { type: Boolean, default: false },
    kpi: { type: Object, default: null },
});

const search = ref(props.search);
const tanggal = ref(props.tanggal);
const appName = usePage().props.appName;

const kpiItems = computed(() => props.kpi ? [
    { label: 'Sudah Absen', value: props.kpi.sudah_absen },
    { label: 'Belum Absen', value: props.kpi.belum_absen },
    { label: 'Terlambat', value: props.kpi.terlambat },
] : []);

function submitSearch() {
    const query = { q: search.value };

    if (props.hasDateFilter) {
        query.tanggal = tanggal.value;
    }

    router.get(`/${props.slug}`, query, { preserveState: true, replace: true });
}
</script>

<template>
    <Head :title="`${page.title} · ${appName}`" />
    <AppLayout>
        <div class="flex flex-wrap items-center justify-between mb-3 gap-2">
            <h5 class="mb-0 font-semibold">{{ page.title }}</h5>
            <form v-if="tables.length" class="flex gap-2" @submit.prevent="submitSearch">
                <Input v-if="hasDateFilter" v-model="tanggal" type="date" required aria-label="Tanggal data" />
                <Input v-model="search" type="search" placeholder="Cari..." />
                <Button type="submit">Cari</Button>
            </form>
        </div>

        <Alert v-if="page.same_as" variant="info" class="mb-3">
            Data menu ini sama dengan
            <Link :href="`/${page.same_as}`">{{ page.same_as_title }}</Link>.
        </Alert>

        <div v-if="kpiItems.length" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <Card v-for="item in kpiItems" :key="item.label">
                <div class="text-xs text-gray-500">{{ item.label }}</div>
                <div class="text-2xl font-semibold">{{ item.value }}</div>
            </Card>
        </div>

        <template v-if="tables.length">
            <DataTable
                v-for="table in tables"
                :key="table.name"
                class="mb-3"
                :title="tables.length > 1 ? table.title : null"
                :columns="table.columns"
                :rows="table.rows.data"
                :total="table.rows.total"
                :links="table.rows.links"
                :badge-columns="slug === 'checkin_pagi' ? ['checkin_pertama_hari_ini'] : []"
            />
        </template>
        <div v-else-if="!page.same_as" class="card">
            <div class="card-body text-gray-500">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </div>
        </div>
    </AppLayout>
</template>
