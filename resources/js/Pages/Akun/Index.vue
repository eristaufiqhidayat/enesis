<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import Pagination from '@/Components/ui/molecules/Pagination.vue';
import { useAmbilData } from '@/Support/ambilData.js';
import { angka } from '@/Support/format.js';

const props = defineProps({
    menu: { type: String, required: true },
    judul: { type: String, required: true },
    akun: { type: Object, required: true },
    jumlah: { type: Object, required: true },
    statusOpsi: { type: Object, required: true },
    status: { type: String, required: true },
    q: { type: String, default: '' },
    depo: { default: null },
    koordinator: { default: null },
    daftarDepo: { type: Object, required: true },
    daftarKoordinator: { type: Object, required: true },
    bisaTambah: { type: Boolean, default: false },
    bisaUbah: { type: Boolean, default: false },
    bisaHapus: { type: Boolean, default: false },
    bisaLihatSales: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const base = `/${props.menu}`;
const tampilKoordinator = computed(() => props.menu === 'user_koordinator');
const tampilFilterKoordinator = computed(() => props.menu === 'user_sales' && Object.keys(props.daftarKoordinator).length > 0);
const jumlahKolom = computed(() => (tampilKoordinator.value ? 9 : 8));

const cari = ref({ q: props.q, depo: props.depo ?? '', koordinator: props.koordinator ?? '' });
const aksiTerbuka = ref(null);

const bersih = (data) => Object.fromEntries(Object.entries(data).filter(([, v]) => v !== null && v !== undefined && v !== ''));

function tautan(ganti = {}) {
    const query = new URLSearchParams(bersih({
        status: props.status !== 'semua' ? props.status : null,
        q: props.q,
        depo: props.depo,
        koordinator: props.koordinator,
        ...ganti,
    })).toString();

    return query ? `${base}?${query}` : base;
}

// Tombol Cari aktif lagi hanya saat isian berbeda dari pencarian yang sedang tampil.
const { memuat, berubah, ambil } = useAmbilData({
    form: () => ({ q: cari.value.q.trim(), depo: cari.value.depo, koordinator: tampilFilterKoordinator.value ? cari.value.koordinator : '' }),
    diterapkan: () => ({ q: props.q, depo: props.depo, koordinator: tampilFilterKoordinator.value ? props.koordinator : '' }),
});

function kirimCari() {
    ambil(base, bersih({
        status: props.status !== 'semua' ? props.status : null,
        q: cari.value.q.trim(),
        depo: cari.value.depo,
        koordinator: tampilFilterKoordinator.value ? cari.value.koordinator : '',
    }), { preserveState: true, replace: true });
}

function toggleAksi(id) {
    aksiTerbuka.value = aksiTerbuka.value === id ? null : id;
}
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="menu">
        <PageHeader :title="judul" :subtitle="`Akun login web & aplikasi HP · ${angka(jumlah.semua)} akun · ${angka(jumlah.aktif)} aktif`">
            <template #actions>
                <Button v-if="bisaTambah" :tag="Link" :href="`${base}/tambah`"><Icon name="plus" class="me-1" /> Tambah akun</Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Saring status">
            <Button
                v-for="(label, kode) in statusOpsi"
                :key="kode"
                :tag="Link"
                :variant="status === kode ? 'primary' : 'outline-secondary'"
                :href="tautan({ status: kode === 'semua' ? null : kode })"
                :aria-current="status === kode ? 'true' : null"
            >
                {{ label }} <span class="ms-1 opacity-75">{{ angka(jumlah[kode]) }}</span>
            </Button>
        </div>

        <Card body-class="overflow-x-auto">
            <template #header>
                <form class="flex flex-wrap items-end gap-2" role="search" @submit.prevent="kirimCari">
                    <div class="grow min-w-56">
                        <Label for="q" class="sr-only">Cari akun</Label>
                        <Input id="q" v-model="cari.q" type="search" placeholder="Cari username, nama karyawan, depo, koordinator…" />
                    </div>
                    <div>
                        <Label for="depo" class="sr-only">Depo</Label>
                        <Select id="depo" v-model="cari.depo">
                            <option value="">Semua depo</option>
                            <option v-for="(nama, id) in daftarDepo" :key="id" :value="id">{{ nama }}</option>
                        </Select>
                    </div>
                    <div v-if="tampilFilterKoordinator">
                        <Label for="koordinator" class="sr-only">Koordinator</Label>
                        <Select id="koordinator" v-model="cari.koordinator">
                            <option value="">Semua koordinator</option>
                            <option v-for="(nama, id) in daftarKoordinator" :key="id" :value="id">{{ nama }}</option>
                        </Select>
                    </div>
                    <Button type="submit" :loading="memuat" :disabled="!berubah">Cari</Button>
                </form>
            </template>

            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Karyawan</th>
                        <th scope="col">Depo · Koordinator</th>
                        <th v-if="tampilKoordinator" scope="col" class="text-end">Sales aktif</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">HP aktif</th>
                        <th scope="col">Login terakhir</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in akun.data" :key="a.id">
                        <td>
                            <Link :href="`${base}/${a.id}`" class="font-semibold">{{ a.username }}</Link>
                            <div v-if="a.nickname" class="text-gray-500">{{ a.nickname }}</div>
                        </td>
                        <td>{{ a.karyawan ?? '-' }}</td>
                        <td>
                            {{ a.depo ?? '-' }}
                            <div v-if="a.koordinator" class="text-gray-500">Koor: {{ a.koordinator }}</div>
                        </td>
                        <td v-if="tampilKoordinator" class="text-end">
                            <template v-if="a.karyawanId && bisaLihatSales">
                                <Link :href="`/user_sales?koordinator=${a.karyawanId}`" :title="`Lihat sales di bawah ${a.karyawan ?? a.username}`">{{ angka(a.jumlahSales) }}</Link>
                            </template>
                            <template v-else>{{ a.karyawanId ? angka(a.jumlahSales) : '—' }}</template>
                        </td>
                        <td>{{ a.role ?? '-' }}</td>
                        <td>
                            <Badge :variant="a.aktif ? 'success' : 'secondary'">{{ a.aktif ? 'Aktif' : 'Tidak aktif' }}</Badge>
                            <div v-if="a.keterangan" class="text-amber-700">{{ a.keterangan }}</div>
                        </td>
                        <td>{{ a.hp ? `${a.hp}${a.hpVersi ? ` · v${a.hpVersi}` : ''}` : '—' }}</td>
                        <td>{{ a.loginTerakhir ?? '—' }}</td>
                        <td class="text-end whitespace-nowrap">
                            <Button v-if="bisaUbah" :tag="Link" variant="light" :href="`${base}/${a.id}/edit`" :aria-label="`Edit ${a.username}`" title="Edit"><Icon name="pen" /></Button>
                            <span class="relative inline-block">
                                <Button variant="light" :aria-label="`Aksi lain untuk ${a.username}`" title="Aksi lain" @click="toggleAksi(a.id)"><Icon name="ellipsis" /></Button>
                                <div v-if="aksiTerbuka === a.id" class="dropdown-menu text-start">
                                    <Link class="block py-1" :href="`${base}/${a.id}`">Lihat detail &amp; sesi HP</Link>
                                    <template v-if="bisaUbah">
                                        <Link class="block py-1" :href="`${base}/${a.id}?buka=modal-password`">Atur password awal</Link>
                                        <Link v-if="a.hp" class="block py-1" :href="`${base}/${a.id}?buka=modal-sesi`">Cabut sesi HP</Link>
                                        <Link class="block py-1" :href="`${base}/${a.id}?buka=modal-status`">{{ a.aktif ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</Link>
                                    </template>
                                    <Link v-if="bisaHapus" class="block py-1 text-red-600" :href="`${base}/${a.id}?buka=modal-hapus`">Hapus akun</Link>
                                </div>
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!akun.data.length">
                        <td :colspan="jumlahKolom" class="text-center text-gray-500 py-4">Tidak ada akun.</td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <small class="text-gray-500">Menampilkan {{ akun.from ?? 0 }}–{{ akun.to ?? 0 }} dari {{ angka(akun.total) }}</small>
                    <Pagination :links="akun.links" />
                </div>
            </template>
        </Card>
    </AppLayout>
</template>
