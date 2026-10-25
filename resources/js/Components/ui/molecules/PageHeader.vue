<script setup>
// Judul halaman + ikon + keterangan + tombol aksi di kanan (padanan molecule Blade page-header).
import { computed, inject } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/atoms/Icon.vue';
import { cariIkonMenu } from '@/Support/menu.js';
import { PAGE_HEADER_SLUG } from '@/Layouts/pageHeaderContext.js';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    // Opsional: default dicari otomatis dari config menu (ikon menu sidebar untuk slug halaman ini,
    // lewat PAGE_HEADER_SLUG yang disediakan AppLayout — lihat STYLE_GUIDE.md § PageHeader).
    icon: { type: String, default: null },
});

const page = usePage();
const slug = inject(PAGE_HEADER_SLUG, computed(() => page.props.currentSlug ?? null));
const resolvedIcon = computed(() => props.icon ?? cariIkonMenu(page.props.menu, slug.value));
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
        <div class="flex items-center gap-2">
            <span v-if="resolvedIcon" class="page-header-icon"><Icon :name="resolvedIcon" /></span>
            <div>
                <h5 class="mb-0 font-semibold">{{ title }}</h5>
                <p v-if="subtitle" class="mb-0 text-xs text-gray-500">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
