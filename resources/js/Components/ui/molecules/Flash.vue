<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Alert from '@/Components/ui/molecules/Alert.vue';

// Pesan hasil aksi dari redirect ->with('sukses'|'galat') (dibagikan HandleInertiaRequests).
const flash = computed(() => usePage().props.flash ?? {});
</script>

<template>
    <Alert v-if="flash.sukses" variant="success" class="mb-3" role="status">{{ flash.sukses }}</Alert>
    <Alert v-if="flash.galat" variant="danger" class="mb-3" role="alert">{{ flash.galat }}</Alert>
</template>
