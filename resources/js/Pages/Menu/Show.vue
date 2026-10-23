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
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">{{ page.title }}</h5>
            <form v-if="tables.length" class="d-flex gap-2" @submit.prevent="submitSearch">
                <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Cari...">
                <button class="btn btn-sm btn-primary" type="submit">Cari</button>
            </form>
        </div>

        <div v-if="page.same_as" class="alert alert-info py-2">
            Data menu ini sama dengan
            <Link :href="`/${page.same_as}`">{{ page.same_as_title }}</Link>.
        </div>

        <template v-if="tables.length">
            <div v-for="table in tables" :key="table.name" class="card mb-3">
                <div v-if="tables.length > 1" class="card-header fw-semibold">{{ table.title }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th v-for="label in Object.values(table.columns)" :key="label" class="text-nowrap">{{ label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in table.rows.data" :key="index">
                                <td v-for="column in Object.keys(table.columns)" :key="column" class="text-nowrap">{{ row[column] }}</td>
                            </tr>
                            <tr v-if="!table.rows.data.length">
                                <td :colspan="Object.keys(table.columns).length" class="text-center text-muted py-4">Tidak ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">Total {{ formatTotal(table.rows.total) }} data</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
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
            <div class="card-body text-muted">
                Halaman ini di Marketama berupa peta atau grafik, jadi tidak ada tabel data yang ditarik.
            </div>
        </div>
    </AppLayout>
</template>
