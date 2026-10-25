<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, default: () => [] },
    // Prop Inertia yang diambil ulang saat pindah halaman (kosong = kunjungan penuh).
    only: { type: Array, default: () => [] },
});
</script>

<template>
    <nav>
        <ul class="pagination mb-0">
            <li v-for="(link, index) in links" :key="index" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                <Link v-if="link.url" class="page-link" :href="link.url" v-html="link.label" preserve-state :only="only" />
                <span v-else class="page-link" v-html="link.label"></span>
            </li>
        </ul>
    </nav>
</template>
