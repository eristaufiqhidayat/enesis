<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import FormKaryawan from './partials/FormKaryawan.vue';
import PindahArea from './partials/PindahArea.vue';

// Halaman tambah/edit karyawan (dibuka lewat URL). Dari daftar, edit memakai modal (Index.vue).
// Di halaman ini juga ada pindah area (sales), hapus, dan riwayat perubahan.
const props = defineProps({
    slug: { type: String, required: true },
    kategori: { type: String, required: true },
    judul: { type: String, required: true },
    nama: { type: String, required: true },
    item: { type: Object, default: null },
    nilai: { type: Object, required: true },
    opsi: { type: Object, required: true },
    dipakai: { type: Array, default: () => [] },
    salesSeDepo: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    bisa: { type: Object, required: true },
});

const page = usePage();
const appName = page.props.appName;
const base = `/${props.slug}`;

const judulHalaman = computed(() => (props.item ? `${props.bisa.ubah ? 'Edit' : 'Detail'} ${props.nama}: ${props.item.nama}` : `Tambah ${props.nama}`));
const subtitle = computed(() => {
    if (!props.item) return `${props.judul} baru bisa langsung dipilih saat membuat akun login.`;
    const akun = props.item.akun.length ? `Akun login: ${props.item.akun.join(', ')}.` : 'Belum punya akun login.';

    return `ID ${props.item.id} · ${akun}${props.item.diubah ? ` Terakhir diubah ${props.item.diubah}.` : ''}`;
});

const errorHapus = computed(() => page.props.errors?.hapus);
const modalHapus = ref(false);
function hapus() {
    router.delete(`${base}/${props.item.id}`, { onFinish: () => (modalHapus.value = false) });
}
</script>

<template>
    <Head :title="`${item ? item.nama : 'Tambah ' + nama} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judulHalaman" :subtitle="subtitle">
            <template #actions>
                <Button :tag="Link" variant="light" :href="base">Kembali ke daftar</Button>
            </template>
        </PageHeader>

        <Alert v-if="errorHapus" variant="danger" class="mb-3" role="alert">{{ errorHapus }}</Alert>

        <FormKaryawan :slug="slug" :kategori="kategori" :nama="nama" :item="item" :nilai="nilai" :opsi="opsi" :bisa="bisa" />

        <div v-if="item" class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
            <PindahArea v-if="kategori === 'sales' && bisa.ubah" :slug="slug" :item="item" :sales="salesSeDepo" :jumlah-area="nilai.area_ids.length" />

            <Card v-if="riwayat.length">
                <template #header>Riwayat perubahan</template>
                <ul class="flex flex-col gap-1 mb-0">
                    <li v-for="(r, i) in riwayat" :key="i">
                        <Badge variant="light">{{ r.aksi }}</Badge>
                        {{ r.keterangan }}
                        <span class="text-gray-500">· {{ r.oleh ?? '-' }} · {{ r.pada }}</span>
                    </li>
                </ul>
            </Card>
        </div>

        <div v-if="item && bisa.hapus" class="mb-3">
            <Button variant="outline-danger" @click="modalHapus = true">Hapus {{ nama }}</Button>
        </div>

        <Modal :show="modalHapus" :title="`Hapus ${nama}?`" @close="modalHapus = false">
            <p v-if="dipakai.length">{{ item.nama }} masih dipakai {{ dipakai.join(', ') }}, jadi tidak bisa dihapus. Ubah status menjadi Tidak aktif saja.</p>
            <p v-else>{{ item?.nama }} akan dihapus permanen.</p>
            <template #footer>
                <Button variant="light" @click="modalHapus = false">Batal</Button>
                <Button v-if="!dipakai.length" variant="outline-danger" @click="hapus">Ya, hapus</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
