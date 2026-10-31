<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from '@/Components/ui/atoms/Toast.vue';

// Pesan hasil aksi dari redirect ->with('sukses'|'galat') (dibagikan HandleInertiaRequests), ditampilkan sebagai
// toast di bawah layar. Sukses hilang sendiri setelah beberapa detik; galat tetap sampai ditutup.
const DURASI_SUKSES = 5000;

const page = usePage();
const daftar = ref([]);
const timer = new Map();
let nomor = 0;

function tutup(id) {
    clearTimeout(timer.get(id));
    timer.delete(id);
    daftar.value = daftar.value.filter((toast) => toast.id !== id);
}

function tampilkan(variant, pesan) {
    if (!pesan) return;

    const id = ++nomor;
    daftar.value.push({ id, variant, pesan });
    if (variant === 'success') timer.set(id, setTimeout(() => tutup(id), DURASI_SUKSES));
}

// Objek flash baru tiap respons penuh dari server, jadi pesan yang sama dua kali berturut-turut tetap muncul lagi.
watch(() => page.props.flash, (flash) => {
    tampilkan('success', flash?.sukses);
    tampilkan('danger', flash?.galat);
}, { immediate: true });

onBeforeUnmount(() => timer.forEach((t) => clearTimeout(t)));
</script>

<template>
    <div class="toaster" aria-live="polite">
        <TransitionGroup name="toast">
            <Toast v-for="toast in daftar" :key="toast.id" :variant="toast.variant" @close="tutup(toast.id)">{{ toast.pesan }}</Toast>
        </TransitionGroup>
    </div>
</template>
