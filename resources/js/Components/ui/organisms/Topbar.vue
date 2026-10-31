<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/ui/molecules/Breadcrumb.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Tooltip from '@/Components/ui/atoms/Tooltip.vue';
import { useTambahMuatan } from '@/Composables/useTambahMuatan.js';

defineProps({ breadcrumbSlug: { type: String, default: null } });

// Shortcut truk membuka modal Atur Muatan Motoris (organisms/ModalTambahMuatan.vue) di halaman yang sedang dibuka;
// hanya untuk akun yang punya hak tambah di menu barang_canvassing.
const page = usePage();
const { buka: bukaTambahMuatan } = useTambahMuatan();

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
        <div>
            <h3 class="font-bold">Marketama Canvassing System</h3>
            <Breadcrumb :current-slug="breadcrumbSlug" />
        </div>
        <div class="flex items-center gap-2">
            <Tooltip v-if="page.props.bisaTambahMuatan" label="Tambah Loading Barang" align="end"><button type="button" class="shortcut-btn" aria-label="Tambah Loading Barang" @click="bukaTambahMuatan">
                    <Icon name="truck" />
                </button></Tooltip>
            <Tooltip label="Tambah Barang Canvassing" align="end"><Link class="shortcut-btn" href="/barang_canvassing/add">
                    <Icon name="boxes" />
                </Link></Tooltip>
            <Tooltip label="Peta Sebaran Sales" align="end"><Link class="shortcut-btn" href="/peta_sebaran_sales" aria-label="Peta Sebaran Sales">
                    <Icon name="map" />
                </Link></Tooltip>
            <Tooltip label="Design Polygon Area" align="end"><Link class="shortcut-btn" href="/master_area/design_area">
                    <Icon name="shapes" />
                </Link></Tooltip>
            <div class="relative" ref="notifWrapper">
                <Tooltip label="Notifikasi Transaksi" align="end"><a class="notif-bell" href="#"
                        aria-label="Notifikasi Transaksi" @click.prevent="notifOpen = !notifOpen">
                        <Icon name="bell" />
                    </a></Tooltip>
                <div class="dropdown-menu" :class="{ hidden: !notifOpen }">
                    <Icon name="bell-off" class="me-1" /> Tidak ada notifikasi baru
                </div>
            </div>
        </div>
    </div>
</template>
