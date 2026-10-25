<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import PasswordInput from '@/Components/ui/molecules/PasswordInput.vue';
import Radio from '@/Components/ui/atoms/Radio.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import OutletSales from './partials/OutletSales.vue';
import SalesBawahan from './partials/SalesBawahan.vue';

const props = defineProps({
    menu: { type: String, required: true },
    judul: { type: String, required: true },
    akun: { type: Object, default: null },
    karyawan: { type: Object, default: null },
    kategoriAwal: { type: String, required: true },
    pilihanKategori: { type: Object, required: true },
    roles: { type: Array, required: true },
    salesBawahan: { type: Array, default: null },
    outletSales: { type: Object, default: null },
    bisaLihatSales: { type: Boolean, default: false },
    bisaAturSales: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const base = `/${props.menu}`;
const isEdit = computed(() => props.akun !== null);
const judulHalaman = computed(() => (isEdit.value ? `Edit akun ${props.akun.username}` : 'Tambah akun'));
const batal = computed(() => (isEdit.value ? `${base}/${props.akun.id}` : base));

const form = useForm({
    kategori: props.kategoriAwal,
    karyawan_id: props.akun?.karyawanId ?? props.karyawan?.id ?? null,
    username: props.akun?.username ?? '',
    name: props.akun?.name ?? '',
    role_id: props.akun?.roleId ?? '',
    aktif: props.akun?.aktif ?? true,
    ...(isEdit.value ? {} : {
        password_mode: 'otomatis',
        password: '',
        password_confirmation: '',
        wajib_ganti_password: true,
    }),
});

// Kosong = null (karyawan & role opsional).
form.transform((data) => ({ ...data, karyawan_id: data.karyawan_id || null, role_id: data.role_id || null }));

const karyawanTerpilih = ref(props.karyawan);
const cariKaryawan = ref(props.karyawan?.nama ?? '');
const hasilKaryawan = ref([]);
const sudahCari = ref(false);
let timer;
let kueriTerakhir = 0;

// Mengetik ulang membatalkan pilihan lama; karyawan harus dipilih lagi dari hasil.
function ubahCariKaryawan(nilai) {
    cariKaryawan.value = nilai;
    form.karyawan_id = null;
    karyawanTerpilih.value = null;
    clearTimeout(timer);

    const kueri = ++kueriTerakhir;
    timer = setTimeout(async () => {
        const q = nilai.trim();
        sudahCari.value = q.length >= 2;
        if (q.length < 2) {
            hasilKaryawan.value = [];
            return;
        }

        const res = await fetch(`${base}/karyawan?q=${encodeURIComponent(q)}&kecuali=${props.akun?.id ?? 0}`, { headers: { Accept: 'application/json' } });
        const data = res.ok ? await res.json() : [];
        if (kueri === kueriTerakhir) hasilKaryawan.value = data;
    }, 250);
}

function pilihKaryawan(k) {
    form.karyawan_id = k.id;
    karyawanTerpilih.value = k;
    cariKaryawan.value = k.nama;
    hasilKaryawan.value = [];
    sudahCari.value = false;
    if (!form.name) form.name = k.nama;
}

function teksKaryawan(k) {
    return `${k.nama} · ID ${k.id} · ${k.kategori} · Depo ${k.depo ?? '-'}` + (k.akun_aktif ? ` — sudah punya akun aktif: ${k.akun_aktif}` : '');
}

function simpan() {
    if (isEdit.value) {
        form.put(`${base}/${props.akun.id}`);
    } else {
        form.post(base);
    }
}
</script>

<template>
    <Head :title="`${judulHalaman} · ${appName}`" />
    <AppLayout :breadcrumb-slug="menu">
        <PageHeader :title="judulHalaman" :subtitle="`${judul}${isEdit ? '' : ' · password awal ditampilkan sekali setelah disimpan'}`" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            <form class="lg:col-span-2" autocomplete="off" @submit.prevent="simpan">
                <Card>
                    <fieldset class="mb-3">
                        <legend class="form-label">Kategori akun</legend>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="(label, kode) in pilihanKategori" :key="kode" class="form-check">
                                <Radio v-model="form.kategori" :value="kode" />
                                {{ label }}
                            </label>
                        </div>
                        <FieldError v-if="form.errors.kategori">{{ form.errors.kategori }}</FieldError>
                    </fieldset>

                    <div class="mb-3">
                        <Label for="cari_karyawan">Karyawan <span class="text-gray-500">(wajib untuk sales &amp; koordinator)</span></Label>
                        <Input id="cari_karyawan" type="search" :model-value="cariKaryawan" placeholder="Ketik nama atau ID karyawan…" aria-describedby="karyawan_info" aria-controls="hasil_karyawan" @update:model-value="ubahCariKaryawan" />
                        <div id="karyawan_info" class="text-xs text-gray-500 mt-1">
                            <template v-if="karyawanTerpilih">
                                Terpilih: <strong>{{ karyawanTerpilih.nama }}</strong> · ID {{ karyawanTerpilih.id }} · Depo {{ karyawanTerpilih.depo ?? '-' }} · Koor {{ karyawanTerpilih.koordinator ?? '-' }}
                            </template>
                            <template v-else>Hanya karyawan aktif. Satu karyawan hanya boleh punya satu akun aktif.</template>
                        </div>
                        <FieldError v-if="form.errors.karyawan_id">{{ form.errors.karyawan_id }}</FieldError>
                        <div v-if="hasilKaryawan.length || sudahCari" id="hasil_karyawan" class="list-group mt-1" role="listbox" aria-label="Hasil cari karyawan">
                            <button
                                v-for="k in hasilKaryawan"
                                :key="k.id"
                                type="button"
                                class="list-group-item list-group-item-action text-start"
                                role="option"
                                @click="pilihKaryawan(k)"
                            >{{ teksKaryawan(k) }}</button>
                            <div v-if="sudahCari && !hasilKaryawan.length" class="list-group-item text-gray-500">Tidak ada karyawan aktif yang cocok.</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <Label for="username">Username</Label>
                            <Input id="username" v-model="form.username" required maxlength="60" />
                            <div class="text-xs text-gray-500 mt-1">Huruf besar/kecil dianggap sama; tanpa spasi.</div>
                            <FieldError v-if="form.errors.username">{{ form.errors.username }}</FieldError>
                        </div>
                        <div>
                            <Label for="name">Nama tampilan</Label>
                            <Input id="name" v-model="form.name" maxlength="120" placeholder="Bawaan: nama karyawan" />
                            <FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError>
                        </div>
                    </div>

                    <div class="mb-3">
                        <Label for="role_id">Role</Label>
                        <Select id="role_id" v-model="form.role_id">
                            <option value="">Bawaan sesuai kategori</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nama }}</option>
                        </Select>
                        <div class="text-xs text-gray-500 mt-1">Role menentukan menu, hak ubah, cakupan data, dan boleh login di web / aplikasi HP.</div>
                        <FieldError v-if="form.errors.role_id">{{ form.errors.role_id }}</FieldError>
                    </div>

                    <fieldset v-if="!isEdit" class="mb-3 rounded-md border border-gray-200 p-3">
                        <legend class="form-label px-1">Password awal</legend>
                        <label class="form-check mb-2">
                            <Radio v-model="form.password_mode" value="otomatis" />
                            <span><strong>Buat otomatis</strong> (10 karakter acak) — tampil sekali, wajib diganti saat login pertama.</span>
                        </label>
                        <label class="form-check mb-2">
                            <Radio v-model="form.password_mode" value="manual" />
                            <span><strong>Tentukan sendiri</strong> (minimal 8 karakter, tidak sama dengan username)</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                            <div>
                                <Label for="password">Password</Label>
                                <PasswordInput id="password" v-model="form.password" autocomplete="new-password" />
                                <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
                            </div>
                            <div>
                                <Label for="password_confirmation">Ulangi password</Label>
                                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" />
                            </div>
                        </div>
                        <label class="form-check mt-2">
                            <Checkbox v-model="form.wajib_ganti_password" />
                            Wajib ganti password saat login pertama
                        </label>
                    </fieldset>

                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 p-3 mb-3">
                        <div>
                            <strong>Akun aktif</strong>
                            <div class="text-xs text-gray-500">Akun nonaktif tidak bisa login dan sesi HP-nya dicabut.</div>
                        </div>
                        <label class="form-check">
                            <Checkbox v-model="form.aktif" role="switch" />
                            Aktif
                        </label>
                    </div>

                    <template #footer>
                        <div class="flex justify-end gap-2">
                            <Button :tag="Link" variant="outline-secondary" :href="batal">Batal</Button>
                            <Button type="submit" :disabled="form.processing">Simpan akun</Button>
                        </div>
                    </template>
                </Card>
            </form>

            <div class="flex flex-col gap-3">
                <Card>
                    <template #header><h6 class="mb-0 font-semibold">Aturan akun</h6></template>
                    <ul class="list-disc ps-4 text-gray-600 flex flex-col gap-1">
                        <li>Username unik, huruf besar/kecil dianggap sama.</li>
                        <li>Sales &amp; koordinator wajib terhubung ke karyawan aktif; depo, area rute, dan koordinator ikut data karyawan.</li>
                        <li>Satu karyawan hanya boleh punya satu akun aktif.</li>
                        <li>Password tidak pernah ditampilkan lagi setelah halaman password awal ditutup.</li>
                    </ul>
                </Card>
                <Alert v-if="akun?.sumberJenis" variant="info">
                    Akun ini dari data aplikasi lama. Menjalankan ulang <code>enesis:sinkron-user</code> menimpa username, status, dan karyawannya.
                </Alert>
            </div>
        </div>

        <div class="mt-3">
            <SalesBawahan
                v-if="salesBawahan !== null"
                :akun="akun"
                :sales-bawahan="salesBawahan"
                :bisa-lihat-sales="bisaLihatSales"
                :bisa-atur-sales="bisaAturSales"
            />
            <OutletSales v-if="outletSales !== null" :outlet-sales="outletSales" />
        </div>
    </AppLayout>
</template>
