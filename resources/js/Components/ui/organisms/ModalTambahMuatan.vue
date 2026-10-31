<script setup>
import { onMounted, ref, watch } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import FormTambahMuatanCanvassing from '@/Components/ui/organisms/partials/FormTambahMuatanCanvassing.vue';
import { useTambahMuatan } from '@/Composables/useTambahMuatan.js';

// Modal "Atur Muatan Motoris" (wizard tambah muatan canvassing), dipasang sekali di AppLayout. Dibuka lewat
// useTambahMuatan().buka() dari tombol Tambah di halaman atau shortcut Topbar, atau dari ?tambah=1 (tautan /add);
// parameternya dibuang dari URL saat modal ditutup supaya muat ulang halaman tidak membuka modal lagi.
const { terbuka, buka, tutup } = useTambahMuatan();

const sibuk = ref(false);
const langkah = ref(1);
const form = ref(null);

onMounted(() => {
    if (new URLSearchParams(window.location.search).has('tambah')) buka();
});

watch(terbuka, (nilai) => {
    const url = new URL(window.location.href);

    if (nilai || !url.searchParams.has('tambah')) return;

    url.searchParams.delete('tambah');
    window.history.replaceState(window.history.state, '', url);
});
</script>

<template>
    <Modal :show="terbuka" title="Atur Muatan Motoris" size="xl" tinggi @close="tutup">
        <FormTambahMuatanCanvassing v-if="terbuka" ref="form" @tersimpan="tutup" @sibuk="sibuk = $event" @langkah="langkah = $event" />
        <template #footer>
            <template v-if="langkah === 1">
                <Button variant="light" @click="tutup">Batal</Button>
                <Button :disabled="sibuk" @click="form?.lanjut()">Lanjut</Button>
            </template>
            <template v-else-if="langkah === 2">
                <Button variant="light" :disabled="sibuk" @click="form?.kembali()">Kembali</Button>
                <Button :disabled="sibuk" @click="form?.lanjut()">Lanjut</Button>
            </template>
            <template v-else>
                <Button variant="light" :disabled="sibuk" @click="form?.kembali()">Kembali</Button>
                <Button type="submit" form="form-tambah-muatan" :loading="sibuk" :disabled="sibuk">Simpan muatan</Button>
            </template>
        </template>
    </Modal>
</template>
