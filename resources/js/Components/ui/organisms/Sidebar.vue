<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const menu = computed(() => page.props.menu ?? []);
const currentSlug = computed(() => page.props.currentSlug ?? '');

const manuallyToggled = ref({});
const navRef = ref(null);

// Sidebar di-mount ulang setiap navigasi Inertia, jadi scroll posisinya
// disimpan di sessionStorage supaya tidak kembali ke atas tiap pindah menu.
const SCROLL_KEY = 'sidebar-scroll-top';

onMounted(() => {
    const saved = sessionStorage.getItem(SCROLL_KEY);
    if (saved && navRef.value) navRef.value.scrollTop = parseInt(saved, 10) || 0;

    navRef.value?.addEventListener('scroll', () => {
        sessionStorage.setItem(SCROLL_KEY, navRef.value.scrollTop);
    });
});

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
    <nav class="sidebar" ref="navRef">
        <div class="brand"><i class="fas fa-leaf me-1"></i> {{ page.props.appName ?? 'Enesis Canvassing' }}</div>
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
</template>
