<script setup>
// Tab penghubung halaman terpisah (mis. Last Seen ↔ Riwayat Lokasi). Daftar tab datang dari shared prop `tabHalaman`
// (sudah disaring menurut hak akun); memilih tab berpindah ke halamannya. Tidak tampil bila tidak ada pasangan.
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Tabs from './Tabs.vue';

const page = usePage();
const tabs = computed(() => (page.props.tabHalaman ?? []).map((t) => ({ key: t.slug, label: t.label })));

function pindah(slug) {
    if (slug !== page.props.currentSlug) router.visit(`/${slug}`);
}
</script>

<template>
    <Tabs v-if="tabs.length" :model-value="page.props.currentSlug" :tabs="tabs" @update:model-value="pindah" />
</template>
