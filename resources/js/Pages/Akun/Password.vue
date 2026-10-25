<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import Card from '@/Components/ui/molecules/Card.vue';

// Halaman ini hanya hasil langsung dari POST (tidak disimpan di session), jadi password tampil sekali.
const props = defineProps({
    menu: { type: String, required: true },
    judul: { type: String, required: true },
    akun: { type: Object, required: true },
    password: { type: String, required: true },
});

const appName = usePage().props.appName;
const sudahDicatat = ref(false);
const label = ref('Salin');

async function salin() {
    try {
        await navigator.clipboard.writeText(props.password);
        label.value = 'Tersalin';
    } catch {
        label.value = 'Salin manual';
    }
}

// Riwayat browser dikosongkan sebelum pindah halaman, supaya password tidak bisa dibuka lagi lewat tombol Back.
function tutup() {
    router.clearHistory();
}
</script>

<template>
    <Head :title="`Password awal ${akun.username} · ${appName}`" />
    <AppLayout :breadcrumb-slug="menu">
        <div class="max-w-md mx-auto mt-4">
            <Card>
                <template #header>
                    <h6 class="mb-0 font-semibold">Password awal untuk {{ akun.username }}</h6>
                </template>
                <p>Berikan ke pengguna secara langsung. Password ini <strong>hanya tampil sekali</strong>; saat login pertama (web atau HP) pengguna wajib menggantinya.</p>
                <div class="flex items-center gap-2 rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 mb-3">
                    <code class="grow text-xl font-bold tracking-widest">{{ password }}</code>
                    <Button variant="outline-secondary" @click="salin">{{ label }}</Button>
                </div>
                <label class="form-check mb-3">
                    <Checkbox v-model="sudahDicatat" />
                    Sudah saya catat / berikan ke pengguna
                </label>
                <template #footer>
                    <div class="flex justify-end">
                        <Button v-if="sudahDicatat" :tag="Link" :href="`/${menu}/${akun.id}`" @click="tutup">Tutup</Button>
                        <Button v-else variant="outline-secondary" class="opacity-50 pointer-events-none" aria-disabled="true">Tutup</Button>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>
