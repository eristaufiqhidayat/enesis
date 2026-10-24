<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/ui/molecules/Breadcrumb.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Tooltip from '@/Components/ui/atoms/Tooltip.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
// Logout lewat form biasa (bukan kunjungan Inertia) karena halaman login adalah Blade.
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const notifOpen = ref(false);
const notifWrapper = ref(null);

function closeNotifOnOutsideClick(e) {
    if (notifWrapper.value && !notifWrapper.value.contains(e.target)) notifOpen.value = false;
}

onMounted(() => document.addEventListener('click', closeNotifOnOutsideClick));
onUnmounted(() => document.removeEventListener('click', closeNotifOnOutsideClick));
</script>

<template>
    <div class="topbar flex justify-between items-center gap-2 mb-3">
        <Breadcrumb />
        <div class="flex items-center gap-2">
            <Tooltip label="Tambah Loading Barang" align="end"><a class="shortcut-btn" href="/loading_barang/add"><i class="fas fa-truck-loading"></i></a></Tooltip>
            <Tooltip label="Tambah Barang Canvassing" align="end"><a class="shortcut-btn" href="/barang_canvassing/add"><i class="fas fa-boxes"></i></a></Tooltip>
            <Tooltip label="Design Polygon Area" align="end"><a class="shortcut-btn" href="/master_area/design_area"><i class="fas fa-draw-polygon"></i></a></Tooltip>
            <div class="relative" ref="notifWrapper">
                <Tooltip label="Notifikasi Transaksi" align="end"><a class="notif-bell" href="#" aria-label="Notifikasi Transaksi" @click.prevent="notifOpen = !notifOpen"><i class="fas fa-bell"></i></a></Tooltip>
                <div class="dropdown-menu" :class="{ hidden: !notifOpen }">
                    <i class="fas fa-bell-slash me-1"></i> Tidak ada notifikasi baru
                </div>
            </div>
            <div v-if="user" class="topbar-user">
                <i class="fas fa-user-circle"></i>
                <span class="topbar-user-name" :title="user.username">{{ user.name }}</span>
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <Button type="submit" variant="outline-secondary">Keluar</Button>
                </form>
            </div>
        </div>
    </div>
</template>
