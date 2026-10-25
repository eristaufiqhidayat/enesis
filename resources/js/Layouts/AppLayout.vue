<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Flash from '@/Components/ui/molecules/Flash.vue';
import Sidebar from '@/Components/ui/organisms/Sidebar.vue';
import SettingsSideNav from '@/Components/ui/organisms/SettingsSideNav.vue';
import Topbar from '@/Components/ui/organisms/Topbar.vue';
import { cariSubGrup } from '@/Components/ui/organisms/partials/subGrup.js';

// breadcrumbSlug: slug menu asal untuk halaman yang URL-nya tidak sama dengan slug menu (mis. /loading_barang/add).
defineProps({
    title: { type: String, default: '' },
    breadcrumbSlug: { type: String, default: null },
});

// Panel navigasi anak muncul otomatis untuk halaman di bawah sub-grup footer (lihat SettingsSideNav).
const page = usePage();
const adaPanel = computed(() => cariSubGrup(page.props.menu, page.props.currentSlug ?? null) !== null);
</script>

<template>
    <Sidebar />
    <main>
        <Topbar :breadcrumb-slug="breadcrumbSlug" />
        <Flash />
        <div :class="{ 'settings-layout': adaPanel }">
            <SettingsSideNav v-if="adaPanel" />
            <div class="settings-content">
                <slot />
            </div>
        </div>
    </main>
</template>
