<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import DatePicker from '@/Components/ui/atoms/DatePicker.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { angka, angkaRingkas, desimal, durasiSingkat, jarakSingkat, ringkasanPenjualan, rupiah } from '@/Support/format.js';
import { gambarKunjungan, gambarLompatan, gambarRute } from '@/Support/rute.js';
import TandaMotoris from './TandaMotoris.vue';

// Rute GPS satu orang pada satu tanggal di drawer (tombol Lihat riwayat di tabel Tracking Last Seen), dengan daftar
// kunjungan outlet di kiri peta bila endpoint mengirim `kunjungan` (Sales). Titik diambil dari endpoint `locations`
// halaman Riwayat Lokasi; tanggal bisa diganti tanpa menutup drawer.
const props = defineProps({
    // Dari baris tabel: { nama }.
    orang: { type: Object, required: true },
    locationsUrl: { type: String, required: true },
    idParam: { type: String, required: true },
    id: { type: Number, required: true },
    tanggal: { type: String, required: true },
    // Endpoint tanda motoris (TandaMotorisController); null = tanpa fitur tanda (mis. drawer Koordinator).
    tandaUrl: { type: String, default: null },
});

const wadah = ref(null);
const tanggalDipilih = ref(props.tanggal);
const info = ref('Memuat...');
// Kunjungan outlet pada tanggal itu; null = endpoint tanpa data kunjungan (kolom daftar disembunyikan).
const kunjungan = ref(null);
const aktifId = ref(null);
// Indikasi GPS tidak wajar pada tanggal itu (AnomaliGps di endpoint locations); null = tidak ada peringatan.
const anomali = ref(null);

// Isi banner: "terdeteksi" = HP menandai mock location (bukti dari perangkat); "indikasi" = hanya pola lompatan posisi.
const peringatan = computed(() => {
    const a = anomali.value;
    if (!a) return null;

    const terdeteksi = a.tingkat === 'terdeteksi';
    const bagian = [];
    if (a.titik_mock || a.kunjungan_mock) {
        const dari = [a.titik_mock ? `${angka(a.titik_mock)} titik GPS` : null, a.kunjungan_mock ? `${angka(a.kunjungan_mock)} kunjungan` : null].filter(Boolean).join(' dan ');
        bagian.push(`Aplikasi HP menandai lokasi palsu (mock location) pada ${dari}.`);
    }
    if (a.lompatan) {
        bagian.push(`${angka(a.lompatan)} lompatan posisi mustahil, ${angka(a.kembali_ke_asal)} di antaranya langsung kembali ke lokasi semula. `
            + `Terjauh ${desimal(a.terjauh.km)} km dalam ${angkaRingkas(a.terjauh.menit, 1)} menit (${angka(a.terjauh.kmh)} km/jam) pada ${a.terjauh.jam}.`);
    }

    if (a.menyimpang) {
        bagian.push(`${angka(a.menyimpang)} titik GPS menyimpang jauh dari posisi sekitarnya lalu langsung kembali. Terjauh ${desimal(a.menyimpang_terjauh.km)} km pada ${a.menyimpang_terjauh.jam}.`);
    }

    const judul = terdeteksi ? 'Lokasi palsu terdeteksi oleh aplikasi HP' : 'Indikasi GPS tidak wajar';

    return {
        terdeteksi,
        judul,
        isi: bagian.join(' '),
        catatan: terdeteksi ? null : 'Ini indikasi dari pola data, bukan bukti pemalsuan GPS.',
        // Isian otomatis form Tandai motoris.
        saran: { kategori: 'lokasi_palsu', catatan: `${judul}. ${bagian.join(' ')}` },
    };
});

// Ringkasan hari itu dari kunjungan yang ditampilkan: jumlah beli / tidak beli dan total nilai penjualan aktif.
const ringkasanHari = computed(() => {
    const daftar = kunjungan.value ?? [];
    const beli = daftar.filter((k) => k.beli).length;

    return { beli, tidak: daftar.length - beli, total: daftar.reduce((jumlah, k) => jumlah + (k.penjualan?.total ?? 0), 0) };
});

// Layer dan marker Leaflet bukan state reaktif Vue: disimpan di luar ref supaya tidak di-proxy.
let rute = null;
let lapisanKunjungan = null;
let lapisanLompatan = null;
let penanda = new Map();

const { peta } = useLeafletMap(wadah, {
    onSiap(map, L) {
        rute = L.layerGroup().addTo(map);
        lapisanKunjungan = L.layerGroup().addTo(map);
        lapisanLompatan = L.layerGroup().addTo(map);
        // Drawer dibuka dengan animasi, jadi ukuran peta dihitung ulang setelah elemen tampil.
        map.invalidateSize();
        muat();
    },
});

function muat() {
    const map = peta();
    if (!map || !tanggalDipilih.value) return;

    const tanggal = tanggalDipilih.value;
    info.value = 'Memuat...';
    aktifId.value = null;
    fetch(props.locationsUrl + '?' + new URLSearchParams({ [props.idParam]: props.id, tanggal }))
        .then((r) => r.json())
        .then(async (data) => {
            // Respons lama (tanggal sudah diganti atau drawer sudah ditutup) dibuang.
            if (peta() !== map || tanggalDipilih.value !== tanggal) return;
            const L = window.L;
            kunjungan.value = data.kunjungan ?? null;
            anomali.value = data.anomali_gps ?? null;
            info.value = data.total ? `${data.total} titik lokasi` : 'Tidak ada data lokasi pada tanggal ini';
            // Lebar peta berubah saat kolom daftar muncul atau hilang.
            await nextTick();
            map.invalidateSize();

            if (data.total) gambarRute(L, map, rute, data, props.orang.nama);
            else rute.clearLayers();
            penanda = gambarKunjungan(L, lapisanKunjungan, data.kunjungan ?? []);
            gambarLompatan(L, lapisanLompatan, data.anomali_gps?.segmen ?? [], data.anomali_gps?.titik_menyimpang ?? []);
            sesuaikanBatas(L, map, data);
        })
        .catch(() => { info.value = 'Gagal memuat data'; });
}

// Peta mencakup rute dan semua penanda kunjungan.
function sesuaikanBatas(L, map, data) {
    const titik = [
        ...data.locations.map((l) => [parseFloat(l.lat), parseFloat(l.lng)]),
        ...(data.kunjungan ?? []).filter((k) => k.lat !== null && k.lng !== null).map((k) => [k.lat, k.lng]),
    ];
    if (titik.length) map.fitBounds(L.latLngBounds(titik), { padding: [30, 30] });
}

function fokus(k) {
    aktifId.value = k.id;
    const marker = penanda.get(k.id);
    if (!marker) return;

    const map = peta();
    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
    marker.openPopup();
}

watch(tanggalDipilih, muat);
</script>

<template>
    <div class="lg:flex lg:h-full lg:min-h-0 lg:flex-col">
    <div class="flex flex-wrap items-start gap-2 mb-1 lg:shrink-0">
        <DatePicker v-model="tanggalDipilih" aria-label="Tanggal rute" class="w-36" />
        <span class="text-sm text-gray-500 mt-1.5">{{ info }}</span>
        <TandaMotoris v-if="tandaUrl" class="ml-auto w-full sm:w-80" :url="tandaUrl" :sales-id="id" :tanggal="tanggalDipilih" :saran="peringatan?.saran ?? null" @pilih-tanggal="tanggalDipilih = $event" />
    </div>
    <div class="rute-harian lg:min-h-0 lg:flex-1" :class="{ 'rute-harian-daftar': kunjungan !== null }">
        <div v-if="kunjungan !== null" class="rute-harian-kolom">
            <div class="sticky top-0 z-10 bg-white text-sm pb-2">
                <span class="font-semibold">Kunjungan outlet ({{ kunjungan.length }})</span>
                <span v-if="kunjungan.length" class="text-gray-500"> · {{ ringkasanHari.beli }} beli · {{ ringkasanHari.tidak }} tidak beli · {{ rupiah(ringkasanHari.total) }}</span>
                <span v-else class="text-gray-500"> · Tidak ada kunjungan outlet pada tanggal ini.</span>
            </div>
            <section class="space-y-2" aria-label="Kunjungan outlet">
                <template v-for="(k, i) in kunjungan" :key="k.id">
                    <p v-if="k.perjalanan" class="rute-perjalanan">
                        ↓ {{ durasiSingkat(k.perjalanan.menit) }} di jalan<template v-if="k.perjalanan.jarak_m !== null"> · {{ jarakSingkat(k.perjalanan.jarak_m) }}</template>
                    </p>
                    <button
                        type="button"
                        class="rute-kunjungan"
                        :class="{ 'rute-kunjungan-aktif': aktifId === k.id }"
                        @click="fokus(k)"
                    >
                        <span class="rute-kunjungan-nomor" :class="k.beli ? 'rute-kunjungan-beli' : 'rute-kunjungan-tidak'">{{ i + 1 }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold truncate">{{ k.outlet }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ k.jam }}<template v-if="k.keluar"> – {{ k.keluar }}</template><template v-if="k.durasi_menit !== null"> · {{ k.durasi_menit }} menit</template>
                            </span>
                            <span class="mt-1 flex flex-wrap gap-1">
                                <Badge :variant="k.beli ? 'success' : 'warning'">{{ k.beli ? 'Beli' : 'Tidak beli' }}</Badge>
                                <Badge v-if="k.di_luar_rute" variant="warning">Di luar rute</Badge>
                                <Badge v-if="k.lokasi_palsu" variant="danger">Lokasi palsu</Badge>
                                <Badge v-if="k.lat === null" variant="light">Tanpa koordinat</Badge>
                            </span>
                            <span v-if="k.penjualan" class="block text-xs text-gray-500">{{ ringkasanPenjualan(k.penjualan) }}</span>
                            <span v-if="!k.beli && k.keterangan" class="block text-xs text-gray-500">{{ k.keterangan }}</span>
                        </span>
                    </button>
                </template>
            </section>
        </div>
        <div ref="wadah" class="detail-depo-peta detail-depo-peta-drawer-bar"></div>
    </div>
    </div>
</template>
