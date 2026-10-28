<script setup>
// Indikator memuat berbentuk lingkaran berputar (padanan class .spinner di app.css). size: 'sm' (bawaan, kecil
// untuk di samping teks) atau 'md' (untuk area yang sedang menunggu data).
defineProps({
    size: { type: String, default: 'sm' },
});
</script>

<template>
    <span class="spinner" :class="`spinner-${size}`" role="status" aria-label="Memuat"></span>
</template>
