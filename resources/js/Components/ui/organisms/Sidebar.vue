<script setup>
import { computed, nextTick, onMounted, provide, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/atoms/Icon.vue';
import SidebarUserMenu from './SidebarUserMenu.vue';
import SidebarNavList from './partials/SidebarNavList.vue';
import { SIDEBAR_CONTEXT } from './partials/sidebarContext.js';

const page = usePage();
const menu = computed(() => page.props.menu ?? []);
const currentSlug = computed(() => page.props.currentSlug ?? '');

const navRef = ref(null);

// Sidebar di-mount ulang setiap navigasi Inertia, jadi scroll posisinya
// disimpan di sessionStorage supaya tidak kembali ke atas tiap pindah menu.
const SCROLL_KEY = 'sidebar-scroll-top';

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

provide(SIDEBAR_CONTEXT, { currentSlug });

onMounted(() => {
    document.body.classList.toggle('sidebar-collapsed', collapsedFull.value);

    const saved = sessionStorage.getItem(SCROLL_KEY);
    if (saved && navRef.value) navRef.value.scrollTop = parseInt(saved, 10) || 0;

    navRef.value?.addEventListener('scroll', () => {
        sessionStorage.setItem(SCROLL_KEY, navRef.value.scrollTop);
    });
});

// Section ber-posisi 'footer' (config marketama.menu) tidak masuk daftar scroll, tapi ke popup menu user di footer.
const footerSection = computed(() => menu.value.find((section) => section.posisi === 'footer') ?? null);

// Judul grup dan item tanpa grup sama-sama masuk ke satu daftar; judul grup berperan sebagai flyout
// yang isinya item grup itu (lihat SidebarSubmenu). Grup tanpa label (Dashboard, dst.) tampil langsung.
const navItems = computed(() =>
    menu.value
        .filter((section) => section.posisi !== 'footer')
        .flatMap((section) =>
            section.label
                ? [{ id: section.label, title: section.label, icon: section.icon, group: true, children: section.items }]
                : section.items,
        ),
);

// Pencarian menu: data dari prop searchMenu (menu yang diratakan + entri per tab untuk halaman
// bertab, lihat Akses::menuPencarian), difilter substring case-insensitive, maksimal 8 hasil.
// Sama dengan versi Blade (ticket 01).
const SEARCH_LIMIT = 8;
const query = ref('');
const showResults = ref(false);

const searchMenus = computed(() => page.props.searchMenu ?? []);

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
            <SidebarNavList :items="navItems" top-level />
        </ul>
        </div>
        <div v-if="footerSection" class="sidebar-footer">
            <SidebarUserMenu :items="footerSection.items" />
        </div>
    </nav>
</template>
