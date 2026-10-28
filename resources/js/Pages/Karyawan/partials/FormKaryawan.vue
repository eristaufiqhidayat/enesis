<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import InputRibuan from '@/Components/ui/atoms/InputRibuan.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';

// Isi form tambah/edit karyawan (Sales, Koordinator, Supervisor, Lainnya), dipakai di halaman Form dan modal Index.
// Field per kategori mengikuti Marketama. Pilihan koordinator/area (sales) dan wilayah depo (koordinator) dimuat
// ulang dari /{menu}/opsi saat depo diganti. Emit 'tersimpan' setelah simpan berhasil, 'proses' saat menyimpan.
const props = defineProps({
    slug: { type: String, required: true },
    kategori: { type: String, required: true },
    nama: { type: String, required: true },
    item: { type: Object, default: null },
    nilai: { type: Object, required: true },
    opsi: { type: Object, required: true },
    bisa: { type: Object, required: true },
    // false = tombol Simpan tidak ditampilkan di dalam form (modal: tombolnya di footer modal).
    aksiBawah: { type: Boolean, default: true },
});

const emit = defineEmits(['tersimpan', 'proses']);

const base = `/${props.slug}`;
const bolehSimpan = computed(() => (props.item ? props.bisa.ubah : props.bisa.tambah));
const pakaiDepo = computed(() => ['sales', 'koordinator'].includes(props.kategori));
const wajibKontak = computed(() => props.kategori !== 'lainnya');

const GAJI = [
    ['gaji_pokok', 'Gaji pokok'],
    ['tunjangan_bbm_makan', 'Tunjangan BBM & makan'],
    ['sewa_motor', 'Sewa motor'],
    ['tunjangan_internet', 'Tunjangan kuota internet'],
];

const form = useForm({ ...props.nilai, foto: null });
const pilihan = ref({ koordinator: props.opsi.koordinator ?? [], area: props.opsi.area ?? [], wilayah: props.opsi.wilayah ?? [] });
const memuatOpsi = ref(false);
const pratinjau = ref(null);

watch(() => form.processing, (sedang) => emit('proses', sedang));

// Ganti depo: muat ulang pilihan dan kosongkan isian yang bergantung pada depo lama.
watch(() => form.depo_id, async (depo, sebelum) => {
    if (depo === sebelum) return;
    form.koordinator_id = '';
    form.wilayah_depo_id = '';
    form.area_ids = [];
    form.area_aktif_ids = [];

    if (!depo) {
        pilihan.value = { koordinator: [], area: [], wilayah: [] };
        return;
    }

    memuatOpsi.value = true;
    try {
        const respons = await fetch(`${base}/opsi?depo=${encodeURIComponent(depo)}`, { headers: { Accept: 'application/json' } });
        pilihan.value = await respons.json();
    } finally {
        memuatOpsi.value = false;
    }
});

// Area aktif hanya boleh dari area yang dipilih.
const opsiAreaAktif = computed(() => pilihan.value.area.filter((a) => form.area_ids.includes(a.value)));
watch(() => form.area_ids, (area) => {
    form.area_aktif_ids = form.area_aktif_ids.filter((a) => area.includes(a));
}, { deep: true });

function pilihFoto(e) {
    const file = e.target.files?.[0] ?? null;
    form.foto = file;
    pratinjau.value = file ? URL.createObjectURL(file) : null;
}

function simpan() {
    const opsiKirim = { forceFormData: true, preserveScroll: true, onSuccess: () => emit('tersimpan') };

    if (props.item) {
        form.transform((d) => ({ ...d, _method: 'put' })).post(`${base}/${props.item.id}`, opsiKirim);
    } else {
        form.post(base, opsiKirim);
    }
}
</script>

<template>
    <form @submit.prevent="simpan">
        <fieldset :disabled="!bolehSimpan" class="contents">
            <div class="grid grid-cols-1 gap-3 mb-3 lg:grid-cols-3">
                <div class="flex flex-col gap-3 min-w-0 lg:col-span-2">
                    <Card>
                        <template #header>Data {{ nama }}</template>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <Label for="nama">Nama lengkap <span class="text-red-600">*</span></Label>
                                <Input id="nama" v-model="form.nama" required maxlength="150" />
                                <FieldError v-if="form.errors.nama">{{ form.errors.nama }}</FieldError>
                            </div>

                            <div v-if="kategori === 'lainnya'">
                                <Label for="jabatan">Kategori karyawan <span class="text-red-600">*</span></Label>
                                <Select id="jabatan" v-model="form.jabatan" :options="opsi.jabatan" placeholder="-- Pilih kategori --" required />
                                <FieldError v-if="form.errors.jabatan">{{ form.errors.jabatan }}</FieldError>
                            </div>

                            <template v-if="pakaiDepo">
                                <div>
                                    <Label for="depo_id">Depo <span class="text-red-600">*</span></Label>
                                    <Combobox id="depo_id" v-model="form.depo_id" :options="opsi.depo" placeholder="Cari depo..." :clearable="false" />
                                    <FieldError v-if="form.errors.depo_id">{{ form.errors.depo_id }}</FieldError>
                                </div>
                                <div v-if="kategori === 'sales'">
                                    <Label for="koordinator_id">Koordinator <span class="text-red-600">*</span></Label>
                                    <Select id="koordinator_id" v-model="form.koordinator_id" :options="pilihan.koordinator" :disabled="!form.depo_id || memuatOpsi"
                                        :placeholder="form.depo_id ? '-- Pilih koordinator --' : 'Pilih depo dulu'" required />
                                    <FieldError v-if="form.errors.koordinator_id">{{ form.errors.koordinator_id }}</FieldError>
                                </div>
                                <div v-else>
                                    <Label for="wilayah_depo_id">Wilayah depo</Label>
                                    <Select id="wilayah_depo_id" v-model="form.wilayah_depo_id" :options="pilihan.wilayah" :disabled="!form.depo_id || memuatOpsi"
                                        :placeholder="form.depo_id ? '-- Pilih wilayah depo --' : 'Pilih depo dulu'" />
                                    <FieldError v-if="form.errors.wilayah_depo_id">{{ form.errors.wilayah_depo_id }}</FieldError>
                                </div>
                            </template>

                            <div>
                                <Label for="no_hp">No HP <span v-if="wajibKontak" class="text-red-600">*</span></Label>
                                <Input id="no_hp" v-model="form.no_hp" inputmode="tel" maxlength="30" :required="wajibKontak" />
                                <FieldError v-if="form.errors.no_hp">{{ form.errors.no_hp }}</FieldError>
                            </div>
                            <div>
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" maxlength="150" />
                                <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
                            </div>
                            <div>
                                <Label for="tanggal_mulai">Tanggal mulai bekerja <span v-if="wajibKontak" class="text-red-600">*</span></Label>
                                <Input id="tanggal_mulai" v-model="form.tanggal_mulai" type="date" :required="wajibKontak" />
                                <FieldError v-if="form.errors.tanggal_mulai">{{ form.errors.tanggal_mulai }}</FieldError>
                            </div>
                            <div>
                                <Label for="aktif">Status <span class="text-red-600">*</span></Label>
                                <Select id="aktif" v-model="form.aktif" :options="[{ value: '1', label: 'Aktif' }, { value: '0', label: 'Tidak aktif' }]" required />
                                <FieldError v-if="form.errors.aktif">{{ form.errors.aktif }}</FieldError>
                            </div>
                        </div>
                    </Card>

                    <Card v-if="kategori === 'sales'">
                        <template #header>Area sales</template>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <Label for="area_ids">Area <span class="text-red-600">*</span></Label>
                                <Combobox id="area_ids" v-model="form.area_ids" multiple :options="pilihan.area" :disabled="!form.depo_id || memuatOpsi"
                                    :placeholder="form.depo_id ? 'Cari area di depo ini...' : 'Pilih depo dulu'" />
                                <small class="block text-gray-500 mt-1">{{ form.area_ids.length }} area dipilih dari {{ pilihan.area.length }} area depo.</small>
                                <FieldError v-if="form.errors.area_ids">{{ form.errors.area_ids }}</FieldError>
                            </div>
                            <div>
                                <Label for="area_aktif_ids">Area aktif (rute di aplikasi HP)</Label>
                                <Combobox id="area_aktif_ids" v-model="form.area_aktif_ids" multiple :options="opsiAreaAktif" :disabled="!form.area_ids.length"
                                    placeholder="Pilih dari area di atas..." />
                                <small class="block text-gray-500 mt-1">Kosong = semua area sales ini menjadi rute.</small>
                                <FieldError v-if="form.errors.area_aktif_ids">{{ form.errors.area_aktif_ids }}</FieldError>
                            </div>
                        </div>
                    </Card>
                </div>

                <div class="flex flex-col gap-3 min-w-0">
                    <Card>
                        <template #header>Gaji & tunjangan (Rp)</template>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-3">
                            <div v-for="[kolom, label] in GAJI" :key="kolom">
                                <Label :for="kolom">{{ label }}</Label>
                                <InputRibuan :id="kolom" v-model="form[kolom]" />
                                <FieldError v-if="form.errors[kolom]">{{ form.errors[kolom] }}</FieldError>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <template #header>Foto</template>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="foto-produk">
                                <img v-if="pratinjau || item?.foto" :src="pratinjau ?? item.foto" :alt="`Foto ${item?.nama ?? nama}`">
                                <template v-else>Belum ada foto</template>
                            </span>
                            <div class="min-w-0">
                                <Label for="foto">{{ item?.foto ? 'Ganti foto' : 'Unggah foto' }}</Label>
                                <Input id="foto" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @change="pilihFoto" />
                                <small class="block text-gray-500 mt-1">.jpg, .jpeg, .png, maksimal 2 MB.</small>
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
