<script setup>
import { computed } from 'vue';
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import DetailDepoTim from './partials/DetailDepoTim.vue';

// total dan tim kosong saat masih ditunda (lihat <Deferred> di bawah).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    depo: { type: Object, required: true },
    total: { type: Object, default: () => ({}) },
    tim: { type: Array, default: () => [] },
    periode: { type: Object, required: true },
    filter: { type: Object, required: true },
});

const appName = usePage().props.appName;
const periodeText = computed(() => (props.periode.sama ? props.periode.dari : `${props.periode.dari} – ${props.periode.sampai}`));

// URLSearchParams tidak boleh dipanggil langsung di template (bukan global yang diizinkan Vue di sana,
// "_ctx.URLSearchParams is not a constructor" - page jadi blank).
const kembaliHref = computed(() => `/summary_all_depo?${new URLSearchParams(props.filter)}`);
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="depo.nama" :subtitle="depo.distributor ?? null">
            <template #actions>
                <Button :tag="Link" variant="outline-secondary" :href="kembaliHref">Kembali ke Summary All Depo</Button>
            </template>
        </PageHeader>

        <Card>
            <div class="text-xs text-gray-500 mb-2">Periode: <b class="text-gray-700">{{ periodeText }}</b></div>

            <Deferred :data="['total', 'tim']">
            <template #fallback>
                <TableSkeleton />
            </template>

            <DetailDepoTim :total="total" :tim="tim" />
            </Deferred>
        </Card>
    </AppLayout>
</template>
