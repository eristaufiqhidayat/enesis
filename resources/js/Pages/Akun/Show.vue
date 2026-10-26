<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import OutletSales from './partials/OutletSales.vue';
import SalesBawahan from './partials/SalesBawahan.vue';
import { angka } from '@/Support/format.js';

const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    akun: { type: Object, required: true },
    karyawan: { type: Object, default: null },
    area: { type: Number, default: 0 },
    kembar: { type: Array, default: () => [] },
    sesiAktif: { type: Object, default: null },
    sesi: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    alasanHapus: { type: String, default: null },
    alasanNonaktif: { type: Array, required: true },
    salesBawahan: { type: Array, default: null },
    outletSales: { type: Object, default: null },
    bisaUbah: { type: Boolean, default: false },
    bisaHapus: { type: Boolean, default: false },
    bisaLihatSales: { type: Boolean, default: false },
    bisaAturSales: { type: Boolean, default: false },
});

const page = usePage();
const appName = page.props.appName;
const base = `/${props.slug}/${props.akun.id}`;
const modal = ref(null);

// Dibuka dari menu aksi di daftar akun: /{menu}/{id}?buka=modal-password dst.
onMounted(() => {
    const buka = new URLSearchParams(window.location.search).get('buka')?.replace('modal-', '');
    const boleh = { password: props.bisaUbah, sesi: props.bisaUbah && !!props.sesiAktif, status: props.bisaUbah, hapus: props.bisaHapus };
    if (buka && boleh[buka]) modal.value = buka;
});

const alasanStatus = computed(() => (props.akun.aktif
    ? props.alasanNonaktif
    : ['Kembali bekerja', 'Salah nonaktifkan', 'Lainnya']));
const inisial = (props.akun.karyawan ?? props.akun.username).charAt(0).toUpperCase();
const formStatus = useForm({ aktif: !props.akun.aktif, alasan: alasanStatus.value[0] });
const formHapus = useForm({ konfirmasi: '' });

const tutup = () => {
    modal.value = null;
};

function buatPassword() {
    router.post(`${base}/password`, {}, { onFinish: tutup });
}

function cabutSesi() {
    router.post(`${base}/cabut-sesi`, {}, { preserveScroll: true, onFinish: tutup });
}

function ubahStatus() {
    formStatus.post(`${base}/status`, { preserveScroll: true, onSuccess: tutup });
}

function hapusAkun() {
    formHapus.delete(`/${props.slug}/${props.akun.id}`);
}

function pakai() {
    router.post(`${base}/pakai`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${akun.username} · ${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <Card class="mb-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-lg font-bold text-blue-800">{{ inisial }}</span>
                    <div>
                        <h5 class="mb-0 font-semibold">{{ akun.karyawan ?? akun.name }}</h5>
                        <div class="text-gray-500">
                            {{ `@${akun.username}` }} · {{ judul }} · role {{ akun.role ?? '-' }}{{ akun.sumberJenis ? ' · dari aplikasi lama' : '' }}
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <Badge :variant="akun.aktif ? 'success' : 'secondary'">{{ akun.aktif ? 'Aktif' : 'Tidak aktif' }}</Badge>
                            <Badge v-if="akun.keterangan" variant="warning">{{ akun.keterangan }}</Badge>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template v-if="bisaUbah">
                        <Button :tag="Link" variant="outline-secondary" :href="`${base}/edit`"><Icon name="pen" class="me-1" /> Edit</Button>
                        <Button variant="outline-secondary" @click="modal = 'password'"><Icon name="key" class="me-1" /> Atur password</Button>
                        <Button :variant="akun.aktif ? 'outline-warning' : 'success'" @click="modal = 'status'">{{ akun.aktif ? 'Nonaktifkan' : 'Aktifkan' }}</Button>
                    </template>
                    <Button v-if="bisaHapus" variant="outline-danger" @click="modal = 'hapus'"><Icon name="trash" class="me-1" /> Hapus</Button>
                </div>
            </div>
        </Card>

        <Alert v-if="akun.perluKeputusan" variant="danger" class="mb-3">
            <strong>Perlu keputusan admin.</strong>
            <template v-if="kembar.length">
                Karyawan ini punya akun lain:
                <template v-for="(k, i) in kembar" :key="k.id">
                    <Link :href="`/${k.menu}/${k.id}`">{{ k.username }}</Link>{{ k.aktif ? ' (aktif)' : ' (nonaktif)' }}{{ i === kembar.length - 1 ? '.' : ',' }}
                </template>
                Pilih akun yang dipakai; akun aktif lain milik karyawan yang sama akan dinonaktifkan.
            </template>
            <template v-else>{{ akun.catatanMigrasi || 'Periksa akun ini lalu tandai sudah diputuskan.' }}</template>
            <form v-if="bisaUbah" class="mt-2" @submit.prevent="pakai">
                <Button type="submit">{{ kembar.some((k) => k.aktif) ? 'Pakai akun ini' : 'Tandai sudah diputuskan' }}</Button>
            </form>
        </Alert>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
            <Card>
                <template #header><h6 class="mb-0 font-semibold">Data karyawan</h6></template>
                <dl v-if="karyawan" class="grid grid-cols-2 gap-3 mb-0">
                    <div><dt class="text-xs text-gray-500">ID karyawan</dt><dd class="font-semibold mb-0">{{ karyawan.id }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Status karyawan</dt><dd class="font-semibold mb-0">{{ karyawan.aktif === null ? '-' : (karyawan.aktif ? 'Aktif' : 'Tidak aktif') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Depo</dt><dd class="font-semibold mb-0">{{ akun.depo ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Koordinator</dt><dd class="font-semibold mb-0">{{ akun.koordinator ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Area rute aktif</dt><dd class="font-semibold mb-0">{{ area }} area</dd></div>
                    <div><dt class="text-xs text-gray-500">Login terakhir</dt><dd class="font-semibold mb-0">{{ akun.loginTerakhir ?? '—' }}</dd></div>
                </dl>
                <p v-else class="text-gray-500 mb-0">
                    Akun ini belum terhubung ke data karyawan.<template v-if="bisaUbah"> <Link :href="`${base}/edit`">Hubungkan</Link>.</template>
                </p>
            </Card>

            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <h6 class="mb-0 font-semibold">Sesi HP aktif</h6>
                        <Button v-if="sesiAktif && bisaUbah" variant="outline-danger" @click="modal = 'sesi'">Cabut sesi</Button>
                    </div>
                </template>
                <div v-if="sesiAktif" class="flex items-center gap-3 rounded-md bg-gray-50 p-3 mb-2">
                    <Icon name="smartphone" class="text-2xl text-blue-600" />
                    <div>
                        <strong>{{ sesiAktif.device || 'HP' }}</strong>
                        <div class="text-gray-500">Aplikasi v{{ sesiAktif.versi ?? '?' }} · login {{ sesiAktif.login ?? '—' }} · dipakai {{ sesiAktif.terakhir ?? '—' }}</div>
                    </div>
                </div>
                <p v-else class="text-gray-500">Tidak ada HP yang sedang login dengan akun ini.</p>
                <p class="text-xs text-gray-500 mb-0">Satu akun hanya aktif di satu HP. Cabut sesi bila HP hilang atau berganti; data yang belum terkirim di HP lama ikut hilang.</p>
            </Card>
        </div>

        <SalesBawahan
            v-if="salesBawahan !== null"
            :akun="akun"
            :sales-bawahan="salesBawahan"
            :bisa-lihat-sales="bisaLihatSales"
            :bisa-atur-sales="bisaAturSales"
        />
        <OutletSales v-if="outletSales !== null" :outlet-sales="outletSales" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <Card body-class="overflow-x-auto">
                <template #header><h6 class="mb-0 font-semibold">Riwayat sesi HP</h6></template>
                <table class="data-table">
                    <thead><tr><th scope="col">HP</th><th scope="col">Versi</th><th scope="col">Login</th><th scope="col">Terakhir dipakai</th><th scope="col">Berakhir</th></tr></thead>
                    <tbody>
                        <tr v-for="(s, i) in sesi" :key="i">
                            <td>{{ s.device || 'HP' }}</td>
                            <td>{{ s.versi ?? '-' }}</td>
                            <td>{{ s.login ?? '—' }}</td>
                            <td>{{ s.terakhir ?? '—' }}</td>
                            <td>
                                <template v-if="s.berakhir">{{ s.berakhir }}</template>
                                <Badge v-else variant="success">Masih aktif</Badge>
                            </td>
                        </tr>
                        <tr v-if="!sesi.length">
                            <td colspan="5" class="text-center text-gray-500 py-3">Belum pernah login di aplikasi HP.</td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Card>
                <template #header><h6 class="mb-0 font-semibold">Riwayat perubahan akun</h6></template>
                <ol class="list-none m-0 p-0 flex flex-col gap-2">
                    <li v-for="(r, i) in riwayat" :key="i">
                        <div>{{ r.keterangan }}</div>
                        <div class="text-xs text-gray-500">{{ r.aktor ?? 'sistem' }} · {{ r.waktu ?? '—' }}</div>
                    </li>
                    <li v-if="!riwayat.length" class="text-gray-500">Belum ada perubahan oleh admin.</li>
                </ol>
            </Card>
        </div>

        <template v-if="bisaUbah">
            <Modal :show="modal === 'password'" :title="`Atur password awal ${akun.username}?`" @close="tutup">
                Password lama tidak berlaku lagi. Password awal acak baru ditampilkan <strong>sekali</strong>, dan pengguna wajib menggantinya saat login berikutnya (web maupun HP).
                <template #footer>
                    <Button variant="light" @click="tutup">Batal</Button>
                    <Button @click="buatPassword">Buat password awal</Button>
                </template>
            </Modal>

            <Modal :show="modal === 'sesi'" :title="`Cabut sesi HP ${akun.username}?`" @close="tutup">
                HP {{ sesiAktif?.device ?? '' }} langsung keluar dan harus login ulang. Data di antrian HP yang belum terkirim ikut hilang.
                <template #footer>
                    <Button variant="light" @click="tutup">Batal</Button>
                    <Button variant="outline-danger" @click="cabutSesi">Cabut sesi</Button>
                </template>
            </Modal>

            <Modal :show="modal === 'status'" :title="`${akun.aktif ? 'Nonaktifkan' : 'Aktifkan'} akun ${akun.username}?`" @close="tutup">
                <form id="form-status" @submit.prevent="ubahStatus">
                    <ul v-if="akun.aktif" class="list-disc ps-4 mb-3">
                        <li>Tidak bisa login di web maupun aplikasi HP.</li>
                        <li>Sesi HP aktif langsung dicabut.</li>
                        <li>Data transaksi tetap ada; akun bisa diaktifkan lagi.</li>
                    </ul>
                    <p v-else>Akun bisa login lagi dengan password terakhirnya.</p>
                    <Label for="alasan">Alasan</Label>
                    <Select id="alasan" v-model="formStatus.alasan">
                        <option v-for="a in alasanStatus" :key="a" :value="a">{{ a }}</option>
                    </Select>
                    <FieldError v-if="formStatus.errors.status">{{ formStatus.errors.status }}</FieldError>
                </form>
                <template #footer>
                    <Button variant="light" @click="tutup">Batal</Button>
                    <Button type="submit" form="form-status" :variant="akun.aktif ? 'outline-warning' : 'success'" :disabled="formStatus.processing">{{ akun.aktif ? 'Nonaktifkan' : 'Aktifkan' }}</Button>
                </template>
            </Modal>
        </template>

        <Modal v-if="bisaHapus" :show="modal === 'hapus'" :title="`Hapus akun ${akun.username}?`" @close="tutup">
            <p>Hanya untuk akun yang <strong>belum pernah login</strong> dan dibuat di Enesis. Akun yang sudah dipakai cukup dinonaktifkan supaya riwayatnya tetap utuh.</p>
            <Alert v-if="alasanHapus" variant="danger">{{ alasanHapus }}</Alert>
            <template v-else>
                <Label for="konfirmasi">Ketik <code>{{ akun.username }}</code> untuk konfirmasi</Label>
                <Input id="konfirmasi" v-model="formHapus.konfirmasi" autocomplete="off" />
                <FieldError v-if="formHapus.errors.hapus">{{ formHapus.errors.hapus }}</FieldError>
            </template>
            <template #footer>
                <Button variant="light" @click="tutup">Batal</Button>
                <Button v-if="!alasanHapus" variant="outline-danger" :disabled="formHapus.processing" @click="hapusAkun">Hapus permanen</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
