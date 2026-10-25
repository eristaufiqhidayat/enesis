<script setup>
import { computed, provide } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Flash from '@/Components/ui/molecules/Flash.vue';
import Sidebar from '@/Components/ui/organisms/Sidebar.vue';
import SettingsSideNav from '@/Components/ui/organisms/SettingsSideNav.vue';
import Topbar from '@/Components/ui/organisms/Topbar.vue';
import { cariSubGrup } from '@/Components/ui/organisms/partials/subGrup.js';
import { PAGE_HEADER_SLUG } from './pageHeaderContext.js';

// breadcrumbSlug: slug menu asal untuk halaman yang URL-nya tidak sama dengan slug menu (mis. /loading_barang/add).
// Dipakai juga oleh PageHeader (lewat provide di bawah) untuk mencari ikon menu yang relevan.
const props = defineProps({
    title: { type: String, default: '' },
    breadcrumbSlug: { type: String, default: null },
});

// Panel navigasi anak muncul otomatis untuk halaman di bawah sub-grup footer (lihat SettingsSideNav).
const page = usePage();
const adaPanel = computed(() => cariSubGrup(page.props.menu, page.props.currentSlug ?? null) !== null);

provide(PAGE_HEADER_SLUG, computed(() => props.breadcrumbSlug ?? page.props.currentSlug ?? null));
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
