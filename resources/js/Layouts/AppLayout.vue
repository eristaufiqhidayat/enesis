<script setup>
import { computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const menu = computed(() => page.props.menu ?? []);
const currentSlug = computed(() => page.props.currentSlug ?? '');

function isChildOpen(item) {
    if (!item.children) return false;
    return item.children.some((child) => child.slug === currentSlug.value);
}

onMounted(() => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new window.bootstrap.Tooltip(el));
});
</script>

<template>
    <nav class="sidebar">
        <div class="brand"><i class="fas fa-leaf me-1"></i> {{ $page.props.appName ?? 'Enesis Canvassing' }}</div>
        <ul class="nav flex-column">
            <template v-for="section in menu" :key="section.label ?? 'root'">
                <li v-if="section.label" class="section-label">{{ section.label }}</li>
                <template v-for="item in section.items" :key="item.id ?? item.slug">
                    <li v-if="item.children" class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ collapsed: !isChildOpen(item) }"
                            href="#"
                            :data-bs-toggle="'collapse'"
                            :data-bs-target="'#' + item.id"
                            :aria-expanded="isChildOpen(item) ? 'true' : 'false'"
                            @click.prevent
                        >
                            <span class="nav-link-icon"><i :class="item.icon"></i></span>{{ item.title }}
                        </a>
                        <ul class="nav flex-column collapse" :class="{ show: isChildOpen(item) }" :id="item.id">
                            <li v-for="child in item.children" :key="child.slug" class="nav-item">
                                <Link class="nav-link" :class="{ active: currentSlug === child.slug }" :href="`/${child.slug}`">
                                    <span v-if="child.icon" class="nav-link-icon"><i :class="child.icon"></i></span>{{ child.title }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li v-else class="nav-item">
                        <Link class="nav-link" :class="{ active: currentSlug === item.slug }" :href="`/${item.slug}`">
                            <span class="nav-link-icon"><i :class="item.icon"></i></span>{{ item.title }}
                        </Link>
                    </li>
                </template>
            </template>
        </ul>
    </nav>
    <main>
        <div class="topbar d-flex justify-content-end align-items-center gap-2 mb-3">
            <a class="shortcut-btn" href="/loading_barang/add" title="Tambah Loading Barang" data-bs-toggle="tooltip"><i class="fas fa-truck-loading"></i></a>
            <a class="shortcut-btn" href="/barang_canvassing/add" title="Tambah Barang Canvassing" data-bs-toggle="tooltip"><i class="fas fa-boxes"></i></a>
            <a class="shortcut-btn" href="/master_area/design_area" title="Design Polygon Area" data-bs-toggle="tooltip"><i class="fas fa-draw-polygon"></i></a>
            <div class="dropdown">
                <a class="notif-bell" href="#" data-bs-toggle="dropdown" title="Notifikasi Transaksi" aria-label="Notifikasi Transaksi"><i class="fas fa-bell"></i></a>
                <div class="dropdown-menu dropdown-menu-end p-3 text-muted small" style="min-width: 240px;">
                    <i class="fas fa-bell-slash me-1"></i> Tidak ada notifikasi baru
                </div>
            </div>
        </div>
        <slot />
    </main>
</template>
