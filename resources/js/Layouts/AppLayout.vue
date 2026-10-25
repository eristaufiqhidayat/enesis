<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Sidebar from '@/Components/ui/organisms/Sidebar.vue';
import Topbar from '@/Components/ui/organisms/Topbar.vue';

// breadcrumbSlug: slug menu asal untuk halaman yang URL-nya tidak sama dengan slug menu (mis. /loading_barang/add).
defineProps({
    title: { type: String, default: '' },
    breadcrumbSlug: { type: String, default: null },
});

// Pesan hasil aksi dari redirect ->with('sukses'|'galat') (dibagikan HandleInertiaRequests).
const flash = computed(() => usePage().props.flash ?? {});
</script>

<template>
    <Sidebar />
    <main>
        <Topbar :breadcrumb-slug="breadcrumbSlug" />
        <Alert v-if="flash.sukses" variant="success" class="mb-3" role="status">{{ flash.sukses }}</Alert>
        <Alert v-if="flash.galat" variant="danger" class="mb-3" role="alert">{{ flash.galat }}</Alert>
        <slot />
    </main>
</template>
