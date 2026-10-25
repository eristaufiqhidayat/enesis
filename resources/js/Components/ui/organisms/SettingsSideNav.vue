<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Card from '@/Components/ui/molecules/Card.vue';
import { cariSubGrup } from './partials/subGrup.js';

// Panel navigasi anak untuk halaman daftar di bawah sub-grup Manajemen Produk / Konfigurasi / Master Data.
// Dirender AppLayout; tidak tampil bila slug aktif bukan anak sub-grup itu.
const page = usePage();
const currentSlug = computed(() => page.props.currentSlug ?? null);
const subGrup = computed(() => cariSubGrup(page.props.menu, currentSlug.value));
</script>

<template>
    <nav v-if="subGrup" class="settings-side-nav">
        <Card body-class="p-1">
            <template #header>
                <h6 class="settings-side-nav-title mb-0 font-semibold">{{ subGrup.title }}</h6>
            </template>
            <ul>
                <li v-for="anak in subGrup.children" :key="anak.slug">
                    <Link :href="`/${anak.slug}`" class="settings-side-nav-link" :class="{ 'is-active': anak.slug === currentSlug }">
                        {{ anak.title }}
                    </Link>
                </li>
            </ul>
        </Card>
    </nav>
</template>
