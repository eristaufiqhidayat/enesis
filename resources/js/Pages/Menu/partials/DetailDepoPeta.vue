<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { escapeHtml, ikonDepo, ikonLingkaran, ikonMotoris } from '@/Support/peta.js';

// Peta satu titik di drawer: lokasi depo (kolom latitude/longitude master_depo) atau posisi terakhir motoris
// (ikon 'motoris', baris Tracking Last Seen). Objek `depo` cukup memuat nama, latitude, dan longitude.
const props = defineProps({
    depo: { type: Object, required: true },
    // Dipakai di drawer peta: peta mengisi tinggi drawer, bukan tinggi tetap di bagian Lokasi.
    drawer: { type: Boolean, default: false },
    // Ikon penanda: 'depo' (merah) atau 'motoris' (biru).
    ikon: { type: String, default: 'depo' },
    // Keterangan di bawah nama pada popup penanda (mis. waktu terakhir terlihat); bila diisi popup langsung terbuka.
    rincian: { type: String, default: null },
});

const wadah = ref(null);
const penuh = ref(false);

const adaKoordinat = ['latitude', 'longitude'].every((kunci) => {
    const nilai = props.depo[kunci];

    return nilai !== null && nilai !== '' && nilai !== undefined && Number.isFinite(Number(nilai));
});

const lat = Number(props.depo.latitude);
const lng = Number(props.depo.longitude);

const { peta } = useLeafletMap(wadah, {
    pusat: adaKoordinat ? [lat, lng] : undefined,
    zoom: 15,
    onSiap(map, L) {
        const motoris = props.ikon === 'motoris';
        const penanda = L.marker([lat, lng], { icon: motoris ? ikonLingkaran('#0d6efd', ikonMotoris) : ikonLingkaran('#dc2626', ikonDepo) })
            .addTo(map)
            .bindPopup(escapeHtml(props.depo.nama) + (props.rincian ? `<br><small class="text-gray-500">${escapeHtml(props.rincian)}</small>` : ''));

        if (props.rincian) penanda.openPopup();
        // Drawer dibuka dengan animasi, jadi ukuran peta dihitung ulang setelah elemen tampil.
        map.invalidateSize();
    },
});

// Ukuran peta ikut berubah saat masuk atau keluar layar penuh, jadi dihitung ulang setelah DOM diperbarui.
function aturUlangUkuran() {
    nextTick(() => peta()?.invalidateSize());
}

function gantiPenuh() {
    penuh.value = !penuh.value;
    aturUlangUkuran();
}

// Esc saat layar penuh hanya keluar dari layar penuh. Ditangkap di fase capture supaya drawer di belakangnya tidak ikut tertutup.
function tanganiEsc(event) {
    if (penuh.value && event.key === 'Escape') {
        event.stopPropagation();
        penuh.value = false;
        aturUlangUkuran();
    }
}

onMounted(() => window.addEventListener('keydown', tanganiEsc, true));
onUnmounted(() => window.removeEventListener('keydown', tanganiEsc, true));
</script>

<template>
    <div v-if="adaKoordinat" class="detail-depo-peta-wadah" :class="{ 'detail-depo-peta-penuh': penuh }">
        <div ref="wadah" class="detail-depo-peta" :class="{ 'detail-depo-peta-drawer': drawer }"></div>
        <Button
            variant="light"
            class="detail-depo-peta-tombol"
            :aria-label="penuh ? 'Keluar dari layar penuh' : 'Tampilkan peta layar penuh'"
            @click="gantiPenuh"
        >
            <Icon :name="penuh ? 'minimize-2' : 'maximize-2'" />
        </Button>
    </div>
    <div v-else class="text-xs text-gray-500">Koordinat belum diisi.</div>
</template>
