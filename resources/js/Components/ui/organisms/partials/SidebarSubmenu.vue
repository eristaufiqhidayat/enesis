<script setup>
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import SidebarNavList from './SidebarNavList.vue';
import { SIDEBAR_CONTEXT } from './sidebarContext.js';

const props = defineProps({
    item: { type: Object, required: true },
    flyoutOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'close']);

const { currentSlug } = inject(SIDEBAR_CONTEXT);

// Sama dengan versi Blade: desktop (>991px) memakai flyout ke kanan parent, mobile tetap accordion inline.
const FLYOUT_CLOSE_DELAY = 150;
const DESKTOP_QUERY = '(min-width: 992px)';
const desktopQuery = window.matchMedia?.(DESKTOP_QUERY);
const isDesktop = ref(desktopQuery ? desktopQuery.matches : true);

const itemRef = ref(null);
const toggleRef = ref(null);
const panelRef = ref(null);
const navListRef = ref(null);
const panelStyle = ref({});
const arrowStyle = ref({});
let closeTimer = null;
let removeListeners = () => {};

// Rekursif: descendant di kedalaman berapa pun yang aktif membuat parent-nya ditandai.
function hasActiveDescendant(items) {
    return items.some((child) => (child.children ? hasActiveDescendant(child.children) : child.slug === currentSlug.value));
}

const hasActive = computed(() => hasActiveDescendant(props.item.children));

// Accordion mobile: kalau user sudah menekan manual, pakai pilihannya; kalau belum, ikuti ada/tidaknya menu aktif.
const accordionManual = ref(null);
const accordionOpen = computed(() => accordionManual.value ?? hasActive.value);

// Posisi panel di-fixed-kan di dekat parent (bukan absolute) karena .sidebar-scroll memotong overflow-nya.
// Panah (elemen terpisah, lihat app.css) diposisikan bersamaan: nempel di tepi kiri panel, sejajar baris pertama panel.
// Harus elemen terpisah, bukan pseudo-element di panel, karena panel overflow-y:auto akan memotongnya.
function placeFlyout() {
    if (!toggleRef.value || !panelRef.value) return;
    const rect = toggleRef.value.getBoundingClientRect();
    const height = panelRef.value.offsetHeight;
    const top = Math.max(8, Math.min(rect.top, window.innerHeight - height - 8));
    panelStyle.value = {
        left: `${rect.right + 4}px`,
        top: `${top}px`,
    };
    arrowStyle.value = {
        left: `${rect.right - 2}px`,
        top: `${top + 14}px`,
    };
}

function addFlyoutListeners() {
    const onDocumentClick = (e) => {
        if (!itemRef.value.contains(e.target)) emit('close');
    };
    const onKeydown = (e) => {
        if (e.key === 'Escape') emit('close');
    };
    const onScroll = () => emit('close');
    const onSidebarTransitionEnd = (e) => {
        if (e.propertyName === 'width') placeFlyout();
    };

    const scrollContainer = itemRef.value.closest('.sidebar-scroll');
    const sidebar = itemRef.value.closest('.sidebar');

    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    scrollContainer?.addEventListener('scroll', onScroll);
    sidebar?.addEventListener('transitionend', onSidebarTransitionEnd);

    removeListeners = () => {
        document.removeEventListener('click', onDocumentClick);
        document.removeEventListener('keydown', onKeydown);
        scrollContainer?.removeEventListener('scroll', onScroll);
        sidebar?.removeEventListener('transitionend', onSidebarTransitionEnd);
        removeListeners = () => {};
    };
}

watch(
    () => props.flyoutOpen,
    async (open) => {
        if (!open) {
            removeListeners();
            panelStyle.value = {};
            arrowStyle.value = {};
            navListRef.value?.closeFlyouts();
            return;
        }
        addFlyoutListeners();
        // Tunggu DOM memasang class terbuka dulu, baru posisi diukur dari tinggi panel yang sudah tampil.
        await nextTick();
        placeFlyout();
    },
);

function onMouseEnter() {
    if (!isDesktop.value) return;
    clearTimeout(closeTimer);
    emit('open');
}

function onMouseLeave() {
    if (!isDesktop.value) return;
    // Delay singkat supaya pointer sempat pindah ke panel (yang ada di dalam <li> yang sama).
    closeTimer = setTimeout(() => emit('close'), FLYOUT_CLOSE_DELAY);
}

function onToggleClick(e) {
    e.preventDefault();

    if (!isDesktop.value) {
        accordionManual.value = !accordionOpen.value;
        return;
    }

    // Sidebar yang dipersempit tetap dipersempit saat submenu diklik; flyout tetap terbuka di strip ikon.
    // Klik membuka (tidak membalik), supaya klik setelah hover tidak menutup flyout yang sudah terbuka.
    clearTimeout(closeTimer);
    emit('open');
}

const onDesktopChange = (e) => {
    isDesktop.value = e.matches;
    // Lewat ke mobile saat flyout terbuka: tutup, supaya tidak muncul lagi di posisi lama saat lebar kembali.
    if (!e.matches) emit('close');
};

onMounted(() => {
    desktopQuery?.addEventListener('change', onDesktopChange);
});

onBeforeUnmount(() => {
    clearTimeout(closeTimer);
    removeListeners();
    desktopQuery?.removeEventListener('change', onDesktopChange);
});
</script>

<template>
    <li
        ref="itemRef"
        class="nav-item has-flyout"
        :class="{ 'section-group': item.group, 'is-flyout-open': flyoutOpen }"
        @mouseenter="onMouseEnter"
        @mouseleave="onMouseLeave"
    >
        <!-- Judul grup dan item submenu berbagi perilaku flyout; bedanya hanya tampilan toggle-nya. -->
        <div
            v-if="item.group"
            ref="toggleRef"
            class="section-label"
            :class="{ 'is-open': accordionOpen, 'has-active-child': hasActive }"
            data-flyout-toggle
            role="button"
            tabindex="0"
            aria-haspopup="true"
            :aria-expanded="(isDesktop ? flyoutOpen : accordionOpen) ? 'true' : 'false'"
            @click="onToggleClick"
            @keydown.space.prevent="$event.currentTarget.click()"
            @keydown.enter.prevent="$event.currentTarget.click()"
        >
            <span class="section-label-text">
                <Icon :name="item.icon" class="section-label-icon" />
                <span class="section-label-name">{{ item.title }}</span>
            </span>
            <Icon name="chevron-right" class="nav-chevron" />
        </div>
        <a
            v-else
            ref="toggleRef"
            class="nav-link"
            :class="{ 'is-open': accordionOpen, 'has-active-child': hasActive }"
            href="#"
            data-flyout-toggle
            aria-haspopup="true"
            :aria-expanded="(isDesktop ? flyoutOpen : accordionOpen) ? 'true' : 'false'"
            @click="onToggleClick"
            @keydown.space.prevent="$event.currentTarget.click()"
            @keydown.enter.prevent="$event.currentTarget.click()"
        >
            <span class="nav-link-text">{{ item.title }}</span>
            <Icon name="chevron-right" class="nav-chevron" />
        </a>
        <span class="nav-flyout-arrow" aria-hidden="true" :style="arrowStyle"></span>
        <ul
            ref="panelRef"
            :id="item.group ? undefined : item.id"
            data-flyout-panel
            class="nav flex flex-col list-none m-0 p-0 nav-flyout"
            :class="{ 'is-open': accordionOpen }"
            :style="panelStyle"
        >
            <li class="nav-flyout-header">
                <Icon v-if="item.icon" :name="item.icon" class="section-label-icon" />
                <span>{{ item.title }}</span>
            </li>
            <SidebarNavList ref="navListRef" :items="item.children" />
        </ul>
    </li>
</template>
