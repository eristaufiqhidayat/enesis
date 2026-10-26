<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';

defineProps({
    riwayat: { type: Array, required: true },
    bolehUnduh: { type: Boolean, default: false },
    bolehHapus: { type: Boolean, default: false },
});

// Padanan status: [label, variant badge].
const STATUS = {
    menunggu: ['Menunggu', 'light'],
    proses: ['Diproses', 'primary'],
    selesai: ['Siap diunduh', 'success'],
    gagal: ['Gagal', 'danger'],
    kedaluwarsa: ['Kedaluwarsa', 'secondary'],
};

const ukuran = (b) => {
    if (b === null) return '-';
    const satuan = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let n = b;
    while (n >= 1024 && i < satuan.length - 1) { n /= 1024; i++; }
    return `${n.toLocaleString('id-ID', { maximumFractionDigits: i ? 1 : 0 })} ${satuan[i]}`;
};

const akanDihapus = ref(null);
const menghapus = ref(false);

function hapus() {
    router.delete(`/backup_data/${akanDihapus.value.id}`, {
        preserveScroll: true,
        onStart: () => { menghapus.value = true; },
        onFinish: () => { menghapus.value = false; akanDihapus.value = null; },
    });
}
</script>

<template>
    <Card body-class="overflow-x-auto">
        <template #header><span class="font-semibold">Riwayat backup</span></template>
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Dibuat</th>
                    <th scope="col">Format</th>
                    <th scope="col">Isi</th>
                    <th scope="col">Oleh</th>
                    <th scope="col" class="text-end">Ukuran</th>
                    <th scope="col">Berlaku sampai</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in riwayat" :key="r.id">
                    <td class="whitespace-nowrap">{{ r.dibuat }}</td>
                    <td><Badge variant="primary">{{ r.format }}</Badge></td>
                    <td>
                        {{ r.jumlahTabel }} tabel
                        <span v-if="r.status === 'selesai' || r.status === 'kedaluwarsa'" class="text-gray-500">· {{ r.baris.toLocaleString('id-ID') }} baris</span>
                        <span v-if="r.rentang" class="block text-xs text-gray-500">Transaksi {{ r.rentang }}</span>
                    </td>
                    <td>{{ r.oleh }}</td>
                    <td class="text-end whitespace-nowrap">{{ ukuran(r.ukuran) }}</td>
                    <td class="whitespace-nowrap">{{ r.sampai ?? '-' }}</td>
                    <td>
                        <Badge :variant="STATUS[r.status][1]">{{ STATUS[r.status][0] }}</Badge>
                        <span v-if="r.status === 'proses'" class="block text-xs text-gray-500">{{ r.tabelSelesai }} / {{ r.jumlahTabel }} · {{ r.tabelSekarang }}</span>
                        <span v-if="r.status === 'gagal' && r.pesan" class="block max-w-xs text-xs text-red-700">{{ r.pesan }}</span>
                    </td>
                    <td class="text-end whitespace-nowrap">
                        <div class="inline-flex gap-1">
                            <Button v-if="bolehUnduh && r.status === 'selesai'" tag="a" variant="outline-primary" :href="`/backup_data/${r.id}/unduh`" :aria-label="`Unduh backup ${r.dibuat}`">Unduh</Button>
                            <Button v-if="bolehHapus && r.status !== 'menunggu' && r.status !== 'proses'" variant="outline-danger" :aria-label="`Hapus backup ${r.dibuat}`" @click="akanDihapus = r">Hapus</Button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!riwayat.length">
                    <td colspan="8" class="text-center text-gray-500">Belum ada backup.</td>
                </tr>
            </tbody>
        </table>
    </Card>

    <Modal :show="akanDihapus !== null" title="Hapus backup?" @close="akanDihapus = null">
        <p v-if="akanDihapus" class="text-sm">Backup {{ akanDihapus.format }} tanggal {{ akanDihapus.dibuat }} dan filenya dihapus permanen.</p>
        <template #footer>
            <Button variant="light" @click="akanDihapus = null">Batal</Button>
            <Button variant="outline-danger" :loading="menghapus" @click="hapus">Hapus</Button>
        </template>
    </Modal>
</template>
