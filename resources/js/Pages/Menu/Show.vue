<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

const props = defineProps({
    slug: { type: String, required: true },
    page: { type: Object, required: true },
    tables: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const search = ref(props.search);
const appName = usePage().props.appName;

function submitSearch() {
    router.get(`/${props.slug}`, { q: search.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head :title="`${page.title} · ${appName}`" />
    <AppLayout>
        <div class="flex flex-wrap items-center justify-between mb-3 gap-2">
            <h5 class="mb-0 font-semibold">{{ page.title }}</h5>
            <form v-if="tables.length" class="flex gap-2" @submit.prevent="submitSearch">
                <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Cari...">
                <Button type="submit">Cari</Button>
            </form>
        </div>

        <Alert v-if="page.same_as" variant="info" class="mb-3">
            Data menu ini sama dengan
            <Link :href="`/${page.same_as}`">{{ page.same_as_title }}</Link>.
        </Alert>

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
            />
        </template>
        <div v-else-if="!page.same_as" class="card">
            <div class="card-body text-gray-500">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </div>
        </div>
    </AppLayout>
</template>
