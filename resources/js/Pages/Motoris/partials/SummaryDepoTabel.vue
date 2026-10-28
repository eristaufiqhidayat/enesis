<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import { angka } from '@/Support/format.js';
import { KELOMPOK_KOLOM, formatSel, hitungTotal, warnaCapai } from '@/Support/summaryDepo.js';

// Tampilan tabel Summary All Depo: satu baris per depo, metrik sebagai kolom. Data = prop kartu yang sama dengan tampilan kartu.
const props = defineProps({
    kartu: { type: Array, default: () => [] },
    target: { type: Object, required: true },
    hkKoordinator: { type: Number, default: 0 },
    // Pembuka modal detail depo dan modal detail EC & penjualan (lihat Pages/Motoris/SummaryDepo.vue).
    bukaDetail: { type: Function, required: true },
    bukaPenjualan: { type: Function, required: true },
});

const semuaKolom = KELOMPOK_KOLOM.flatMap((g) => g.kolom);
const total = computed(() => hitungTotal(props.kartu, props.target, props.hkKoordinator));

// Sort di sisi klien (data semua depo sudah dimuat): naik → turun → urutan bawaan (EC Achievement dari server).
const sort = ref({ kunci: null, arah: null });

function urutkan(kunci) {
    const { kunci: aktif, arah } = sort.value;
    sort.value = aktif !== kunci || arah === null ? { kunci, arah: 'asc' } : (arah === 'asc' ? { kunci, arah: 'desc' } : { kunci: null, arah: null });
}

const arahSort = (kunci) => (sort.value.kunci === kunci ? sort.value.arah : null);
const ariaSort = (kunci) => ({ asc: 'ascending', desc: 'descending' })[arahSort(kunci)] ?? 'none';

const baris = computed(() => {
    const { kunci, arah } = sort.value;

    if (!kunci) {
        return props.kartu;
    }

    const faktor = arah === 'asc' ? 1 : -1;

    return [...props.kartu].sort((a, b) => ((Number(a[kunci]) || 0) - (Number(b[kunci]) || 0)) * faktor);
});

const classAngka = (kolom, nilai) => (kolom.tipe === 'persen' ? warnaCapai(nilai) : null);

// Scroll mouse di kolom Depo (sticky) tetap scroll vertikal tabel seperti biasa (lihat sesuaikanTinggi() di
// bawah — tabel ini scroll vertikal sendiri, bukan halaman). Di kolom lain tidak ada konversi otomatis ke
// scroll horizontal (dulu dicoba lewat wheel handler, dibatalkan — tumpang tindih dengan scroll vertikal tabel
// bikin perilakunya membingungkan); geser tabel ke samping pakai scrollbar bawah atau Shift + Scroll bawaan
// browser, diberitahu lewat atribut title di sel-sel non-Depo (lihat HINT_GESER).
const scrollEl = ref(null);
const HINT_GESER = 'Tahan Shift + Scroll untuk menggeser tabel ke samping';

// Baris kedua header (nama kolom) harus menempel tepat di bawah baris pertama (kelompok), bukan menimpanya —
// tingginya diukur di sini karena bergantung pada ukuran font/padding, bukan nilai tetap.
const barisGrup = ref(null);
const topBarisKolom = ref('0px');

// `position: sticky` vertikal cuma bisa bekerja kalau kontainer scroll-nya benar-benar scroll secara vertikal
// (overflow-y: auto + tinggi dibatasi) — div dengan overflow-x-auto saja membuat overflow-y ikut terhitung
// "auto" oleh browser tapi TIDAK pernah benar-benar discroll (tingginya mengikuti isi), sehingga sticky top
// tidak pernah "nempel" (terbukti lewat devtools: elemen tetap ikut discroll halaman, bukan menempel).
// Makanya tabel ini scroll vertikal di dalam dirinya sendiri (seperti spreadsheet), dibatasi sampai sisa
// tinggi layar, bukan ikut scroll halaman penuh — scroll mouse di mana pun di tabel otomatis mengenai
// scrollbar vertikal ini karena itu scrollable ancestor terdekat.
// Footer kartu ("Total N depo") dan padding bawah <main> ikut diukur, supaya tinggi tabel pas dan halaman
// tidak perlu discroll sama sekali — bukan cuma menyisakan jarak tetap yang bisa meleset. +2px ekstra sebagai
// jaga-jaga pembulatan subpixel (supaya tidak pas-pasan sampai memunculkan scrollbar halaman 1px).
const footerEl = ref(null);
const PADDING_BAWAH_MAIN = 24 + 2; // 1.5rem, lihat `main { padding: ... 1.5rem }` di app.css

function sesuaikanTinggi() {
    if (!scrollEl.value) {
        return;
    }

    const atas = scrollEl.value.getBoundingClientRect().top;
    // .card-footer (bukan elemen <small> itu sendiri) supaya padding di sekelilingnya ikut terhitung.
    const bawah = (footerEl.value?.closest('.card-footer')?.offsetHeight ?? 0) + PADDING_BAWAH_MAIN;
    scrollEl.value.style.maxHeight = `calc(100vh - ${Math.round(atas)}px - ${bawah}px)`;
}

onMounted(() => {
    if (barisGrup.value) {
        topBarisKolom.value = `${barisGrup.value.offsetHeight}px`;
    }

    sesuaikanTinggi();
    window.addEventListener('resize', sesuaikanTinggi);
});

onUnmounted(() => window.removeEventListener('resize', sesuaikanTinggi));
</script>
<template>
    <Card body-class="p-0">
        <div ref="scrollEl" class="overflow-auto">
        <table class="data-table tabel-header-sticky">
            <thead>
                <tr ref="barisGrup">
                    <th rowspan="2" class="kolom-sticky kolom-sticky-pembatas">Depo</th>
                    <th v-for="g in KELOMPOK_KOLOM" :key="g.judul" :colspan="g.kolom.length" class="th-grup" :title="HINT_GESER">{{ g.judul }}</th>
                </tr>
                <tr>
                    <th
                        v-for="kolom in semuaKolom"
                        :key="kolom.kunci"
                        class="sortable text-right"
                        :class="{ 'sort-aktif': arahSort(kolom.kunci), 'sort-asc': arahSort(kolom.kunci) === 'asc' }"
                        :style="{ top: topBarisKolom }"
                        :title="HINT_GESER"
                        :aria-sort="ariaSort(kolom.kunci)"
                    >
                        <button type="button" class="sort-button" @click="urutkan(kolom.kunci)">
                            {{ kolom.label }}
                            <Icon :name="arahSort(kolom.kunci) ? 'chevron-down' : 'chevrons-down'" class="sort-icon" />
                        </button>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="k in baris" :key="k.id">
                    <td class="kolom-sticky kolom-sticky-pembatas">
                        <button type="button" class="font-semibold text-blue-600 hover:underline" @click="bukaDetail(k)">{{ k.kode ?? k.nama }}</button>
                        <span class="block text-[0.7rem] uppercase text-gray-500">{{ [k.nama, k.distributor].filter(Boolean).join(' · ') }}</span>
                    </td>
                    <td v-for="kolom in semuaKolom" :key="kolom.kunci" class="text-right" :class="classAngka(kolom, k[kolom.kunci])" :title="HINT_GESER">
                        <button v-if="kolom.tautan" type="button" class="text-green-500 underline decoration-dotted underline-offset-2" title="Lihat data EC & penjualan" @click="bukaPenjualan(k)">{{ formatSel(k[kolom.kunci], kolom.tipe) }}</button>
                        <template v-else>{{ formatSel(k[kolom.kunci], kolom.tipe) }}</template>
                    </td>
                </tr>
                <tr v-if="!kartu.length">
                    <td :colspan="semuaKolom.length + 1" class="text-center text-gray-500 py-4">Tidak ada depo yang cocok.</td>
                </tr>
            </tbody>
            <tfoot v-if="kartu.length">
                <tr class="baris-total">
                    <th class="kolom-sticky kolom-sticky-pembatas">Total ({{ kartu.length }} depo)</th>
                    <td v-for="kolom in semuaKolom" :key="kolom.kunci" class="text-right" :class="classAngka(kolom, total[kolom.kunci])" :title="HINT_GESER">{{ formatSel(total[kolom.kunci], kolom.tipe) }}</td>
                </tr>
            </tfoot>
        </table>
        </div>
        <template #footer>
            <small ref="footerEl" class="text-gray-500">Total {{ angka(kartu.length) }} depo</small>
        </template>
    </Card>
</template>
