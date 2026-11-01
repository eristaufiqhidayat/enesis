<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Skeleton from '@/Components/ui/atoms/Skeleton.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import { tanggalWaktuPendek } from '@/Support/format.js';

// Tanda outlet (TandaOutletController): koordinator menandai outlet yang berpotensi curang (outlet fiktif, alamat
// tidak sesuai, dll.). Berbeda dari tanda motoris, tanda ini melekat ke OUTLET (bukan per kunjungan/tanggal), jadi
// komponen ini tidak punya dimensi tanggal atau daftar "tanda di tanggal lain" — isinya seluruh drawer/modal yang
// membukanya (dipasang di RuteHarianPeta.vue per baris kunjungan, dan di Menu/Show.vue untuk menu All Outlet).
const props = defineProps({
    url: { type: String, required: true },
    outletId: { type: Number, required: true },
    // Isian otomatis form dari indikasi jarak antar kunjungan (spec 054, { kategori, catatan }); null = form kosong.
    saran: { type: Object, default: null },
});

// 'berubah': dikirim hanya setelah tandai/cabut berhasil (bukan saat muat awal), supaya komponen induk tahu
// kapan perlu menyegarkan tampilannya sendiri (mis. muat ulang tabel outlet_all) tanpa melakukannya tiap drawer dibuka.
const emit = defineEmits(['berubah']);

const tanda = ref([]);
// Pendaftar outlet { nama, dibuat_oleh, dibuat_pada } dari master outlet; null sebelum dimuat.
const outlet = ref(null);
const kategori = ref({});
// Pilihan kategori; yang sudah punya tanda aktif diberi keterangan (server menolak tanda kembar).
const pilihanKategori = computed(() => Object.fromEntries(
    Object.entries(kategori.value).map(([kunci, label]) => [kunci, tanda.value.some((t) => t.kategori === kunci) ? `${label} (sudah ditandai)` : label]),
));
const bisaTambah = ref(false);
const memuat = ref(true);
const formBuka = ref(false);
const kategoriDipilih = ref('');
const catatan = ref('');
const menyimpan = ref(false);
// Galat validasi per kolom ({ kategori: ['...'] }) dan pesan umum untuk galat lain.
const galat = ref({});
const pesan = ref(null);

// "22 Okt 2026 · 11:23" bila formatnya Y-m-d H:i; selain itu apa adanya dari sumber.
function waktuPendaftaran(nilai) {
    const waktu = tanggalWaktuPendek(nilai);

    return waktu ? `${waktu.tanggal} · ${waktu.jam}` : nilai;
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function muat() {
    const outletId = props.outletId;
    memuat.value = true;
    pesan.value = null;

    try {
        const respon = await fetch(`${props.url}?${new URLSearchParams({ outlet_id: outletId })}`, { headers: { Accept: 'application/json' } });
        if (!respon.ok) throw new Error(`HTTP ${respon.status}`);
        const data = await respon.json();

        // Respons lama (outlet sudah diganti) dibuang.
        if (outletId !== props.outletId) return;
        tanda.value = data.tanda;
        outlet.value = data.outlet ?? null;
        kategori.value = data.kategori;
        bisaTambah.value = data.bisa_tambah;
    } catch {
        pesan.value = 'Gagal memuat tanda outlet.';
    } finally {
        memuat.value = false;
    }
}

function bukaForm() {
    kategoriDipilih.value = props.saran?.kategori ?? '';
    catatan.value = props.saran?.catatan ?? '';
    formBuka.value = true;
}

function batal() {
    formBuka.value = false;
    kategoriDipilih.value = '';
    catatan.value = '';
    galat.value = {};
}

async function simpan() {
    menyimpan.value = true;
    galat.value = {};
    pesan.value = null;

    try {
        const respon = await fetch(props.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ outlet_id: props.outletId, kategori: kategoriDipilih.value, catatan: catatan.value }),
        });

        if (respon.status === 422) {
            galat.value = (await respon.json()).errors ?? {};

            return;
        }
        if (!respon.ok) throw new Error(`HTTP ${respon.status}`);

        batal();
        await muat();
        emit('berubah', tanda.value.length);
    } catch {
        pesan.value = 'Gagal menyimpan tanda.';
    } finally {
        menyimpan.value = false;
    }
}

async function cabut(item) {
    if (!window.confirm(`Cabut tanda "${item.kategori_label}"?`)) return;

    pesan.value = null;

    try {
        const respon = await fetch(`${props.url}/${item.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        if (!respon.ok) throw new Error(`HTTP ${respon.status}`);

        await muat();
        emit('berubah', tanda.value.length);
    } catch {
        pesan.value = 'Gagal mencabut tanda.';
    }
}

onMounted(muat);
watch(() => props.outletId, () => {
    batal();
    outlet.value = null;
    tanda.value = [];
    muat();
});
</script>

<template>
    <section class="tanda-motoris" aria-label="Tanda outlet">
        <div class="flex flex-wrap items-center gap-2">
            <h6 class="font-semibold text-sm mb-0">Tanda outlet</h6>
            <Badge v-if="tanda.length" variant="danger">{{ tanda.length }}</Badge>
            <Button v-if="bisaTambah && !formBuka" variant="outline-danger" class="ml-auto" @click="bukaForm"><Icon name="flag" class="me-1" />{{ tanda.length ? 'Tambah tanda' : 'Tandai' }}</Button>
        </div>

        <!-- Kerangka dua baris (pendaftar outlet dan satu tanda) selama data awal diambil; muat ulang setelah simpan/cabut tidak mengosongkan isi. -->
        <div v-if="memuat && !outlet" class="animate-pulse space-y-2" role="status" aria-busy="true" aria-label="Memuat data outlet">
            <Skeleton class="h-3 w-3/4" />
            <Skeleton class="h-12 w-full" />
        </div>

        <p v-if="outlet" class="mb-0 text-xs text-gray-600">
            <span class="font-semibold">Didaftarkan oleh:</span> {{ outlet.dibuat_oleh || 'tidak diketahui' }}<template v-if="outlet.dibuat_pada"> · {{ waktuPendaftaran(outlet.dibuat_pada) }}</template>
        </p>

        <p v-if="pesan" class="mb-0 text-xs text-red-600">{{ pesan }}</p>

        <form v-if="formBuka" class="tanda-motoris-form" @submit.prevent="simpan">
            <p v-if="saran" class="mb-0 text-xs text-gray-500">Terisi dari indikasi jarak antar kunjungan; ubah bila perlu.</p>
            <div>
                <Label>Kategori</Label>
                <Select v-model="kategoriDipilih" :options="pilihanKategori" placeholder="Pilih kategori" aria-label="Kategori tanda" />
                <FieldError v-if="galat.kategori">{{ galat.kategori[0] }}</FieldError>
            </div>
            <div>
                <Label>Catatan (opsional)</Label>
                <Textarea v-model="catatan" maxlength="500" rows="3" aria-label="Catatan tanda" />
                <FieldError v-if="galat.catatan">{{ galat.catatan[0] }}</FieldError>
            </div>
            <div class="flex gap-2">
                <Button type="submit" variant="primary" :loading="menyimpan">Simpan tanda</Button>
                <Button variant="light" @click="batal">Batal</Button>
            </div>
        </form>

        <ul v-if="tanda.length" class="tanda-motoris-daftar">
            <li v-for="item in tanda" :key="item.id" class="tanda-motoris-item">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Badge variant="danger">{{ item.kategori_label }}</Badge>
                    <span class="text-xs text-gray-500">{{ item.oleh }} · {{ item.waktu }}</span>
                    <button v-if="item.bisa_cabut" type="button" class="tautan-depo ml-auto text-xs" @click="cabut(item)">Cabut</button>
                </div>
                <p v-if="item.catatan" class="mb-0 mt-1 text-sm">{{ item.catatan }}</p>
            </li>
        </ul>
        <p v-else-if="!memuat && !pesan" class="mb-0 text-xs text-gray-500">Belum ada tanda untuk outlet ini.</p>
    </section>
</template>
