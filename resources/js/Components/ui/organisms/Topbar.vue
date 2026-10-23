<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import Breadcrumb from '@/Components/ui/molecules/Breadcrumb.vue';

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
            <a class="shortcut-btn" href="/loading_barang/add" title="Tambah Loading Barang"><i class="fas fa-truck-loading"></i></a>
            <a class="shortcut-btn" href="/barang_canvassing/add" title="Tambah Barang Canvassing"><i class="fas fa-boxes"></i></a>
            <a class="shortcut-btn" href="/master_area/design_area" title="Design Polygon Area"><i class="fas fa-draw-polygon"></i></a>
            <div class="relative" ref="notifWrapper">
                <a class="notif-bell" href="#" title="Notifikasi Transaksi" aria-label="Notifikasi Transaksi" @click.prevent="notifOpen = !notifOpen"><i class="fas fa-bell"></i></a>
                <div class="dropdown-menu" :class="{ hidden: !notifOpen }">
                    <i class="fas fa-bell-slash me-1"></i> Tidak ada notifikasi baru
                </div>
            </div>
        </div>
    </div>
</template>
