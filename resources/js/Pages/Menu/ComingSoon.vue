<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';

const props = defineProps({
    title: { type: String, required: true },
    back: { type: String, required: true },
    backTitle: { type: String, required: true },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="back">
        <PageHeader :title="title" />
        <Card body-class="card-body text-gray-500">
            Form ini belum tersedia di versi Laravel. Data yang sudah ada bisa dilihat di
            <Link class="text-blue-600 hover:underline" :href="`/${back}`">{{ backTitle }}</Link>.
        </Card>
    </AppLayout>
</template>
