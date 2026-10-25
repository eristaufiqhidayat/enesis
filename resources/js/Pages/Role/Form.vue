<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Radio from '@/Components/ui/atoms/Radio.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { angka } from '@/Support/format.js';

const props = defineProps({
    role: { type: Object, default: null },
    hak: { type: [Object, Array], default: () => ({}) },
    grup: { type: Array, required: true },
    aksi: { type: Object, required: true },
    cakupan: { type: Object, required: true },
    jumlahAkun: { type: Number, default: 0 },
    bisaUbah: { type: Boolean, default: false },
    bisaHapus: { type: Boolean, default: false },
    superAdmin: { type: Boolean, default: false },
});

const page = usePage();
const appName = page.props.appName;

const judul = computed(() => (props.role ? `Edit role: ${props.role.nama}` : 'Tambah role'));
const subtitle = computed(() => (props.role
    ? `Dipakai ${angka(props.jumlahAkun)} akun. Perubahan berlaku pada halaman berikutnya yang mereka buka.`
    : 'Role baru belum punya anggota; pilih role ini di form akun.'));
const pilihanCakupan = Object.entries(props.cakupan).map(([kode, [label, desc]]) => ({ kode, label, desc }));
const errorHapus = computed(() => page.props.errors?.hapus);

// Matriks lengkap (menu x aksi) supaya setiap kotak punya nilai awal; hak yang belum ada = tidak boleh.
const matriksAwal = () => Object.fromEntries(props.grup.flatMap((g) => g.menu).map((m) => [
    m.slug,
    Object.fromEntries(Object.keys(props.aksi).map((kode) => [kode, Boolean(props.hak?.[m.slug]?.[kode])])),
]));

const form = useForm({
    nama: props.role?.nama ?? '',
    keterangan: props.role?.keterangan ?? '',
    cakupan: props.role?.cakupan ?? 'depo',
    login_web: props.role ? Boolean(props.role.login_web) : true,
    login_mobile: Boolean(props.role?.login_mobile),
    hak: matriksAwal(),
});

function simpan() {
    if (props.role) {
        form.put(`/role/${props.role.id}`, { preserveScroll: true, onSuccess: () => form.defaults() });
    } else {
        form.post('/role');
    }
}

// Aksi selain "lihat" otomatis menyalakan "lihat"; mematikan "lihat" mematikan semuanya.
function setAksi(menu, kode, nilai) {
    form.hak[menu][kode] = nilai;

    if (kode !== 'lihat' && nilai) form.hak[menu].lihat = true;
    if (kode === 'lihat' && !nilai) Object.keys(form.hak[menu]).forEach((k) => { form.hak[menu][k] = false; });
}

// Centang di baris grup = centang/hapus aksi itu untuk semua menu di grup.
function setGrup(g, kode, nilai) {
    g.menu.forEach((m) => setAksi(m.slug, kode, nilai));
}

function statusGrup(g, kode) {
    const nilai = g.menu.map((m) => form.hak[m.slug][kode]);
    const jumlah = nilai.filter(Boolean).length;

    return { checked: jumlah > 0 && jumlah === nilai.length, indeterminate: jumlah > 0 && jumlah < nilai.length };
}

const cari = ref('');
function tampilMenu(m) {
    const q = cari.value.trim().toLowerCase();

    return q === '' || m.title.toLowerCase().includes(q);
}

const modalHapus = ref(false);
function hapusRole() {
    router.delete(`/role/${props.role.id}`, { onFinish: () => (modalHapus.value = false) });
}
</script>

<template>
    <Head :title="`${role ? 'Role ' + role.nama : 'Tambah role'} · ${appName}`" />
    <AppLayout breadcrumb-slug="role">
        <PageHeader :title="judul" :subtitle="subtitle">
            <template v-if="role" #actions>
                <Button :tag="Link" variant="light" :href="`/role/${role.id}/anggota`">Lihat {{ angka(jumlahAkun) }} anggota</Button>
                <Button v-if="bisaHapus" variant="outline-danger" @click="modalHapus = true">Hapus role</Button>
            </template>
        </PageHeader>

        <Alert v-if="errorHapus" variant="danger" class="mb-3" role="alert">{{ errorHapus }}</Alert>

        <form @submit.prevent="simpan">
            <fieldset :disabled="!bisaUbah" class="contents">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-3">
                    <Card>
                        <div class="mb-3">
                            <Label for="nama">Nama role</Label>
                            <Input id="nama" v-model="form.nama" required maxlength="80" />
                            <FieldError v-if="form.errors.nama">{{ form.errors.nama }}</FieldError>
                        </div>
                        <div class="mb-3">
                            <Label for="keterangan">Keterangan</Label>
                            <Textarea id="keterangan" v-model="form.keterangan" rows="2" maxlength="255" />
                            <FieldError v-if="form.errors.keterangan">{{ form.errors.keterangan }}</FieldError>
                        </div>
                        <fieldset>
                            <legend class="form-label">Bisa login di</legend>
                            <label class="form-check mb-1">
                                <Checkbox v-model="form.login_web" :disabled="superAdmin" />
                                Web Enesis
                            </label>
                            <br>
                            <label class="form-check">
                                <Checkbox v-model="form.login_mobile" />
                                Aplikasi HP (motoris / koordinator)
                            </label>
                        </fieldset>
                    </Card>

                    <Card class="lg:col-span-2">
                        <fieldset>
                            <legend class="form-label">Cakupan data</legend>
                            <p class="text-xs text-gray-500">Data di luar cakupan tidak tampil dan tidak bisa diubah. Saat ini berlaku di menu kelola akun; menu lain menyusul.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <label
                                    v-for="p in pilihanCakupan"
                                    :key="p.kode"
                                    class="flex items-start gap-2 rounded-md border border-gray-200 p-3 has-checked:border-blue-500 has-checked:bg-blue-50"
                                >
                                    <Radio v-model="form.cakupan" :value="p.kode" class="mt-0.5" />
                                    <span><strong>{{ p.label }}</strong><br><span class="text-gray-500">{{ p.desc }}</span></span>
                                </label>
                            </div>
                            <FieldError v-if="form.errors.cakupan">{{ form.errors.cakupan }}</FieldError>
                        </fieldset>
                    </Card>
                </div>

                <Alert v-if="superAdmin" variant="info" class="mb-3">Super Admin selalu punya semua hak di semua menu, jadi matriks hak tidak dipakai.</Alert>

                <Card v-else class="mb-3" body-class="overflow-x-auto">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h6 class="mb-0 font-semibold">Hak akses per menu</h6>
                                <span class="text-xs text-gray-500">Mengikuti grup menu di sidebar. Tanpa "Lihat", menu tidak tampil dan aksi lain tidak berlaku.</span>
                            </div>
                            <div class="w-56">
                                <Label for="cari_menu" class="sr-only">Cari menu</Label>
                                <Input id="cari_menu" v-model="cari" type="search" placeholder="Cari menu…" />
                            </div>
                        </div>
                    </template>

                    <Alert v-if="form.errors.hak" variant="danger" class="mb-3" role="alert">{{ form.errors.hak }}</Alert>

                    <table id="matriks_hak" class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Menu</th>
                                <th v-for="(label, kode) in aksi" :key="kode" scope="col" class="text-center w-20">{{ label }}</th>
                            </tr>
                        </thead>
                        <tbody v-for="g in grup" :key="g.label">
                            <tr class="bg-gray-50">
                                <th scope="rowgroup" class="text-xs font-bold uppercase tracking-wide">{{ g.label }}</th>
                                <td v-for="(label, kode) in aksi" :key="kode" class="text-center">
                                    <Checkbox
                                        :model-value="statusGrup(g, kode).checked"
                                        :indeterminate="statusGrup(g, kode).indeterminate"
                                        :aria-label="`${label} semua menu grup ${g.label}`"
                                        title="Semua di grup ini"
                                        @update:model-value="setGrup(g, kode, $event)"
                                    />
                                </td>
                            </tr>
                            <tr v-for="m in g.menu" v-show="tampilMenu(m)" :key="m.slug">
                                <th scope="row" class="font-normal ps-5">{{ m.title }}</th>
                                <td v-for="(label, kode) in aksi" :key="kode" class="text-center">
                                    <Checkbox
                                        :model-value="form.hak[m.slug][kode]"
                                        :aria-label="`${label} ${m.title}`"
                                        @update:model-value="setAksi(m.slug, kode, $event)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>

                <div v-if="bisaUbah" class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-white px-3 py-2">
                    <span class="text-xs text-gray-500">{{ form.isDirty ? 'Ada perubahan belum disimpan.' : 'Belum ada perubahan.' }}</span>
                    <div class="flex gap-2">
                        <Button :tag="Link" variant="outline-secondary" href="/role">Batal</Button>
                        <Button type="submit" :disabled="form.processing">Simpan role</Button>
                    </div>
                </div>
            </fieldset>
        </form>

        <Modal v-if="bisaHapus" :show="modalHapus" :title="`Hapus role ${role.nama}?`" @close="modalHapus = false">
            {{ jumlahAkun ? `Role ini masih dipakai ${jumlahAkun} akun. Pindahkan dulu anggotanya ke role lain.` : 'Role tanpa anggota ini dihapus permanen.' }}
            <template #footer>
                <Button variant="light" @click="modalHapus = false">Batal</Button>
                <Button v-if="!jumlahAkun" variant="outline-danger" @click="hapusRole">Hapus role</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
