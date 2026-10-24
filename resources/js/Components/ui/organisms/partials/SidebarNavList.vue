<script setup>
import { inject, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SidebarSubmenu from './SidebarSubmenu.vue';
import { SIDEBAR_CONTEXT } from './sidebarContext.js';

const props = defineProps({
    items: { type: Array, required: true },
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
            <Link class="nav-link" :class="{ active: currentSlug === item.slug }" :href="`/${item.slug}`">
                <span class="nav-link-text">{{ item.title }}</span>
            </Link>
        </li>
    </template>
</template>
