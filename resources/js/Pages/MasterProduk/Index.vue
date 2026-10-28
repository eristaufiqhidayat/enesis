<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import FormProduk from './partials/FormProduk.vue';
import FormSkeleton from './partials/FormSkeleton.vue';
import { useAmbilData } from '@/Support/ambilData.js';
import { angka } from '@/Support/format.js';

// Daftar master produk (Produk, Produk Kompetitor, Kategori, Brand, Satuan) — satu halaman untuk 5 menu.
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    nama: { type: String, required: true },
    keterangan: { type: String, default: '' },
    kolom: { type: Object, required: true },
    baris: { type: Object, required: true },
    q: { type: String, default: '' },
    total: { type: Number, default: 0 },
    bisa: { type: Object, required: true },
});

const appName = usePage().props.appName;
const base = `/${props.slug}`;
const cari = ref(props.q);

const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ q: cari.value.trim() }),
    diterapkan: () => ({ q: props.q }),
});

function kirimCari() {
    const q = cari.value.trim();
    ambil(base, q ? { q } : {}, { preserveState: true, replace: true });
}

// Edit produk dibuka di modal. Data form diambil sebagai JSON dari route edit yang sama.
const editId = ref(null);
const editData = ref(null);
const editGagal = ref(false);
const editSedangSimpan = ref(false);

async function bukaEdit(id) {
    editId.value = id;
    editData.value = null;
    editGagal.value = false;

    try {
        const respons = await fetch(`${base}/${id}/edit`, { headers: { Accept: 'application/json' } });

        if (!respons.ok) {
            throw new Error(`HTTP ${respons.status}`);
        }

        editData.value = await respons.json();
    } catch {
        editGagal.value = true;
    }
}

function tutupEdit() {
    editId.value = null;
    editData.value = null;
    editGagal.value = false;
    editSedangSimpan.value = false;
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="`${angka(total)} ${nama} · ${keterangan}`">
            <template #actions>
                <Button v-if="bisa.ekspor" tag="a" variant="outline-secondary" :href="`${base}/ekspor`"><Icon name="download" class="me-1" /> Ekspor CSV</Button>
                <Button v-if="bisa.tambah" :tag="Link" variant="success" :href="`${base}/tambah`"><Icon name="plus" class="me-1" /> Tambah {{ nama }}</Button>
            </template>
        </PageHeader>

        <DataTable
            :columns="kolom"
            :sticky-columns="['foto', 'nama']"
            tinggi-penuh
            :rows="baris.data"
            :memuat="memuat"
            :total="baris.total"
            :links="baris.links"
            :empty-text="q ? `Tidak ada ${nama} yang cocok dengan “${q}”.` : `Belum ada ${nama}.`"
        >
            <template #header>
                <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="kirimCari">
                    <div class="grow min-w-56">
                        <Label for="q" class="sr-only">Cari {{ nama }}</Label>
                        <Input id="q" v-model="cari" type="search" :placeholder="`Cari ${nama}…`" />
                    </div>
                    <Button type="submit" :loading="memuat" :disabled="!berubah">Cari</Button>
                </form>
            </template>

            <template #cell-foto="{ row }">
                <span class="foto-produk-kecil">
                    <img v-if="row.foto" :src="row.foto" :alt="`Foto ${row.nama}`" loading="lazy">
                    <Icon v-else name="camera" />
                </span>
            </template>
            <template #cell-kode="{ row }"><span class="kode-mono">{{ row.kode }}</span></template>
            <template #cell-nama="{ row }">
                <button type="button" class="cursor-pointer font-semibold text-blue-600 hover:underline" @click="bukaEdit(row.id)">{{ row.nama }}</button>
            </template>
            <template #cell-merk="{ row }">
                {{ row.merk ?? '-' }}
                <Badge v-if="row.merk_aktif === false" variant="warning" class="ms-1">tidak aktif</Badge>
            </template>
            <template #cell-produk_fokus="{ row }">
                <Badge :variant="row.produk_fokus === 'Ya' ? 'success' : 'secondary'">{{ row.produk_fokus }}</Badge>
            </template>
            <template #cell-aktif="{ row }">
                <Badge :variant="row.aktif === 'Aktif' ? 'success' : 'secondary'">{{ row.aktif }}</Badge>
            </template>
            <template #cell-total_produk="{ row }">{{ angka(row.total_produk) }}</template>
            <template #cell-diubah="{ row }"><span class="text-gray-500">{{ row.diubah ?? '-' }}</span></template>

            <template #aksi="{ row }">
                <Button variant="outline-secondary" @click="bukaEdit(row.id)">{{ bisa.ubah ? 'Edit' : 'Lihat' }}</Button>
            </template>
        </DataTable>

        <Modal :show="editId !== null" :title="editData ? `${bisa.ubah ? 'Edit' : 'Detail'} ${nama}: ${editData.item.nama}` : nama" placement="kanan" @close="tutupEdit">
            <Alert v-if="editGagal" variant="danger" role="alert">Data {{ nama }} tidak bisa dimuat. Coba lagi.</Alert>
            <FormSkeleton v-else-if="!editData" />
            <FormProduk
                v-else
                id="form-produk-edit"
                :slug="slug"
                :nama="nama"
                :item="editData.item"
                :field="editData.field"
                :nilai="editData.nilai"
                :bisa="bisa"
                :aksi-bawah="false"
                @tersimpan="tutupEdit"
                @proses="editSedangSimpan = $event"
            />

            <template v-if="editData" #footer>
                <Button variant="light" @click="tutupEdit">Batal</Button>
                <Button v-if="bisa.ubah" type="submit" form="form-produk-edit" :loading="editSedangSimpan">Simpan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
