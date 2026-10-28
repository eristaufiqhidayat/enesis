<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import InputRibuan from '@/Components/ui/atoms/InputRibuan.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import Card from '@/Components/ui/molecules/Card.vue';

// Isi form tambah/edit master produk, dipakai di halaman Form dan di modal edit Index.
// Field dari App\Support\Master\MasterProduk (jenis: text, textarea, angka, rupiah, pilihan, foto).
// Field rupiah dan foto dikelompokkan di kartu sendiri di kanan. Emit 'tersimpan' setelah simpan berhasil.
const props = defineProps({
    slug: { type: String, required: true },
    nama: { type: String, required: true },
    item: { type: Object, default: null },
    field: { type: Array, required: true },
    nilai: { type: Object, required: true },
    bisa: { type: Object, required: true },
    // false = tombol Simpan tidak ditampilkan di dalam form (dipakai modal, tombolnya ada di footer modal).
    aksiBawah: { type: Boolean, default: true },
});

const emit = defineEmits(['tersimpan', 'proses']);

const base = `/${props.slug}`;

const bolehSimpan = computed(() => (props.item ? props.bisa.ubah : props.bisa.tambah));

const fieldData = computed(() => props.field.filter((f) => f.jenis !== 'rupiah' && f.jenis !== 'foto'));
const fieldHarga = computed(() => props.field.filter((f) => f.jenis === 'rupiah'));
const fieldFoto = computed(() => props.field.find((f) => f.jenis === 'foto'));

const form = useForm({ ...props.nilai, foto: null });
const pratinjau = ref(null);

watch(() => form.processing, (sedang) => emit('proses', sedang));

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
            onSuccess: () => {
                form.foto = null;
                pratinjau.value = null;
                emit('tersimpan');
            },
        });
    } else {
        form.post(base, {
            forceFormData: true,
            onSuccess: () => emit('tersimpan'),
        });
    }
}
</script>

<template>
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
                                <InputRibuan :id="f.nama" v-model="form[f.nama]" :required="f.wajib" />
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

            <div v-if="bolehSimpan && aksiBawah" class="flex flex-wrap items-center gap-2 mb-3">
                <Button type="submit" :loading="form.processing" :disabled="form.processing">{{ item ? 'Simpan perubahan' : `Tambah ${nama}` }}</Button>
                <span v-if="form.isDirty && item" class="text-gray-500">Ada perubahan yang belum disimpan.</span>
            </div>
        </fieldset>
    </form>
</template>
