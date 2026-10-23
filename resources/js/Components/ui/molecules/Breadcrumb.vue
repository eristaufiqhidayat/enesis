<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    menu: { type: Array, default: null },
    currentSlug: { type: String, default: null },
});

const page = usePage();

const trail = computed(() => {
    const menu = props.menu ?? page.props.menu ?? [];
    const currentSlug = props.currentSlug ?? page.props.currentSlug ?? null;

    for (const section of menu) {
        for (const item of section.items ?? []) {
            if (item.children) {
                for (const child of item.children) {
                    if ((child.slug ?? null) === currentSlug) {
                        return [section.label, item.title, child.title].filter(Boolean);
                    }
                }
            } else if ((item.slug ?? null) === currentSlug) {
                return [section.label, item.title].filter(Boolean);
            }
        }
    }

    return [];
});
</script>

<template>
    <nav v-if="trail.length" class="breadcrumb">
        <template v-for="(label, index) in trail" :key="index">
            <span v-if="index > 0" class="breadcrumb-separator">/</span>
            <span class="breadcrumb-item">{{ label }}</span>
        </template>
    </nav>
</template>
