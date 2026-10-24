<script setup>
import { inject, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/ui/atoms/Icon.vue';
import SidebarSubmenu from './SidebarSubmenu.vue';
import { SIDEBAR_CONTEXT } from './sidebarContext.js';

const props = defineProps({
    items: { type: Array, required: true },
    // Ikon item daun hanya untuk daftar top-level (grup tanpa label, lihat Sidebar.vue): daftar
    // yang dirender di dalam panel flyout grup/submenu (SidebarSubmenu) tidak menyalakan ini.
    topLevel: { type: Boolean, default: false },
});

const { currentSlug } = inject(SIDEBAR_CONTEXT);

// Satu level menu hanya boleh punya satu flyout terbuka: membuka sibling otomatis menutup yang lain.
const openFlyoutId = ref(null);

function openFlyout(item) {
    openFlyoutId.value = item.id;
}

function closeFlyout(item) {
    if (openFlyoutId.value === item.id) openFlyoutId.value = null;
}

// Dipanggil induk saat flyout-nya ditutup, supaya flyout bersarang di dalamnya ikut tertutup.
function closeFlyouts() {
    openFlyoutId.value = null;
}

// Tooltip nama menu saat sidebar dipersempit (nama tidak terlihat di strip ikon). Dirender fixed di body,
// karena .sidebar memotong overflow dan bubble-nya akan terpotong kalau ditempel di dalam link.
const tip = ref(null);

function showTip(e, title) {
    if (!props.topLevel || !window.matchMedia('(min-width: 992px)').matches) return;
    if (!document.body.classList.contains('sidebar-collapsed')) return;
    const rect = e.currentTarget.getBoundingClientRect();
    tip.value = { title, left: rect.right + 6, top: rect.top + rect.height / 2 };
}

function hideTip() {
    tip.value = null;
}

defineExpose({ closeFlyouts });
</script>

<template>
    <template v-for="item in props.items" :key="item.id ?? item.slug">
        <SidebarSubmenu
            v-if="item.children"
            :item="item"
            :flyout-open="openFlyoutId === item.id"
            @open="openFlyout(item)"
            @close="closeFlyout(item)"
        />
        <li v-else class="nav-item">
            <Link class="nav-link" :class="{ active: currentSlug === item.slug }" :href="`/${item.slug}`" @mouseenter="showTip($event, item.title)" @mouseleave="hideTip">
                <span v-if="props.topLevel && item.icon" class="nav-link-icon">
                    <Icon :name="item.icon" class="nav-link-icon-svg" />
                </span>
                <span class="nav-link-text">{{ item.title }}</span>
            </Link>
        </li>
    </template>
    <Teleport to="body">
        <div v-if="tip" class="tooltip-wrapper tooltip-right tooltip-floating" :style="{ left: `${tip.left}px`, top: `${tip.top}px` }" :data-tooltip="tip.title"></div>
    </Teleport>
</template>
