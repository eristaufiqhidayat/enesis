<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/ui/molecules/Breadcrumb.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Tooltip from '@/Components/ui/atoms/Tooltip.vue';

defineProps({ breadcrumbSlug: { type: String, default: null } });

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

function keluar() {
    router.post('/logout');
}

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
            <Tooltip label="Tambah Loading Barang" align="end"><Link class="shortcut-btn" href="/loading_barang/add">
                    <Icon name="truck" />
                </Link></Tooltip>
            <Tooltip label="Tambah Barang Canvassing" align="end"><Link class="shortcut-btn" href="/barang_canvassing/add">
                    <Icon name="boxes" />
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
            <div v-if="user" class="topbar-user">
                <Icon name="circle-user" />
                <span class="topbar-user-name" :title="user.username">{{ user.name }}</span>
                <Button variant="outline-secondary" @click="keluar">Keluar</Button>
            </div>
        </div>
    </div>
</template>
