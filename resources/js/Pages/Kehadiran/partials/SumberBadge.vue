<script setup>
import Badge from '@/Components/ui/atoms/Badge.vue';

// Asal data: aplikasi motoris, atau riwayat aplikasi lama hasil migrasi.
defineProps({ sumber: { type: String, required: true } });
</script>

<template>
    <Badge :variant="sumber === 'motoris' ? 'primary' : 'secondary'">{{ sumber === 'motoris' ? 'Motoris' : 'Aplikasi lama' }}</Badge>
</template>
