<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const menu = computed(() => page.props.menu ?? []);
const currentSlug = computed(() => page.props.currentSlug ?? '');

const manuallyToggled = ref({});
const notifOpen = ref(false);
const notifWrapper = ref(null);

function closeNotifOnOutsideClick(e) {
    if (notifWrapper.value && !notifWrapper.value.contains(e.target)) notifOpen.value = false;
}

onMounted(() => document.addEventListener('click', closeNotifOnOutsideClick));
onUnmounted(() => document.removeEventListener('click', closeNotifOnOutsideClick));

function isChildOpen(item) {
    if (!item.children) return false;
    if (item.id in manuallyToggled.value) return manuallyToggled.value[item.id];
    return item.children.some((child) => child.slug === currentSlug.value);
}

function toggleSubmenu(item) {
    manuallyToggled.value = { ...manuallyToggled.value, [item.id]: !isChildOpen(item) };
}
</script>

<template>
    <nav class="sidebar">
        <div class="brand"><i class="fas fa-leaf me-1"></i> {{ $page.props.appName ?? 'Enesis Canvassing' }}</div>
        <ul class="nav flex flex-col list-none m-0 p-0">
            <template v-for="section in menu" :key="section.label ?? 'root'">
                <li v-if="section.label" class="section-label">{{ section.label }}</li>
                <template v-for="item in section.items" :key="item.id ?? item.slug">
                    <li v-if="item.children" class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ 'is-open': isChildOpen(item) }"
                            href="#"
                            :aria-expanded="isChildOpen(item) ? 'true' : 'false'"
                            @click.prevent="toggleSubmenu(item)"
                        >
                            <span class="nav-link-icon"><i :class="item.icon"></i></span>
                            <span class="nav-link-text">{{ item.title }}</span>
                            <i class="fas fa-chevron-down nav-chevron"></i>
                        </a>
                        <ul class="nav flex flex-col list-none m-0 p-0" :class="{ hidden: !isChildOpen(item) }" :id="item.id">
                            <li v-for="child in item.children" :key="child.slug" class="nav-item">
                                <Link class="nav-link" :class="{ active: currentSlug === child.slug }" :href="`/${child.slug}`">
                                    <span v-if="child.icon" class="nav-link-icon"><i :class="child.icon"></i></span>
                                    <span class="nav-link-text">{{ child.title }}</span>
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li v-else class="nav-item">
                        <Link class="nav-link" :class="{ active: currentSlug === item.slug }" :href="`/${item.slug}`">
                            <span class="nav-link-icon"><i :class="item.icon"></i></span>
                            <span class="nav-link-text">{{ item.title }}</span>
                        </Link>
                    </li>
                </template>
            </template>
        </ul>
    </nav>
    <main>
        <div class="topbar flex justify-end items-center gap-2 mb-3">
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
        <slot />
    </main>
</template>
