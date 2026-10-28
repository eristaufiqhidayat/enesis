<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';

// Form tambah/edit master produk. Field dari App\Support\Master\MasterProduk (jenis: text, textarea, angka,
// rupiah, pilihan, foto). Field rupiah dan foto dikelompokkan di kartu sendiri di kanan.
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    nama: { type: String, required: true },
    item: { type: Object, default: null },
    field: { type: Array, required: true },
    nilai: { type: Object, required: true },
    dipakai: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    bisa: { type: Object, required: true },
});

const page = usePage();
const appName = page.props.appName;
const base = `/${props.slug}`;

const bolehSimpan = computed(() => (props.item ? props.bisa.ubah : props.bisa.tambah));
const judulHalaman = computed(() => (props.item ? `${props.bisa.ubah ? 'Edit' : 'Detail'} ${props.nama}: ${props.item.nama}` : `Tambah ${props.nama}`));
const subtitle = computed(() => {
    if (!props.item) return `${props.judul} baru langsung tersedia di aplikasi HP pada sinkron master berikutnya.`;
    const dipakai = props.dipakai.length ? `Dipakai ${props.dipakai.join(', ')}.` : 'Belum dipakai data lain.';

    return `ID ${props.item.id} · ${dipakai}${props.item.diubah ? ` Terakhir diubah ${props.item.diubah}.` : ''}`;
});

const fieldData = computed(() => props.field.filter((f) => f.jenis !== 'rupiah' && f.jenis !== 'foto'));
const fieldHarga = computed(() => props.field.filter((f) => f.jenis === 'rupiah'));
const fieldFoto = computed(() => props.field.find((f) => f.jenis === 'foto'));

const form = useForm({ ...props.nilai, foto: null });
const pratinjau = ref(null);

function pilihFoto(e) {
    const file = e.target.files?.[0] ?? null;
    form.foto = file;
    pratinjau.value = file ? URL.createObjectURL(file) : null;
}

function simpan() {
    if (props.item) {
        form.transform((d) => ({ ...d, _method: 'put' })).post(`${base}/${props.item.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.foto = null; pratinjau.value = null; },
        });
    } else {
        form.post(base, { forceFormData: true });
    }
}

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

        <form @submit.prevent="simpan">
            <fieldset :disabled="!bolehSimpan" class="contents">
                <div class="grid grid-cols-1 gap-3 mb-3" :class="{ 'lg:grid-cols-3': fieldHarga.length || fieldFoto }">
                    <Card :class="{ 'lg:col-span-2': fieldHarga.length || fieldFoto }">
                        <template #header>Data {{ nama }}</template>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-for="f in fieldData" :key="f.nama" :class="{ 'sm:col-span-2': f.jenis === 'textarea' }">
                                <Label :for="f.nama">{{ f.label }} <span v-if="f.wajib" class="text-red-600">*</span></Label>
                                <Select v-if="f.jenis === 'pilihan'" :id="f.nama" v-model="form[f.nama]" :options="f.opsi" :placeholder="`-- Pilih ${f.label.toLowerCase()} --`" :required="f.wajib" />
                                <Textarea v-else-if="f.jenis === 'textarea'" :id="f.nama" v-model="form[f.nama]" rows="3" :maxlength="f.maks" />
                                <Input
                                    v-else
                                    :id="f.nama"
                                    v-model="form[f.nama]"
                                    :type="f.jenis === 'angka' ? 'number' : 'text'"
                                    :step="f.jenis === 'angka' ? 'any' : null"
                                    :min="f.jenis === 'angka' ? 0 : null"
                                    :maxlength="f.maks"
                                    :required="f.wajib"
                                />
                                <small v-if="f.bantuan" class="block text-gray-500 mt-1">{{ f.bantuan }}</small>
                                <FieldError v-if="form.errors[f.nama]">{{ form.errors[f.nama] }}</FieldError>
                            </div>
                        </div>
                    </Card>

                    <div v-if="fieldHarga.length || fieldFoto" class="flex flex-col gap-3 min-w-0">
                        <Card v-if="fieldHarga.length">
                            <template #header>Harga (Rp)</template>
                            <div class="grid grid-cols-2 gap-3">
                                <div v-for="f in fieldHarga" :key="f.nama">
                                    <Label :for="f.nama">{{ f.label }} <span v-if="f.wajib" class="text-red-600">*</span></Label>
                                    <Input :id="f.nama" v-model="form[f.nama]" inputmode="numeric" :required="f.wajib" />
                                    <small v-if="f.bantuan" class="block text-gray-500 mt-1">{{ f.bantuan }}</small>
                                    <FieldError v-if="form.errors[f.nama]">{{ form.errors[f.nama] }}</FieldError>
                                </div>
                            </div>
                        </Card>

                        <Card v-if="fieldFoto">
                            <template #header>Foto</template>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="foto-produk">
                                    <img v-if="pratinjau || item?.foto" :src="pratinjau ?? item.foto" :alt="`Foto ${item?.nama ?? nama}`">
                                    <template v-else-if="item?.foto_lama">Foto lama<br>di Marketama</template>
                                    <template v-else>Belum ada foto</template>
                                </span>
                                <div class="min-w-0">
                                    <Label for="foto">{{ item?.foto ? 'Ganti foto' : 'Unggah foto' }}</Label>
                                    <Input id="foto" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @change="pilihFoto" />
                                    <small class="block text-gray-500 mt-1">{{ fieldFoto.bantuan }}</small>
                                    <small v-if="item?.foto_lama && !item?.foto" class="block text-gray-500 kode-mono break-all">{{ item.foto_lama }}</small>
                                    <FieldError v-if="form.errors.foto">{{ form.errors.foto }}</FieldError>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>

                <div v-if="bolehSimpan" class="flex flex-wrap items-center gap-2 mb-3">
                    <Button type="submit" :loading="form.processing" :disabled="form.processing">{{ item ? 'Simpan perubahan' : `Tambah ${nama}` }}</Button>
                    <span v-if="form.isDirty && item" class="text-gray-500">Ada perubahan yang belum disimpan.</span>
                </div>
            </fieldset>
        </form>

        <div v-if="item && bisa.hapus" class="mb-3">
            <Button variant="outline-danger" @click="modalHapus = true">Hapus {{ nama }}</Button>
        </div>

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

        <Modal :show="modalHapus" :title="`Hapus ${nama}?`" @close="modalHapus = false">
            <p v-if="dipakai.length">{{ item.nama }} masih dipakai {{ dipakai.join(', ') }}, jadi tidak bisa dihapus.</p>
            <p v-else>{{ item?.nama }} akan dihapus permanen dan hilang dari aplikasi HP.</p>
            <template #footer>
                <Button variant="light" @click="modalHapus = false">Batal</Button>
                <Button v-if="!dipakai.length" variant="outline-danger" @click="hapus">Ya, hapus</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
