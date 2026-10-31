<script setup>
// Satu pesan toast (notifikasi singkat yang melayang di bawah layar, lihat molecule Flash). Isi lewat slot.
// variant: 'success' | 'danger'. Tombol × mengirim 'close'; pemanggil yang mengatur kapan toast hilang.
defineProps({
    variant: { type: String, default: 'success' },
});

defineEmits(['close']);
</script>

<template>
    <div class="toast" :class="`toast-${variant}`" :role="variant === 'danger' ? 'alert' : 'status'">
        <span class="toast-isi"><slot /></span>
        <button type="button" class="toast-tutup" aria-label="Tutup notifikasi" @click="$emit('close')">&times;</button>
    </div>
</template>
