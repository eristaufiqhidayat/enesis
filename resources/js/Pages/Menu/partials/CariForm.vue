<script setup>
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';

// Input cari + tombol Cari, dipakai di header tabel (atau header skeleton saat tabel masih dimuat).
const search = defineModel({ type: String, default: '' });
defineProps({
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['submit']);
</script>

<template>
    <form class="flex items-center gap-2" @submit.prevent="emit('submit')">
        <Input v-model="search" type="search" placeholder="Cari..." class="w-48 min-w-0" />
        <Button type="submit" :loading="loading" :disabled="disabled">Cari</Button>
    </form>
</template>
