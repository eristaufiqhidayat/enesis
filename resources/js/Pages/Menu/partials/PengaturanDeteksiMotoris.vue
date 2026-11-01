<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import { BATAS_DETEKSI, BAWAAN_DETEKSI, bacaDeteksi, hapusDeteksi, simpanDeteksi } from '@/Support/deteksiKunjungan.js';

// Ambang deteksi "kunjungan berturutan berjarak dekat" (spec 054, spec 058) langsung di modal Riwayat Lacak Motoris:
// tombol menampilkan ambang yang berlaku dan membuka form kecil (flyover). Disimpan di localStorage browser ini;
// 'berubah' memberi tahu induk agar data tanggal yang dibuka dimuat ulang dengan ambang baru.
const emit = defineEmits(['berubah']);

const buka = ref(false);
const wadah = ref(null);
const berlaku = ref(bacaDeteksi());
const form = reactive({ ...berlaku.value });
const pesan = ref(null);

const dalamBatas = (nilai, { min, maks }) => Number.isInteger(nilai) && nilai >= min && nilai <= maks;
const galat = computed(() => ({
    jarak_dekat_meter: dalamBatas(form.jarak_dekat_meter, BATAS_DETEKSI.jarak_dekat_meter)
        ? null : `Isi bilangan bulat ${BATAS_DETEKSI.jarak_dekat_meter.min}–${BATAS_DETEKSI.jarak_dekat_meter.maks} meter.`,
    min_kunjungan_berturutan: dalamBatas(form.min_kunjungan_berturutan, BATAS_DETEKSI.min_kunjungan_berturutan)
        ? null : `Isi bilangan bulat ${BATAS_DETEKSI.min_kunjungan_berturutan.min}–${BATAS_DETEKSI.min_kunjungan_berturutan.maks} kunjungan.`,
}));
const sah = computed(() => !galat.value.jarak_dekat_meter && !galat.value.min_kunjungan_berturutan);
const sesuaiBawaan = computed(() => berlaku.value.jarak_dekat_meter === BAWAAN_DETEKSI.jarak_dekat_meter
    && berlaku.value.min_kunjungan_berturutan === BAWAAN_DETEKSI.min_kunjungan_berturutan);

function ganti() {
    if (!buka.value) {
        berlaku.value = bacaDeteksi();
        Object.assign(form, berlaku.value);
        pesan.value = null;
    }
    buka.value = !buka.value;
}

function simpan() {
    if (!sah.value) return;

    const { nilai, tersimpan } = simpanDeteksi(form);
    berlaku.value = nilai;
    pesan.value = tersimpan ? null : 'Browser ini menolak penyimpanan lokal, jadi ambang hanya berlaku sampai halaman dimuat ulang.';
    emit('berubah');
    if (tersimpan) buka.value = false;
}

function kembalikanBawaan() {
    berlaku.value = hapusDeteksi();
    Object.assign(form, berlaku.value);
    pesan.value = null;
    emit('berubah');
    buka.value = false;
}

// Klik di luar menutup panel (fase capture: klik di peta Leaflet pun terhitung); isian yang belum disimpan dibuang.
function tutupDiLuar(e) {
    if (buka.value && wadah.value && !wadah.value.contains(e.target)) buka.value = false;
}

onMounted(() => document.addEventListener('click', tutupDiLuar, true));
onBeforeUnmount(() => document.removeEventListener('click', tutupDiLuar, true));
</script>

<template>
    <div ref="wadah" class="deteksi-motoris">
        <button type="button" class="tanda-chip" :aria-expanded="buka" title="Atur ambang deteksi kunjungan berjarak dekat" @click="ganti">
            <Icon name="settings" class="temuan-ikon" />Ambang: {{ berlaku.jarak_dekat_meter }} m · {{ berlaku.min_kunjungan_berturutan }} kunjungan
        </button>
        <Transition name="flyover">
            <form v-if="buka" class="deteksi-panel" aria-label="Ambang deteksi kunjungan berjarak dekat" @submit.prevent="simpan">
                <p class="mb-0 text-xs text-gray-500">
                    Kunjungan berturutan ditandai "jarak dekat" bila jaraknya di bawah ambang dan jumlahnya mencapai minimum.
                    Disimpan di browser ini saja.
                </p>
                <div>
                    <Label for="deteksi_jarak">Jarak dekat (meter)</Label>
                    <Input id="deteksi_jarak" v-model.number="form.jarak_dekat_meter" type="number" min="1" />
                    <FieldError v-if="galat.jarak_dekat_meter">{{ galat.jarak_dekat_meter }}</FieldError>
                </div>
                <div>
                    <Label for="deteksi_min">Minimum kunjungan berturutan</Label>
                    <Input id="deteksi_min" v-model.number="form.min_kunjungan_berturutan" type="number" min="2" />
                    <FieldError v-if="galat.min_kunjungan_berturutan">{{ galat.min_kunjungan_berturutan }}</FieldError>
                </div>
                <p v-if="pesan" class="mb-0 text-xs text-amber-700" role="status">{{ pesan }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="!sah">Simpan</Button>
                    <Button variant="light" :disabled="sesuaiBawaan" @click="kembalikanBawaan">Kembalikan ke bawaan</Button>
                </div>
            </form>
        </Transition>
    </div>
</template>
