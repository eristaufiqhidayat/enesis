<script setup>
import Flash from '@/Components/ui/molecules/Flash.vue';
import Sidebar from '@/Components/ui/organisms/Sidebar.vue';
import Topbar from '@/Components/ui/organisms/Topbar.vue';

// breadcrumbSlug: slug menu asal untuk halaman yang URL-nya tidak sama dengan slug menu (mis. /loading_barang/add).
defineProps({
    title: { type: String, default: '' },
    breadcrumbSlug: { type: String, default: null },
});
</script>

<template>
    <Sidebar />
    <main>
        <Topbar :breadcrumb-slug="breadcrumbSlug" />
        <Flash />
        <slot />
    </main>
</template>
