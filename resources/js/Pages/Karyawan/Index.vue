<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import FormKaryawan from './partials/FormKaryawan.vue';
import FormSkeleton from '@/Components/ui/molecules/FormSkeleton.vue';
import { useAmbilData } from '@/Support/ambilData.js';
import { angka } from '@/Support/format.js';
import { filterTabelTeks, urutkanTabel } from '@/Support/sortFilterTabel.js';

// Daftar karyawan per kategori (Sales, Koordinator, Supervisor, Lainnya) — satu halaman untuk 4 menu.
// Tambah & edit dibuka di modal kanan; datanya JSON dari /{menu}/tambah atau /{menu}/{id}/edit.
const props = defineProps({
    slug: { type: String, required: true },
    kategori: { type: String, required: true },
    judul: { type: String, required: true },
    nama: { type: String, required: true },
    kolom: { type: Object, required: true },
    baris: { type: Object, required: true },
    q: { type: String, default: '' },
    status: { type: String, default: 'semua' },
    depo: { type: Number, default: null },
    jumlah: { type: Object, required: true },
    daftarDepo: { type: Array, default: () => [] },
    bisa: { type: Object, required: true },
    sortableColumns: { type: Array, default: () => [] },
    sort: { type: Object, default: null },
    filterableColumns: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const base = `/${props.slug}`;
const STATUS = { semua: 'Semua', aktif: 'Aktif', tidak_aktif: 'Tidak aktif' };
const cari = ref({ q: props.q, depo: props.depo ? String(props.depo) : '' });

const bersih = (d) => Object.fromEntries(Object.entries(d).filter(([, v]) => v !== null && v !== undefined && v !== ''));
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ q: cari.value.q.trim(), depo: cari.value.depo }),
    diterapkan: () => ({ q: props.q, depo: props.depo ? String(props.depo) : '' }),
});

function kirimCari() {
    ambil(base, bersih({ status: props.status !== 'semua' ? props.status : null, q: cari.value.q.trim(), depo: cari.value.depo }),
        { preserveState: true, replace: true });
}

function tautanStatus(kode) {
    const query = new URLSearchParams(bersih({ status: kode === 'semua' ? null : kode, q: props.q, depo: props.depo })).toString();
    return query ? `${base}?${query}` : base;
}

const formUrl = ref(null);
const formData = ref(null);
const formGagal = ref(false);
const formSedangSimpan = ref(false);
const bolehSimpan = computed(() => (formData.value?.item ? props.bisa.ubah : props.bisa.tambah));

async function bukaForm(url) {
    formUrl.value = url;
    formData.value = null;
    formGagal.value = false;

    try {
        const respons = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!respons.ok) throw new Error(`HTTP ${respons.status}`);
        formData.value = await respons.json();
    } catch {
        formGagal.value = true;
    }
}

function tutupForm() {
    formUrl.value = null;
    formData.value = null;
    formGagal.value = false;
    formSedangSimpan.value = false;
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" :subtitle="`${angka(jumlah.semua)} ${nama} · ${angka(jumlah.aktif)} aktif · dipakai aplikasi HP, akun login, dan laporan motoris`">
            <template #actions>
                <Button v-if="bisa.ekspor" tag="a" variant="outline-secondary" :href="`${base}/ekspor`"><Icon name="download" class="me-1" /> Ekspor CSV</Button>
                <Button v-if="bisa.tambah" variant="success" @click="bukaForm(`${base}/tambah`)"><Icon name="plus" class="me-1" /> Tambah {{ nama }}</Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Saring status">
            <Button
                v-for="(label, kode) in STATUS"
                :key="kode"
                :tag="Link"
                :variant="status === kode ? 'primary' : 'outline-secondary'"
                :href="tautanStatus(kode)"
                :aria-current="status === kode ? 'true' : null"
            >
                {{ label }} <span class="ms-1 opacity-75">{{ angka(jumlah[kode]) }}</span>
            </Button>
        </div>

        <DataTable
            :columns="kolom"
            :sticky-columns="['foto', 'nama']"
            tinggi-penuh
            :rows="baris.data"
            :memuat="memuat"
            :total="baris.total"
            :links="baris.links"
            :empty-text="q ? `Tidak ada ${nama} yang cocok dengan “${q}”.` : `Belum ada ${nama}.`"
            :sortable="sortableColumns"
            :sort="sort"
            @sort="urutkanTabel"
            :filterable="filterableColumns"
            :filter-values="filterValues"
            @filter="filterTabelTeks"
        >
            <template #header>
                <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="kirimCari">
                    <div class="grow min-w-56">
                        <Label for="q" class="sr-only">Cari {{ nama }}</Label>
                        <Input id="q" v-model="cari.q" type="search" placeholder="Cari nama, no HP, email, koordinator…" />
                    </div>
                    <div v-if="daftarDepo.length" class="min-w-48">
                        <Label for="filter_depo" class="sr-only">Depo</Label>
                        <Combobox id="filter_depo" v-model="cari.depo" :options="daftarDepo" placeholder="Semua depo" />
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
            <template #cell-nama="{ row }">
                <button type="button" class="cursor-pointer font-semibold text-blue-600 hover:underline" @click="bukaForm(`${base}/${row.id}/edit`)">{{ row.nama }}</button>
            </template>
            <template #cell-aktif="{ row }">
                <Badge :variant="row.aktif === 'Aktif' ? 'success' : 'secondary'">{{ row.aktif }}</Badge>
            </template>
            <template #cell-total_area="{ row }">{{ angka(row.total_area) }}</template>
            <template #cell-jumlah_sales="{ row }">{{ angka(row.jumlah_sales) }}</template>
            <template #cell-diubah="{ row }"><span class="text-gray-500">{{ row.diubah ?? '-' }}</span></template>
        </DataTable>

        <Modal
            :show="formUrl !== null"
            :title="formData ? (formData.item ? `${bisa.ubah ? 'Edit' : 'Detail'} ${nama}: ${formData.item.nama}` : `Tambah ${nama}`) : judul"
            placement="kanan"
            @close="tutupForm"
        >
            <Alert v-if="formGagal" variant="danger" role="alert">Form {{ nama }} tidak bisa dimuat. Coba lagi.</Alert>
            <FormSkeleton v-else-if="!formData" :harga="true" :foto="true" :jumlah-field="6" />
            <template v-else>
                <p v-if="formData.item" class="mb-2 text-gray-500">
                    ID {{ formData.item.id }}<template v-if="formData.item.akun.length"> · akun login: {{ formData.item.akun.join(', ') }}</template>
                    · <Link :href="`${base}/${formData.item.id}/edit`">buka halaman lengkap</Link>
                </p>
                <FormKaryawan
                    id="form-karyawan"
                    :slug="slug"
                    :kategori="kategori"
                    :nama="nama"
                    :item="formData.item"
                    :nilai="formData.nilai"
                    :opsi="formData.opsi"
                    :bisa="bisa"
                    :aksi-bawah="false"
                    @tersimpan="tutupForm"
                    @proses="formSedangSimpan = $event"
                />
            </template>

            <template v-if="formData" #footer>
                <Button variant="light" @click="tutupForm">Batal</Button>
                <Button v-if="bolehSimpan" type="submit" form="form-karyawan" :loading="formSedangSimpan">Simpan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
