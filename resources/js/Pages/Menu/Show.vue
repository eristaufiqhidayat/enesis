<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

function formatTotal(total) {
    return new Intl.NumberFormat('id-ID').format(total);
}
</script>

<template>
    <Head :title="`${page.title} · ${appName}`" />
    <AppLayout>
        <div class="flex flex-wrap items-center justify-between mb-3 gap-2">
            <h5 class="mb-0 font-semibold">{{ page.title }}</h5>
            <form v-if="tables.length" class="flex gap-2" @submit.prevent="submitSearch">
                <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Cari...">
                <button class="btn btn-sm btn-primary" type="submit">Cari</button>
            </form>
        </div>

        <div v-if="page.same_as" class="alert alert-info mb-3">
            Data menu ini sama dengan
            <Link :href="`/${page.same_as}`">{{ page.same_as_title }}</Link>.
        </div>

        <template v-if="tables.length">
            <div v-for="table in tables" :key="table.name" class="card mb-3">
                <div v-if="tables.length > 1" class="card-header font-semibold">{{ table.title }}</div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th v-for="label in Object.values(table.columns)" :key="label">{{ label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in table.rows.data" :key="index">
                                <td v-for="column in Object.keys(table.columns)" :key="column">{{ row[column] }}</td>
                            </tr>
                            <tr v-if="!table.rows.data.length">
                                <td :colspan="Object.keys(table.columns).length" class="text-center text-gray-500 py-4">Tidak ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer flex justify-between items-center">
                    <small class="text-gray-500">Total {{ formatTotal(table.rows.total) }} data</small>
                    <nav>
                        <ul class="pagination mb-0">
                            <li v-for="(link, index) in table.rows.links" :key="index" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                                <Link v-if="link.url" class="page-link" :href="link.url" v-html="link.label" preserve-state />
                                <span v-else class="page-link" v-html="link.label"></span>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </template>
        <div v-else-if="!page.same_as" class="card">
            <div class="card-body text-gray-500">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </div>
        </div>
    </AppLayout>
</template>
