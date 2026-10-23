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

// State collapse per grup disimpan di localStorage dengan key yang sama
// dengan versi Blade (ticket 01) supaya kompatibel antar stack rendering.
const COLLAPSED_GROUPS_KEY = 'sidebar-collapsed-groups';
const collapsedGroups = ref([]);

function readCollapsedGroups() {
    try {
        const saved = JSON.parse(localStorage.getItem(COLLAPSED_GROUPS_KEY));
        return Array.isArray(saved) ? saved : [];
    } catch {
        return [];
    }
}

function writeCollapsedGroups(labels) {
    try {
        localStorage.setItem(COLLAPSED_GROUPS_KEY, JSON.stringify(labels));
    } catch {
        // localStorage tidak tersedia (mode privat dll.), state collapse tidak persist.
    }
}

// Collapse seluruh sidebar jadi strip ikon-saja. Key localStorage dan class di
// <body> sama dengan versi Blade (ticket 03), jadi state-nya ikut ke stack lain.
const COLLAPSED_FULL_KEY = 'sidebar-collapsed-full';

function readCollapsedFull() {
    try {
        return localStorage.getItem(COLLAPSED_FULL_KEY) === '1';
    } catch {
        return false;
    }
}

const collapsedFull = ref(readCollapsedFull());

function setCollapsedFull(value) {
    collapsedFull.value = value;
    document.body.classList.toggle('sidebar-collapsed', value);
    try {
        if (value) localStorage.setItem(COLLAPSED_FULL_KEY, '1');
        else localStorage.removeItem(COLLAPSED_FULL_KEY);
    } catch {
        // localStorage tidak tersedia (mode privat dll.), state collapse tidak persist.
    }
}

onMounted(() => {
    document.body.classList.toggle('sidebar-collapsed', collapsedFull.value);

    const saved = sessionStorage.getItem(SCROLL_KEY);
    if (saved && navRef.value) navRef.value.scrollTop = parseInt(saved, 10) || 0;

    navRef.value?.addEventListener('scroll', () => {
        sessionStorage.setItem(SCROLL_KEY, navRef.value.scrollTop);
    });

    collapsedGroups.value = readCollapsedGroups();
});

function groupContainsCurrentSlug(section) {
    return section.items.some((item) => {
        if (item.children) return item.children.some((child) => child.slug === currentSlug.value);
        return item.slug === currentSlug.value;
    });
}

function isGroupOpen(section) {
    if (!section.label) return true;
    if (groupContainsCurrentSlug(section)) return true;
    return !collapsedGroups.value.includes(section.label);
}

function toggleGroup(section, event) {
    const isCollapsed = collapsedGroups.value.includes(section.label);
    const labels = collapsedGroups.value.filter((label) => label !== section.label);
    if (!isCollapsed) labels.push(section.label);

    collapsedGroups.value = labels;
    writeCollapsedGroups(labels);

    event?.currentTarget?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function isChildOpen(item) {
    if (!item.children) return false;
    if (item.id in manuallyToggled.value) return manuallyToggled.value[item.id];
    return item.children.some((child) => child.slug === currentSlug.value);
}

function toggleSubmenu(item) {
    if (collapsedFull.value) setCollapsedFull(false);
    manuallyToggled.value = { ...manuallyToggled.value, [item.id]: !isChildOpen(item) };
}
</script>

<template>
    <nav class="sidebar" ref="navRef">
        <div class="brand">
            <span><i class="fas fa-leaf me-1"></i> {{ page.props.appName ?? 'Enesis Canvassing' }}</span>
            <button type="button" data-sidebar-toggle aria-label="Persempit/lebarkan sidebar" @click="setCollapsedFull(!collapsedFull)"><i class="fas fa-bars"></i></button>
        </div>
        <ul class="nav flex flex-col list-none m-0 p-0">
            <template v-for="section in menu" :key="section.label ?? 'root'">
                <li
                    v-if="section.label"
                    class="section-label"
                    :class="{ 'is-open': isGroupOpen(section) }"
                    role="button"
                    tabindex="0"
                    :aria-expanded="isGroupOpen(section) ? 'true' : 'false'"
                    @click.prevent="toggleGroup(section, $event)"
                    @keydown.enter.prevent="toggleGroup(section, $event)"
                    @keydown.space.prevent="toggleGroup(section, $event)"
                >
                    {{ section.label }}
                    <i class="fas fa-chevron-down nav-chevron"></i>
                </li>
                <ul class="flex flex-col list-none m-0 p-0" :class="{ hidden: !isGroupOpen(section) }">
                    <template v-for="item in section.items" :key="item.id ?? item.slug">
                        <li v-if="item.children" class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ 'is-open': isChildOpen(item) }"
                                href="#"
                                :aria-expanded="isChildOpen(item) ? 'true' : 'false'"
                                :title="item.title"
                                @click.prevent="toggleSubmenu(item)"
                            >
                                <span class="nav-link-icon"><i :class="item.icon"></i></span>
                                <span class="nav-link-text">{{ item.title }}</span>
                                <i class="fas fa-chevron-down nav-chevron"></i>
                            </a>
                            <ul class="nav flex flex-col list-none m-0 p-0" :class="{ hidden: !isChildOpen(item) }" :id="item.id">
                                <li v-for="child in item.children" :key="child.slug" class="nav-item">
                                    <Link class="nav-link" :class="{ active: currentSlug === child.slug }" :href="`/${child.slug}`" :title="child.title">
                                        <span v-if="child.icon" class="nav-link-icon"><i :class="child.icon"></i></span>
                                        <span class="nav-link-text">{{ child.title }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </li>
                        <li v-else class="nav-item">
                            <Link class="nav-link" :class="{ active: currentSlug === item.slug }" :href="`/${item.slug}`" :title="item.title">
                                <span class="nav-link-icon"><i :class="item.icon"></i></span>
                                <span class="nav-link-text">{{ item.title }}</span>
                            </Link>
                        </li>
                    </template>
                </ul>
            </template>
        </ul>
    </nav>
</template>
