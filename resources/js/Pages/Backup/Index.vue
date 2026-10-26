<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Deferred, Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import TableSkeleton from '@/Components/ui/molecules/TableSkeleton.vue';
import GrupTabel from './partials/GrupTabel.vue';
import RiwayatBackup from './partials/RiwayatBackup.vue';

// `grup` kosong selama masih ditunda (daftar tabel + perkiraan baris dibaca dari database).
const props = defineProps({
    slug: { type: String, required: true },
    judul: { type: String, required: true },
    grup: { type: Array, default: () => [] },
    riwayat: { type: Array, required: true },
    masaSimpan: { type: Number, required: true },
    hariIni: { type: String, required: true },
    bolehBuat: { type: Boolean, default: false },
    bolehHapus: { type: Boolean, default: false },
});

const appName = usePage().props.appName;

const form = useForm({
    format: 'sql',
    tabel: [],
    header: true,
    pemisah: 'koma',
    struktur: true,
    drop: false,
    per_tabel: false,
    rahasia: true,
    dari: null,
    sampai: null,
});

// Bawaan: tabel transaksi & master motoris tercentang setelah daftar tabel termuat.
watch(() => props.grup, (grup) => {
    if (grup.length && !form.tabel.length) {
        form.tabel = grup.filter((g) => g.id === 'trx' || g.id === 'mk').flatMap((g) => g.tabel.map((t) => t.nama));
    }
}, { immediate: true });

const buka = ref({ trx: true, mk: true });
const cari = ref('');
const batasiTanggal = ref(false);
const dari = ref(props.hariIni.slice(0, 8) + '01');
const sampai = ref(props.hariIni);

const semuaTabel = computed(() => props.grup.flatMap((g) => g.tabel));
const kata = computed(() => cari.value.trim().toLowerCase());
const grupTampil = computed(() => props.grup
    .map((g) => ({ grup: g, tabel: g.tabel.filter((t) => !kata.value || t.nama.includes(kata.value)) }))
    .filter((g) => !kata.value || g.tabel.length));
const jumlahPribadi = computed(() => semuaTabel.value.filter((t) => t.pribadi && form.tabel.includes(t.nama)).length);
const isCsv = computed(() => form.format === 'csv');

const labelFormat = computed(() => {
    if (isCsv.value) return 'CSV, satu file per tabel (ZIP)';
    return form.per_tabel ? 'SQL, satu file per tabel (ZIP)' : 'SQL, satu file (.sql.gz)';
});
const contohFile = computed(() => {
    const n = form.tabel.length;
    if (!n) return 'belum ada tabel dipilih';
    if (!isCsv.value && !form.per_tabel) return `${n} tabel dalam satu file`;
    const ext = isCsv.value ? '.csv' : '.sql';
    const contoh = form.tabel.slice(0, 3).map((t) => t + ext).join(', ');
    return n > 3 ? `${contoh} + ${n - 3} file lain` : contoh;
});

const sedangDiproses = computed(() => props.riwayat.some((r) => r.status === 'menunggu' || r.status === 'proses'));
const terbaru = computed(() => props.riwayat.find((r) => r.status === 'menunggu' || r.status === 'proses') ?? null);
const persen = computed(() => terbaru.value && terbaru.value.jumlahTabel
    ? Math.round((terbaru.value.tabelSelesai / terbaru.value.jumlahTabel) * 100) : 0);

function terbukaGrup(id) {
    return kata.value ? true : !!buka.value[id];
}

function pilihSemua(nilai) {
    form.tabel = nilai ? semuaTabel.value.map((t) => t.nama) : [];
}

function buat() {
    form.transform((d) => ({ ...d, dari: batasiTanggal.value ? dari.value : null, sampai: batasiTanggal.value ? sampai.value : null }))
        .post('/backup_data', { preserveScroll: true });
}

// Selama ada backup yang diproses, riwayat diperbarui tiap 2 detik.
let timer = null;
watch(sedangDiproses, (aktif) => {
    clearInterval(timer);
    timer = aktif ? setInterval(() => router.reload({ only: ['riwayat'] }), 2000) : null;
}, { immediate: true });
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head :title="`${judul} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="judul" subtitle="Unduh salinan tabel database per tabel, dalam bentuk CSV atau SQL." />

        <div class="flex flex-wrap items-start gap-3 mb-3">
            <Card class="min-w-0" style="flex: 999 1 560px">
                <h6 class="mb-2 font-semibold">1. Pilih format</h6>
                <div class="grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
                    <button type="button" class="pilihan-format" :class="{ active: isCsv }" :aria-pressed="isCsv ? 'true' : 'false'" @click="form.format = 'csv'">
                        <span class="pilihan-format-judul"><Icon name="file-text" class="h-5 w-5" /> CSV</span>
                        <span class="pilihan-format-ket">Satu file .csv per tabel, dibungkus ZIP. Bisa dibuka di Excel dan dimuat ulang dengan <code>marketama:import</code>.</span>
                    </button>
                    <button type="button" class="pilihan-format" :class="{ active: !isCsv }" :aria-pressed="!isCsv ? 'true' : 'false'" @click="form.format = 'sql'">
                        <span class="pilihan-format-judul"><Icon name="database" class="h-5 w-5" /> SQL</span>
                        <span class="pilihan-format-ket">Perintah CREATE TABLE + INSERT. Untuk memulihkan tabel ke database yang sama atau server baru.</span>
                    </button>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h6 class="mb-0 font-semibold">2. Pilih tabel <span class="font-normal text-gray-500">({{ form.tabel.length }} dari {{ semuaTabel.length }})</span></h6>
                    <div class="flex flex-wrap items-center gap-2">
                        <Input v-model="cari" type="search" placeholder="Cari nama tabel…" aria-label="Cari nama tabel" class="w-56" />
                        <Button variant="outline-primary" @click="pilihSemua(true)">Pilih semua</Button>
                        <Button variant="outline-secondary" @click="pilihSemua(false)">Kosongkan</Button>
                    </div>
                </div>
                <FieldError v-if="form.errors.tabel">{{ form.errors.tabel }}</FieldError>

                <Deferred data="grup">
                    <template #fallback><TableSkeleton /></template>

                    <div class="flex flex-col gap-2">
                        <GrupTabel
                            v-for="g in grupTampil"
                            :key="g.grup.id"
                            v-model:pilih="form.tabel"
                            :grup="g.grup"
                            :tabel="g.tabel"
                            :terbuka="terbukaGrup(g.grup.id)"
                            @toggle="buka[g.grup.id] = !buka[g.grup.id]"
                        />
                        <p v-if="kata && !grupTampil.length" class="py-4 text-center text-sm text-gray-500">Tidak ada tabel yang cocok dengan “{{ cari }}”.</p>
                    </div>
                </Deferred>
                <p class="mt-2 mb-0 text-xs text-gray-500">Tabel sistem Laravel (cache, sesi, antrean job) tidak ikut. Jumlah baris adalah perkiraan database.</p>
            </Card>

            <div class="flex min-w-0 flex-col gap-3" style="flex: 1 1 320px">
                <Card>
                    <h6 class="mb-3 font-semibold">3. Opsi &amp; ringkasan</h6>
                    <dl class="grid gap-x-3 gap-y-1 text-sm mb-3" style="grid-template-columns: auto 1fr">
                        <dt class="text-gray-500">Format</dt><dd class="text-end font-semibold">{{ labelFormat }}</dd>
                        <dt class="text-gray-500">Tabel dipilih</dt><dd class="text-end font-semibold">{{ form.tabel.length }} tabel</dd>
                        <dt class="text-gray-500">Data pribadi</dt><dd class="text-end"><Badge variant="warning">{{ jumlahPribadi }} tabel</Badge></dd>
                    </dl>

                    <div class="flex flex-col gap-2 border-t border-gray-200 pt-3 mb-3 text-sm">
                        <template v-if="isCsv">
                            <label class="flex items-center gap-2"><Checkbox v-model="form.header" /> Baris pertama berisi nama kolom</label>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span id="label-pemisah">Pemisah kolom</span>
                                <div class="flex gap-1" role="group" aria-labelledby="label-pemisah">
                                    <button type="button" class="tab-pill" :class="{ active: form.pemisah === 'koma' }" :aria-pressed="form.pemisah === 'koma' ? 'true' : 'false'" @click="form.pemisah = 'koma'">Koma ( , )</button>
                                    <button type="button" class="tab-pill" :class="{ active: form.pemisah === 'titikkoma' }" :aria-pressed="form.pemisah === 'titikkoma' ? 'true' : 'false'" @click="form.pemisah = 'titikkoma'">Titik koma ( ; )</button>
                                </div>
                            </div>
                            <p class="mb-0 text-xs text-gray-500">Encoding UTF-8 dengan BOM supaya huruf terbaca benar di Excel.</p>
                        </template>
                        <template v-else>
                            <label class="flex items-center gap-2"><Checkbox v-model="form.struktur" /> Sertakan struktur tabel (CREATE TABLE)</label>
                            <label class="flex items-center gap-2"><Checkbox v-model="form.drop" /> Tambahkan DROP TABLE IF EXISTS</label>
                            <label class="flex items-center gap-2"><Checkbox v-model="form.per_tabel" /> Satu file .sql per tabel</label>
                            <p class="mb-0 text-xs text-gray-500">Sintaks mengikuti database server ini.</p>
                        </template>
                        <label class="flex items-center gap-2"><Checkbox v-model="form.rahasia" /> Kosongkan kolom rahasia (password, token)</label>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-gray-200 pt-3 mb-3 text-sm">
                        <label class="flex items-center gap-2"><Checkbox v-model="batasiTanggal" /> Batasi tanggal tabel transaksi (trx_*, lap_*)</label>
                        <div v-if="batasiTanggal" class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="backup-dari">Dari</Label>
                                <Input id="backup-dari" v-model="dari" type="date" />
                            </div>
                            <div>
                                <Label for="backup-sampai">Sampai</Label>
                                <Input id="backup-sampai" v-model="sampai" type="date" />
                            </div>
                        </div>
                        <FieldError v-if="form.errors.sampai">{{ form.errors.sampai }}</FieldError>
                    </div>

                    <div class="kotak-abu mb-3 text-xs">
                        <span class="text-gray-500">Isi file:</span> {{ contohFile }}
                    </div>

                    <Button v-if="bolehBuat" class="w-full" :loading="form.processing" :disabled="!form.tabel.length || sedangDiproses" @click="buat">
                        <Icon name="download" class="h-4 w-4" /> Buat backup {{ isCsv ? 'CSV' : 'SQL' }} ({{ form.tabel.length }} tabel)
                    </Button>
                    <Alert v-else variant="info">Akun Anda tidak punya hak <b>Ekspor</b> di menu ini, jadi hanya bisa melihat riwayat.</Alert>

                    <div v-if="terbaru" class="mt-3" role="status">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>{{ terbaru.status === 'menunggu' ? 'Menunggu giliran…' : `Memproses ${terbaru.tabelSekarang ?? ''}` }}</span>
                            <span>{{ terbaru.tabelSelesai }} / {{ terbaru.jumlahTabel }}</span>
                        </div>
                        <div class="progress"><div class="progress-bar" :style="{ width: `${persen}%` }"></div></div>
                    </div>

                    <p class="mt-3 mb-0 text-xs text-gray-500">Proses berjalan di latar belakang; tabel besar bisa beberapa menit. File disimpan {{ masaSimpan }} hari lalu dihapus otomatis.</p>
                </Card>

                <Alert variant="warning" class="text-xs">
                    Backup berisi data pribadi karyawan, outlet dan lokasi GPS. Hanya akun dengan hak <b>Ekspor</b> di menu Backup Data yang bisa membuat dan mengunduhnya; setiap backup dan unduhan tercatat di riwayat admin.
                </Alert>
            </div>
        </div>

        <RiwayatBackup :riwayat="riwayat" :boleh-unduh="bolehBuat" :boleh-hapus="bolehHapus" />
    </AppLayout>
</template>
