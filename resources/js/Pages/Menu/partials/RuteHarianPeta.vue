<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import Button from '@/Components/ui/atoms/Button.vue';
import DatePicker from '@/Components/ui/atoms/DatePicker.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Spinner from '@/Components/ui/atoms/Spinner.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { angka, angkaRingkas, desimal, durasiSingkat, jarakSingkat, ringkasanPenjualan } from '@/Support/format.js';
import { geserTanggal, hariIni, ringkasanKunjungan, saringKunjungan, susunTemuan, tandaKunjungan, tingkatKunjungan } from '@/Support/pusatKendali.js';
import { gambarKunjungan, gambarLompatan, gambarRute } from '@/Support/rute.js';
import RingkasanHariMotoris from './RingkasanHariMotoris.vue';
import TandaMotoris from './TandaMotoris.vue';
import TandaOutlet from './TandaOutlet.vue';
import TemuanMotoris from './TemuanMotoris.vue';

// Tanda outlet (spec 053) selalu di endpoint ini, lepas dari idParam (Sales/Koordinator): hak mengikuti menu outlet_all.
const TANDA_OUTLET_URL = '/outlet_all/tanda';

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
// Kolom daftar kunjungan (bergulir sendiri); dipakai menggulir ke kunjungan yang ditunjuk temuan.
const kolom = ref(null);
const tanggalDipilih = ref(props.tanggal);
const info = ref('');
// true selama titik lokasi tanggal yang dipilih diambil (spinner di samping tanggal).
const memuatData = ref(true);
// Kunjungan outlet pada tanggal itu; null = endpoint tanpa data kunjungan (kolom daftar disembunyikan).
const kunjungan = ref(null);
const aktifId = ref(null);
// Outlet yang drawer Tanda Outlet-nya sedang dibuka ({ id, nama }); null = tertutup.
const outletTerpilih = ref(null);
// Indikasi GPS tidak wajar pada tanggal itu (AnomaliGps di endpoint locations); null = tidak ada peringatan.
const anomali = ref(null);
// Indikasi kunjungan outlet berturutan berjarak dekat (AnomaliJarakKunjungan, spec 054): daftar runtun yang
// memenuhi ambang pada tanggal itu (bisa lebih dari satu per hari); kosong = tidak ada peringatan.
const indikasiJarak = ref([]);
// true setelah data tanggal yang dipilih selesai dimuat (tombol Temuan baru tampil, supaya tidak berkedip "Tidak ada temuan").
const sudahMuat = ref(false);
// Penyaring daftar kunjungan (semua | beli | tidak | bermasalah) dan id kunjungan yang disorot dari klik temuan.
const filter = ref('semua');
const sorotIds = ref([]);
const hariIniPola = hariIni();

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

// KPI hari itu (kunjungan, beli, tidak beli, penjualan, jam kerja) dari kunjungan yang ditampilkan.
const ringkasanHari = computed(() => ringkasanKunjungan(kunjungan.value ?? []));

// Semua indikasi dalam satu daftar untuk tombol Temuan.
const temuan = computed(() => susunTemuan({ anomali: anomali.value, indikasiJarak: indikasiJarak.value, kunjungan: kunjungan.value ?? [] }));

// Filter daftar: nomor tetap mengikuti urutan asli (sama dengan penanda peta), bukan urutan hasil saringan.
const nomorAsli = computed(() => new Map((kunjungan.value ?? []).map((k, i) => [k.id, i + 1])));
const kunjunganTampil = computed(() => saringKunjungan(kunjungan.value ?? [], filter.value, indikasiJarak.value));
const tabFilter = computed(() => {
    const daftar = kunjungan.value ?? [];

    return [
        { key: 'semua', label: `Semua (${daftar.length})` },
        { key: 'beli', label: `Beli (${ringkasanHari.value.beli})` },
        { key: 'tidak', label: `Tidak beli (${ringkasanHari.value.tidak})` },
        { key: 'bermasalah', label: `Bermasalah (${saringKunjungan(daftar, 'bermasalah', indikasiJarak.value).length})` },
    ];
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
    memuatData.value = true;
    aktifId.value = null;
    sorotIds.value = [];
    sudahMuat.value = false;
    fetch(props.locationsUrl + '?' + new URLSearchParams({ [props.idParam]: props.id, tanggal }))
        .then((r) => r.json())
        .then(async (data) => {
            // Respons lama (tanggal sudah diganti atau drawer sudah ditutup) dibuang.
            if (peta() !== map || tanggalDipilih.value !== tanggal) return;
            const L = window.L;
            kunjungan.value = data.kunjungan ?? null;
            anomali.value = data.anomali_gps ?? null;
            indikasiJarak.value = data.indikasi_jarak_kunjungan ?? [];
            sudahMuat.value = true;
            memuatData.value = false;
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
        .catch(() => { memuatData.value = false; info.value = 'Gagal memuat data'; });
}

// Peta mencakup rute dan semua penanda kunjungan.
function sesuaikanBatas(L, map, data) {
    const titik = [
        ...data.locations.map((l) => [parseFloat(l.lat), parseFloat(l.lng)]),
        ...(data.kunjungan ?? []).filter((k) => k.lat !== null && k.lng !== null).map((k) => [k.lat, k.lng]),
    ];
    if (titik.length) map.fitBounds(L.latLngBounds(titik), { padding: [30, 30] });
}

// Outlet dalam salah satu runtun jarak dekat (spec 054) membuka drawer Tanda Outlet dengan saran kategori & catatan terisi.
function bukaTandaOutlet(k) {
    const runtun = indikasiJarak.value.find((r) => r.outlet_id.includes(k.outlet_id));
    outletTerpilih.value = { id: k.outlet_id, nama: k.outlet, saran: runtun?.saran ?? null };
}

// Klik temuan: peta memperbesar titik-titiknya dan daftar menyorot kunjungan terkait (filter dikembalikan ke Semua).
async function fokusTemuan(t) {
    sorotIds.value = t.kunjunganId;
    if (t.kunjunganId.length) filter.value = 'semua';

    const map = peta();
    if (map && t.titik.length) map.fitBounds(window.L.latLngBounds(t.titik), { padding: [60, 60], maxZoom: 18 });

    // Daftar menggulir ke kunjungan pertama yang ditunjuk (setelah filter dikembalikan ke Semua dan daftar digambar ulang).
    const pertama = (kunjungan.value ?? []).find((k) => t.kunjunganId.includes(k.id));
    if (!pertama) return;

    await nextTick();
    kolom.value?.querySelector(`[data-kunjungan-id="${pertama.id}"]`)?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
}

const bisaMaju = computed(() => tanggalDipilih.value < hariIniPola);
const geser = (hari) => { tanggalDipilih.value = geserTanggal(tanggalDipilih.value, hari); };

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
    <div class="flex flex-wrap items-start gap-2 mb-2 lg:shrink-0">
        <!-- Tanggal, status memuat, dan tombol Temuan sebaris dan rata tengah vertikal; kotak Tanda motoris (lebih tinggi) tetap di kanan atas. -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
                <Button variant="light" aria-label="Hari sebelumnya" title="Hari sebelumnya" @click="geser(-1)"><Icon name="chevron-left" class="w-4 h-4" /></Button>
                <DatePicker v-model="tanggalDipilih" :max="hariIniPola" aria-label="Tanggal rute" class="w-36" />
                <Button variant="light" aria-label="Hari berikutnya" title="Hari berikutnya" :disabled="!bisaMaju" @click="geser(1)"><Icon name="chevron-right" class="w-4 h-4" /></Button>
            </div>
            <span class="flex items-center gap-1.5 text-sm text-gray-500" :aria-busy="memuatData"><Spinner v-if="memuatData" />{{ memuatData ? 'Memuat titik lokasi...' : info }}</span>
            <TemuanMotoris :temuan="temuan" :siap="sudahMuat" @fokus="fokusTemuan" />
        </div>
        <TandaMotoris v-if="tandaUrl" class="ml-auto w-full sm:w-80" :url="tandaUrl" :sales-id="id" :tanggal="tanggalDipilih" :saran="peringatan?.saran ?? null" @pilih-tanggal="tanggalDipilih = $event" />
    </div>
    <RingkasanHariMotoris v-if="kunjungan?.length" :ringkasan="ringkasanHari" class="mb-2 lg:shrink-0" />
    <div class="rute-harian lg:min-h-0 lg:flex-1" :class="{ 'rute-harian-daftar': kunjungan !== null }">
        <div v-if="kunjungan !== null" ref="kolom" class="rute-harian-kolom">
            <div class="sticky top-0 z-10 bg-white text-sm pb-1">
                <span class="font-semibold">Kunjungan outlet ({{ kunjungan.length }})</span>
                <span v-if="!kunjungan.length" class="text-gray-500"> · Tidak ada kunjungan outlet pada tanggal ini.</span>
                <Tabs v-else v-model="filter" :tabs="tabFilter" rapat class="mt-1" />
            </div>
            <p v-if="kunjungan.length && !kunjunganTampil.length" class="mb-0 text-sm text-gray-500">Tidak ada kunjungan pada filter ini.</p>
            <section class="space-y-2" aria-label="Kunjungan outlet">
                <template v-for="k in kunjunganTampil" :key="k.id">
                    <p v-if="k.perjalanan && filter === 'semua'" class="rute-perjalanan">
                        ↓ {{ durasiSingkat(k.perjalanan.menit) }} di jalan<template v-if="k.perjalanan.jarak_m !== null"> · {{ jarakSingkat(k.perjalanan.jarak_m) }}</template>
                    </p>
                    <div class="flex items-start gap-1" :data-kunjungan-id="k.id">
                        <button
                            type="button"
                            class="rute-kunjungan min-w-0 flex-1"
                            :class="[tingkatKunjungan(k, indikasiJarak) && `rute-kunjungan-${tingkatKunjungan(k, indikasiJarak)}`, { 'rute-kunjungan-aktif': aktifId === k.id, 'rute-kunjungan-sorot': sorotIds.includes(k.id) }]"
                            @click="fokus(k)"
                        >
                            <span class="rute-kunjungan-nomor" :class="k.beli ? 'rute-kunjungan-beli' : 'rute-kunjungan-tidak'" :title="k.beli ? 'Beli' : 'Tidak beli'">{{ nomorAsli.get(k.id) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold truncate">{{ k.outlet }}</span>
                                <span class="block text-xs text-gray-500">
                                    {{ k.jam }}<template v-if="k.keluar"> – {{ k.keluar }}</template><template v-if="k.durasi_menit !== null"> · {{ k.durasi_menit }} menit</template>
                                </span>
                                <span v-if="k.beli ? k.penjualan : k.keterangan" class="block text-xs text-gray-500">{{ k.beli ? ringkasanPenjualan(k.penjualan) : k.keterangan }}</span>
                                <span v-if="tandaKunjungan(k, indikasiJarak).length" class="mt-1 flex flex-wrap gap-1">
                                    <span v-for="t in tandaKunjungan(k, indikasiJarak)" :key="t.kunci" class="tanda-kunjungan" :class="`tanda-kunjungan-${t.tingkat}`">
                                        <Icon name="flag" class="tanda-kunjungan-ikon" />{{ t.label }}
                                    </span>
                                </span>
                            </span>
                        </button>
                        <Button
                            v-if="k.outlet_id"
                            :variant="k.outlet_ditandai ? 'outline-danger' : 'outline-secondary'"
                            class="tombol-tanda-outlet shrink-0"
                            :class="k.outlet_ditandai ? 'tombol-tanda-ditandai' : 'tombol-tanda-belum'"
                            :title="k.outlet_ditandai ? 'Outlet sudah ditandai - lihat tanda' : 'Tandai outlet'"
                            :aria-label="k.outlet_ditandai ? 'Lihat tanda outlet' : 'Tandai outlet'"
                            @click="bukaTandaOutlet(k)"
                        ><Icon name="flag" /></Button>
                    </div>
                </template>
            </section>
        </div>
        <div class="peta-rute-wadah">
            <div ref="wadah" class="detail-depo-peta detail-depo-peta-drawer-bar"></div>
            <div class="peta-legenda" aria-label="Legenda peta">
                <template v-if="kunjungan !== null">
                    <span class="peta-legenda-item"><span class="peta-legenda-titik rute-kunjungan-beli"></span>Beli</span>
                    <span class="peta-legenda-item"><span class="peta-legenda-titik rute-kunjungan-tidak"></span>Tidak beli</span>
                </template>
                <span class="peta-legenda-item"><span class="peta-legenda-garis"></span>Rute GPS</span>
                <span class="peta-legenda-item"><span class="peta-legenda-garis peta-legenda-lompatan"></span>Lompatan GPS</span>
                <span class="peta-legenda-item"><span class="peta-legenda-cincin"></span>Titik menyimpang</span>
            </div>
        </div>
    </div>
    <!-- Dialog tengah (bukan drawer): tugas singkat yang bertumpuk di atas modal Riwayat yang hampir sepenuh layar. -->
    <Modal :show="outletTerpilih !== null" :title="outletTerpilih ? `Tanda Outlet · ${outletTerpilih.nama}` : ''" @close="outletTerpilih = null">
        <TandaOutlet v-if="outletTerpilih" :url="TANDA_OUTLET_URL" :outlet-id="outletTerpilih.id" :saran="outletTerpilih.saran" @berubah="muat" />
    </Modal>
    </div>
</template>
