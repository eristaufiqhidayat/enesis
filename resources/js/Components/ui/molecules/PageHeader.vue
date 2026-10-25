<script setup>
// Judul halaman + keterangan + tombol aksi di kanan (padanan molecule Blade page-header).
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
        <div>
            <h5 class="mb-0 font-semibold">{{ title }}</h5>
            <p v-if="subtitle" class="mb-0 text-xs text-gray-500">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
