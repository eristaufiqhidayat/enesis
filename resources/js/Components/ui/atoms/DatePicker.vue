<script setup>
// Pemilih tanggal: kolom tampil "31 Okt 2026" (readonly) yang membuka kalender bulanan (Senin sebagai awal minggu).
// v-model berformat Y-m-d (sama dengan <input type="date">). min/max (Y-m-d) menonaktifkan tanggal di luar rentang.
// Atribut non-prop (id, aria-*, required, dst.) diteruskan ke kolom input; class & style ke pembungkus.
// Keyboard: Enter/Spasi/↓ membuka, panah menggeser tanggal, PageUp/PageDown ganti bulan, Esc menutup.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, watch } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import { tanggalSingkat } from '@/Support/format.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: String, default: '' },
    min: { type: String, default: null },
    max: { type: String, default: null },
    placeholder: { type: String, default: 'Pilih tanggal' },
    disabled: { type: Boolean, default: false },
    // true = ada tombol "Hapus" untuk mengosongkan tanggal.
    clearable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const NAMA_HARI = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const attrs = useAttrs();
const atributInput = computed(() => {
    const { class: _kelas, style: _gaya, ...sisa } = attrs;

    return sisa;
});

// Tanggal dihitung sebagai string Y-m-d (lewat Date UTC) supaya tidak bergeser oleh zona waktu browser.
const kePola = (y, m, d) => `${String(y).padStart(4, '0')}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
const dariPola = (pola) => {
    const [y, m, d] = pola.split('-').map(Number);

    return { y, m: m - 1, d };
};
const geser = (pola, hari) => {
    const { y, m, d } = dariPola(pola);
    const t = new Date(Date.UTC(y, m, d + hari));

    return kePola(t.getUTCFullYear(), t.getUTCMonth(), t.getUTCDate());
};
const hariIni = () => {
    const t = new Date();

    return kePola(t.getFullYear(), t.getMonth(), t.getDate());
};

const buka = ref(false);
const wrapper = ref(null);
const kolom = ref(null);
const grid = ref(null);
// Bulan yang sedang ditampilkan (y, m 0-11) dan tanggal yang difokuskan keyboard.
const tampil = ref(dariPola(props.modelValue || hariIni()));
const fokusPola = ref(props.modelValue || hariIni());

const dalamRentang = (pola) => (!props.min || pola >= props.min) && (!props.max || pola <= props.max);

const judulBulan = computed(() => `${NAMA_BULAN[tampil.value.m]} ${tampil.value.y}`);

// 6 minggu x 7 hari, dimulai Senin; tanggal di luar bulan tampil redup tapi tetap bisa dipilih.
const sel = computed(() => {
    const { y, m } = tampil.value;
    const hariPertama = new Date(Date.UTC(y, m, 1)).getUTCDay(); // 0 = Minggu
    const awal = geser(kePola(y, m, 1), -((hariPertama + 6) % 7));
    const sekarang = hariIni();

    return Array.from({ length: 42 }, (_, i) => {
        const pola = geser(awal, i);

        return {
            pola,
            hari: dariPola(pola).d,
            luar: dariPola(pola).m !== m,
            dipilih: pola === props.modelValue,
            hariIni: pola === sekarang,
            nonaktif: !dalamRentang(pola),
        };
    });
});

function tampilkanBulan(pola) {
    const { y, m } = dariPola(pola);
    tampil.value = { y, m };
}

function gantiBulan(delta) {
    const { y, m } = tampil.value;
    const t = new Date(Date.UTC(y, m + delta, 1));
    tampil.value = { y: t.getUTCFullYear(), m: t.getUTCMonth() };
}

function bukaKalender() {
    if (props.disabled || buka.value) return;

    const awal = props.modelValue || hariIni();
    tampilkanBulan(awal);
    fokusPola.value = awal;
    buka.value = true;
}

function tutup(kembaliFokus = false) {
    buka.value = false;
    if (kembaliFokus) kolom.value?.focus();
}

function pilih(pola) {
    if (!dalamRentang(pola)) return;

    emit('update:modelValue', pola);
    tutup(true);
}

function pilihHariIni() {
    pilih(hariIni());
}

function hapus() {
    emit('update:modelValue', '');
    tutup(true);
}

// Pindahkan fokus keyboard ke tanggal lain (dan bulannya ikut berpindah bila perlu).
function pindahFokus(pola) {
    fokusPola.value = pola;
    const { y, m } = dariPola(pola);
    if (y !== tampil.value.y || m !== tampil.value.m) tampilkanBulan(pola);
    nextTick(() => grid.value?.querySelector(`[data-pola="${pola}"]`)?.focus());
}

function tombolKolom(e) {
    if (['Enter', ' ', 'ArrowDown'].includes(e.key)) {
        e.preventDefault();
        bukaKalender();
        nextTick(() => grid.value?.querySelector(`[data-pola="${fokusPola.value}"]`)?.focus());
    }
}

function tombolGrid(e) {
    const langkah = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];

    if (langkah) {
        e.preventDefault();
        pindahFokus(geser(fokusPola.value, langkah));
    } else if (e.key === 'PageUp' || e.key === 'PageDown') {
        e.preventDefault();
        const { y, m, d } = dariPola(fokusPola.value);
        const t = new Date(Date.UTC(y, m + (e.key === 'PageUp' ? -1 : 1), 1));
        const akhir = new Date(Date.UTC(t.getUTCFullYear(), t.getUTCMonth() + 1, 0)).getUTCDate();
        pindahFokus(kePola(t.getUTCFullYear(), t.getUTCMonth(), Math.min(d, akhir)));
    }
}

function tutupDiLuar(e) {
    if (buka.value && wrapper.value && !wrapper.value.contains(e.target)) tutup();
}

function tutupDenganEsc(e) {
    if (buka.value && e.key === 'Escape') {
        e.stopPropagation(); // jangan ikut menutup modal di belakangnya
        tutup(true);
    }
}

// Nilai berubah dari luar (mis. reset form) saat kalender terbuka: ikuti bulannya.
watch(() => props.modelValue, (nilai) => {
    if (nilai) tampilkanBulan(nilai);
});

onMounted(() => {
    document.addEventListener('click', tutupDiLuar);
    document.addEventListener('keydown', tutupDenganEsc, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', tutupDiLuar);
    document.removeEventListener('keydown', tutupDenganEsc, true);
});
</script>

<template>
    <div ref="wrapper" class="datepicker" :class="$attrs.class" :style="$attrs.style">
        <input
            ref="kolom"
            type="text"
            readonly
            class="form-control form-control-sm datepicker-input"
            :value="tanggalSingkat(modelValue)"
            :placeholder="placeholder"
            :disabled="disabled"
            aria-haspopup="dialog"
            :aria-expanded="buka"
            v-bind="atributInput"
            @click="bukaKalender"
            @keydown="tombolKolom"
        >
        <Icon name="calendar" class="datepicker-ikon" />

        <div v-if="buka" class="datepicker-menu" role="dialog" aria-label="Pilih tanggal">
            <div class="datepicker-header">
                <button type="button" class="datepicker-nav" aria-label="Bulan sebelumnya" @click="gantiBulan(-1)">
                    <Icon name="chevron-left" class="w-4 h-4" />
                </button>
                <span class="datepicker-judul" aria-live="polite">{{ judulBulan }}</span>
                <button type="button" class="datepicker-nav" aria-label="Bulan berikutnya" @click="gantiBulan(1)">
                    <Icon name="chevron-right" class="w-4 h-4" />
                </button>
            </div>
            <div ref="grid" class="datepicker-grid" role="grid" @keydown="tombolGrid">
                <span v-for="nama in NAMA_HARI" :key="nama" class="datepicker-hari-nama" role="columnheader">{{ nama }}</span>
                <button
                    v-for="s in sel"
                    :key="s.pola"
                    type="button"
                    class="datepicker-hari"
                    :class="{ 'datepicker-hari-luar': s.luar, 'datepicker-hari-hariini': s.hariIni, 'datepicker-hari-pilih': s.dipilih }"
                    :data-pola="s.pola"
                    :disabled="s.nonaktif"
                    :tabindex="s.pola === fokusPola ? 0 : -1"
                    :aria-selected="s.dipilih"
                    :aria-current="s.hariIni ? 'date' : null"
                    @click="pilih(s.pola)"
                >{{ s.hari }}</button>
            </div>
            <div class="datepicker-footer">
                <button type="button" class="datepicker-aksi" :disabled="!dalamRentang(hariIni())" @click="pilihHariIni">Hari ini</button>
                <button v-if="clearable" type="button" class="datepicker-aksi" @click="hapus">Hapus</button>
            </div>
        </div>
    </div>
</template>
