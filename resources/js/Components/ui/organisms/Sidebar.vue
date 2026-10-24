<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/atoms/Icon.vue';

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

// Tombol "semua grup": ada grup tertutup → buka semua, semua terbuka → tutup semua.
// Dihitung dari grup yang tampil di menu saat ini (bukan seluruh config), sama seperti versi Blade.
const labeledSections = computed(() => menu.value.filter((section) => section.label));
const anyGroupCollapsed = computed(() => labeledSections.value.some((section) => !isGroupOpen(section)));
const toggleAllLabel = computed(() => (anyGroupCollapsed.value ? 'Buka semua grup' : 'Tutup semua grup'));

function toggleAllGroups() {
    // Grup yang memuat menu aktif tidak ikut ditutup, supaya tetap terlihat.
    const labels = anyGroupCollapsed.value
        ? []
        : labeledSections.value.filter((section) => !groupContainsCurrentSlug(section)).map((section) => section.label);
    collapsedGroups.value = labels;
    writeCollapsedGroups(labels);
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

// Pencarian menu: judul menu (termasuk children) diratakan jadi satu array,
// lalu difilter substring case-insensitive, maksimal 8 hasil. Sama dengan versi Blade (ticket 01).
const SEARCH_LIMIT = 8;
const query = ref('');
const showResults = ref(false);

const searchMenus = computed(() =>
    menu.value
        .flatMap((section) => section.items.flatMap((item) => (item.children ? item.children : [item])))
        .filter((item) => item.slug)
        .map((item) => ({ title: item.title, slug: item.slug })),
);

const searchResults = computed(() => {
    const keyword = query.value.trim().toLowerCase();
    if (!keyword) return [];
    return searchMenus.value.filter((item) => item.title.toLowerCase().includes(keyword)).slice(0, SEARCH_LIMIT);
});

const resultsOpen = computed(() => showResults.value && query.value.trim() !== '');
const resultsRef = ref(null);
const activeIndex = ref(0);

// Hasil baru → sorot item pertama, supaya Enter langsung membuka hasil teratas.
watch(searchResults, () => {
    activeIndex.value = 0;
});

function moveActive(step) {
    if (!resultsOpen.value) {
        showResults.value = true;
        return;
    }
    const total = searchResults.value.length;
    if (total === 0) return;
    activeIndex.value = (activeIndex.value + step + total) % total;
    nextTick(() => resultsRef.value?.children[activeIndex.value]?.scrollIntoView({ block: 'nearest' }));
}

function openActiveResult() {
    const item = searchResults.value[activeIndex.value];
    if (!resultsOpen.value || !item) return;
    router.visit(`/${item.slug}`);
}
</script>


<template>
    <nav class="sidebar">
        <div class="brand">
            <div class="brand-actions">
                <button
                    type="button"
                    data-toggle-all-groups
                    :aria-label="toggleAllLabel"
                    :title="toggleAllLabel"
                    @click="toggleAllGroups"
                >
                    <Icon :name="anyGroupCollapsed ? 'chevrons-down' : 'chevrons-up'" />
                </button>
                <button type="button" data-sidebar-toggle aria-label="Persempit/lebarkan sidebar" @click="setCollapsedFull(!collapsedFull)"><Icon name="menu" /></button>
            </div>
            <div class="sidebar-search">
                <input
                    v-model="query"
                    type="search"
                    class="form-control form-control-sm"
                    data-sidebar-search
                    placeholder="Cari menu..."
                    autocomplete="off"
                    @focus="showResults = true"
                    @input="showResults = true"
                    @blur="showResults = false"
                    @keydown.esc.prevent="showResults = false"
                    @keydown.down.prevent="moveActive(1)"
                    @keydown.up.prevent="moveActive(-1)"
                    @keydown.enter.prevent="openActiveResult"
                />
                <!-- mousedown.prevent menjaga fokus tetap di input, jadi klik hasil tidak terhitung blur dan dropdown tidak tertutup duluan. -->
                <ul ref="resultsRef" class="sidebar-search-results" :class="{ hidden: !resultsOpen }" @mousedown.prevent>
                    <li v-if="query.trim() && searchResults.length === 0" class="sidebar-search-empty">Tidak ditemukan</li>
                    <li v-for="(item, index) in searchResults" :key="item.slug" :class="{ 'is-active': index === activeIndex }">
                        <Link :href="`/${item.slug}`">{{ item.title }}</Link>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar-scroll" ref="navRef">
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
                    <span class="section-label-text">
                        <Icon :name="section.icon" class="section-label-icon" />
                        {{ section.label }}
                    </span>
                    <Icon name="chevron-down" class="nav-chevron" />
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
                                <span class="nav-link-text">{{ item.title }}</span>
                                <Icon name="chevron-down" class="nav-chevron" />
                            </a>
                            <ul class="nav flex flex-col list-none m-0 p-0" :class="{ hidden: !isChildOpen(item) }" :id="item.id">
                                <li v-for="child in item.children" :key="child.slug" class="nav-item">
                                    <Link class="nav-link" :class="{ active: currentSlug === child.slug }" :href="`/${child.slug}`" :title="child.title">
                                        <span class="nav-link-text">{{ child.title }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </li>
                        <li v-else class="nav-item">
                            <Link class="nav-link" :class="{ active: currentSlug === item.slug }" :href="`/${item.slug}`" :title="item.title">
                                <span class="nav-link-text">{{ item.title }}</span>
                            </Link>
                        </li>
                    </template>
                </ul>
            </template>
        </ul>
        </div>
    </nav>
</template>
