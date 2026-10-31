<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from '@/Components/ui/atoms/Toast.vue';
import { useToast } from '@/Composables/useToast.js';

// Pesan hasil aksi dari redirect ->with('sukses'|'galat') (dibagikan HandleInertiaRequests), ditampilkan sebagai
// toast di bawah layar bersama toast dari komponen lain (lihat useToast).
const page = usePage();
const { daftar, tutup, sukses, galat } = useToast();

// Objek flash baru tiap respons penuh dari server, jadi pesan yang sama dua kali berturut-turut tetap muncul lagi.
watch(() => page.props.flash, (flash) => {
    sukses(flash?.sukses);
    galat(flash?.galat);
}, { immediate: true });
</script>

<template>
    <div class="toaster" aria-live="polite">
        <TransitionGroup name="toast">
            <Toast v-for="toast in daftar" :key="toast.id" :variant="toast.variant" @close="tutup(toast.id)">{{ toast.pesan }}</Toast>
        </TransitionGroup>
    </div>
</template>
