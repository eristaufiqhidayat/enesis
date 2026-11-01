<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import { tanggalSingkat } from '@/Support/format.js';

// Tanda motoris di drawer Riwayat Lacak Motoris (TandaMotorisController): koordinator menandai motoris yang berpotensi
// curang (penjualan fiktif, lokasi palsu, dll.) pada tanggal yang sedang dilihat. Daftar memuat tanda aktif pada tanggal
// itu; tombol Tandai hanya tampil bila akun punya hak Tambah, dan Cabut hanya pada tanda yang boleh dicabutnya.
const props = defineProps({
    url: { type: String, required: true },
    salesId: { type: Number, required: true },
    tanggal: { type: String, required: true },
    // Isian otomatis form dari peringatan GPS tanggal ini ({ kategori, catatan }); null = form kosong.
    saran: { type: Object, default: null },
});

defineEmits(['pilih-tanggal']);

const tanda = ref([]);
// Tanda aktif motoris yang sama pada tanggal lain (jendela hari terakhir), dan apakah daftarnya sedang dibuka.
const lainnya = ref([]);
const lainBuka = ref(false);
const kategori = ref({});
const bisaTambah = ref(false);
const memuat = ref(true);
const formBuka = ref(false);
const kategoriDipilih = ref('');
const catatan = ref('');
const menyimpan = ref(false);
// Galat validasi per kolom ({ kategori: ['...'] }) dan pesan umum untuk galat lain.
const galat = ref({});
const pesan = ref(null);
// Id tanda yang catatannya sedang ditampilkan (diklik dari daftar); null = semua tertutup.
const terbukaId = ref(null);
// Daftar tanda ditampilkan sebagai panel melayang (absolute) saat counter diklik, supaya tidak mendorong konten di bawahnya.
const daftarBuka = ref(false);
const wadah = ref(null);

function tutupDiLuar(e) {
    if (wadah.value && !wadah.value.contains(e.target)) {
        daftarBuka.value = false;
        lainBuka.value = false;
    }
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function muat() {
    const tanggal = props.tanggal;
    memuat.value = true;
    pesan.value = null;

    try {
        const respon = await fetch(`${props.url}?${new URLSearchParams({ sales_id: props.salesId, tanggal })}`, { headers: { Accept: 'application/json' } });
        if (!respon.ok) throw new Error(`HTTP ${respon.status}`);
        const data = await respon.json();

        // Respons lama (tanggal sudah diganti) dibuang.
        if (tanggal !== props.tanggal) return;
        tanda.value = data.tanda;
        lainnya.value = data.lainnya ?? [];
        kategori.value = data.kategori;
        bisaTambah.value = data.bisa_tambah;
    } catch {
        pesan.value = 'Gagal memuat tanda motoris.';
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
            body: JSON.stringify({ sales_id: props.salesId, tanggal: props.tanggal, kategori: kategoriDipilih.value, catatan: catatan.value }),
        });

        if (respon.status === 422) {
            galat.value = (await respon.json()).errors ?? {};

            return;
        }
        if (!respon.ok) throw new Error(`HTTP ${respon.status}`);

        batal();
        await muat();
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
    } catch {
        pesan.value = 'Gagal mencabut tanda.';
    }
}

onMounted(muat);
onMounted(() => document.addEventListener('click', tutupDiLuar));
onBeforeUnmount(() => document.removeEventListener('click', tutupDiLuar));
// Tanggal drawer diganti: muat tanda tanggal itu, tutup form & daftar yang belum disimpan.
watch(() => props.tanggal, () => {
    batal();
    daftarBuka.value = false;
    lainBuka.value = false;
    muat();
});

</script>

<template>
    <section ref="wadah" class="tanda-motoris" aria-label="Tanda motoris">
        <div class="flex flex-wrap items-center gap-2">
            <h6 class="font-semibold text-sm mb-0">Tanda motoris</h6>
            <button v-if="tanda.length" type="button" class="cursor-pointer" :aria-expanded="daftarBuka" title="Tanda pada tanggal ini" @click="lainBuka = false; daftarBuka = !daftarBuka">
                <Badge variant="danger">{{ tanda.length }}</Badge>
            </button>
            <button v-if="lainnya.length" type="button" class="cursor-pointer" :aria-expanded="lainBuka" title="Tanda di tanggal lain" @click="daftarBuka = false; lainBuka = !lainBuka">
                <Badge variant="warning">{{ lainnya.length }}</Badge>
            </button>
            <Button v-if="bisaTambah && !formBuka" variant="outline-danger" class="ml-auto" @click="bukaForm"><Icon name="flag" class="me-1" />Tandai</Button>
        </div>

        <p v-if="pesan" class="mb-0 text-xs text-red-600">{{ pesan }}</p>

        <form v-if="formBuka" class="tanda-motoris-form" @submit.prevent="simpan">
            <p v-if="saran" class="mb-0 text-xs text-gray-500">Terisi dari peringatan GPS tanggal ini; ubah bila perlu.</p>
            <div>
                <Label>Kategori</Label>
                <Select v-model="kategoriDipilih" :options="kategori" placeholder="Pilih kategori" aria-label="Kategori tanda" />
                <FieldError v-if="galat.kategori">{{ galat.kategori[0] }}</FieldError>
            </div>
            <div>
                <Label>Catatan (opsional)</Label>
                <Textarea v-model="catatan" maxlength="500" rows="3" aria-label="Catatan tanda" />
                <FieldError v-if="galat.catatan">{{ galat.catatan[0] }}</FieldError>
            </div>
            <FieldError v-if="galat.tanggal">{{ galat.tanggal[0] }}</FieldError>
            <div class="flex gap-2">
                <Button type="submit" variant="primary" :loading="menyimpan">Simpan tanda</Button>
                <Button variant="light" @click="batal">Batal</Button>
            </div>
        </form>

        <div v-if="daftarBuka" class="tanda-motoris-panel">
            <ul v-if="tanda.length" class="tanda-motoris-daftar">
                <li v-for="item in tanda" :key="item.id" class="tanda-motoris-item">
                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        :class="{ 'cursor-pointer': item.catatan }"
                        @click="item.catatan && (terbukaId = terbukaId === item.id ? null : item.id)"
                    >
                        <Badge variant="danger">{{ item.kategori_label }}</Badge>
                        <span class="text-xs text-gray-500">{{ item.oleh }} · {{ item.waktu }}</span>
                        <button v-if="item.bisa_cabut" type="button" class="tautan-depo ml-auto text-xs" @click.stop="cabut(item)">Cabut</button>
                    </div>
                    <p v-if="item.catatan && terbukaId === item.id" class="mb-0 mt-1 text-sm">{{ item.catatan }}</p>
                </li>
            </ul>
            <p v-else-if="!memuat && !pesan" class="mb-0 text-xs text-gray-500">Belum ada tanda pada tanggal ini.</p>
        </div>

        <div v-if="lainBuka && lainnya.length" class="tanda-motoris-panel">
            <ul class="tanda-motoris-daftar">
                <li v-for="item in lainnya" :key="item.id">
                    <button type="button" class="tanda-motoris-lain" @click="$emit('pilih-tanggal', item.tanggal)">
                        <span class="font-semibold">{{ tanggalSingkat(item.tanggal) }}</span> · {{ item.kategori_label }} · {{ item.oleh }}
                    </button>
                </li>
            </ul>
        </div>
    </section>
</template>
