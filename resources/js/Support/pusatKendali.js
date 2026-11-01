import { angka, angkaRingkas, desimal } from '@/Support/format.js';

// Logika murni untuk modal Riwayat Lacak Motoris (RuteHarianPeta.vue): geser tanggal, ringkasan KPI hari itu,
// penyaringan daftar kunjungan, dan daftar "temuan" terpadu dari semua indikasi.

const polaTanggal = (t) => `${String(t.getUTCFullYear()).padStart(4, '0')}-${String(t.getUTCMonth() + 1).padStart(2, '0')}-${String(t.getUTCDate()).padStart(2, '0')}`;

// Geser string Y-m-d sebanyak `hari` (lewat Date UTC supaya tidak bergeser oleh zona waktu browser).
export function geserTanggal(pola, hari) {
    const [y, m, d] = pola.split('-').map(Number);

    return polaTanggal(new Date(Date.UTC(y, m - 1, d + hari)));
}

export function hariIni() {
    const t = new Date();

    return polaTanggal(new Date(Date.UTC(t.getFullYear(), t.getMonth(), t.getDate())));
}

const menitDari = (jam) => {
    const m = /^(\d{1,2}):(\d{2})/.exec(jam ?? '');

    return m ? Number(m[1]) * 60 + Number(m[2]) : null;
};

// KPI hari itu dari daftar kunjungan. `mulai`/`selesai` berupa jam "HH:MM" (kunjungan pertama dan akhir kunjungan terakhir).
export function ringkasanKunjungan(kunjungan) {
    const beli = kunjungan.filter((k) => k.beli).length;
    const mulai = kunjungan[0]?.jam ?? null;
    const akhir = kunjungan.at(-1);
    const selesai = akhir ? (akhir.keluar ?? akhir.jam) : null;
    const dari = menitDari(mulai);
    const sampai = menitDari(selesai);

    return {
        total: kunjungan.length,
        beli,
        tidak: kunjungan.length - beli,
        persenBeli: kunjungan.length ? Math.round((beli / kunjungan.length) * 100) : 0,
        penjualan: kunjungan.reduce((jumlah, k) => jumlah + (k.penjualan?.total ?? 0), 0),
        mulai,
        selesai,
        durasiMenit: dari !== null && sampai !== null && sampai >= dari ? sampai - dari : null,
    };
}

export const dalamRuntun = (k, indikasiJarak) => indikasiJarak.some((r) => r.kunjungan_id.includes(k.id));

// Kunjungan yang perlu dilihat lebih teliti oleh koordinator.
export const kunjunganBermasalah = (k, indikasiJarak = []) => Boolean(
    k.di_luar_rute || k.lokasi_palsu || k.outlet_ditandai || k.lat === null || dalamRuntun(k, indikasiJarak),
);

// Peringatan pada satu kunjungan (bukan hasilnya: beli / tidak beli; status ditandai ditunjukkan tombol bendera outlet), dengan tingkat yang sama dengan panel Temuan.
export function tandaKunjungan(k, indikasiJarak = []) {
    const tanda = [];
    if (k.lokasi_palsu) tanda.push({ kunci: 'lokasi-palsu', label: 'Lokasi palsu', tingkat: 'bahaya' });
    if (k.di_luar_rute) tanda.push({ kunci: 'di-luar-rute', label: 'Di luar rute', tingkat: 'bahaya' });
    if (dalamRuntun(k, indikasiJarak)) tanda.push({ kunci: 'jarak-dekat', label: 'Jarak dekat', tingkat: 'waspada' });
    if (k.lat === null) tanda.push({ kunci: 'tanpa-koordinat', label: 'Tanpa koordinat', tingkat: 'info' });

    return tanda;
}

// Tingkat yang mewarnai tepi kartu kunjungan: hanya bahaya / waspada; null bila bersih atau cuma info.
export function tingkatKunjungan(k, indikasiJarak = []) {
    const tanda = tandaKunjungan(k, indikasiJarak);

    return ['bahaya', 'waspada'].find((tingkat) => tanda.some((t) => t.tingkat === tingkat)) ?? null;
}

export const FILTER_KUNJUNGAN = ['semua', 'beli', 'tidak', 'bermasalah'];

export function saringKunjungan(kunjungan, filter, indikasiJarak = []) {
    if (filter === 'beli') return kunjungan.filter((k) => k.beli);
    if (filter === 'tidak') return kunjungan.filter((k) => !k.beli);
    if (filter === 'bermasalah') return kunjungan.filter((k) => kunjunganBermasalah(k, indikasiJarak));

    return kunjungan;
}

const BOBOT_TINGKAT = { info: 0, waspada: 1, bahaya: 2 };

export const tingkatTertinggi = (temuan) => temuan.reduce((tinggi, t) => (BOBOT_TINGKAT[t.tingkat] > BOBOT_TINGKAT[tinggi] ? t.tingkat : tinggi), 'info');

const titikKunjungan = (daftar) => daftar.filter((k) => k.lat !== null && k.lng !== null).map((k) => [k.lat, k.lng]);

// Satu daftar temuan dari anomali GPS, runtun jarak dekat, dan tanda pada kunjungan, urut dari tingkat tertinggi.
// Tiap temuan: { kunci, tingkat: 'bahaya' | 'waspada' | 'info', judul, rincian, titik: [[lat, lng]], kunjunganId: [] }.
export function susunTemuan({ anomali = null, indikasiJarak = [], kunjungan = [] }) {
    const temuan = [];
    const a = anomali;

    if (a && (a.titik_mock || a.kunjungan_mock)) {
        const dari = [a.titik_mock ? `${angka(a.titik_mock)} titik GPS` : null, a.kunjungan_mock ? `${angka(a.kunjungan_mock)} kunjungan` : null].filter(Boolean).join(' dan ');
        const palsu = kunjungan.filter((k) => k.lokasi_palsu);

        temuan.push({
            kunci: 'lokasi-palsu',
            tingkat: 'bahaya',
            judul: 'Lokasi palsu terdeteksi',
            rincian: `Aplikasi HP menandai lokasi palsu (mock location) pada ${dari}.`,
            titik: titikKunjungan(palsu),
            kunjunganId: palsu.map((k) => k.id),
        });
    }

    if (a?.lompatan) {
        temuan.push({
            kunci: 'lompatan-gps',
            tingkat: a.tingkat === 'terdeteksi' ? 'bahaya' : 'waspada',
            judul: `${angka(a.lompatan)} lompatan posisi mustahil`,
            rincian: `${angka(a.kembali_ke_asal)} di antaranya langsung kembali ke lokasi semula. Terjauh ${desimal(a.terjauh.km)} km dalam ${angkaRingkas(a.terjauh.menit, 1)} menit (${angka(a.terjauh.kmh)} km/jam) pada ${a.terjauh.jam}.`,
            titik: (a.segmen ?? []).flatMap((s) => [[s.dari.lat, s.dari.lng], [s.ke.lat, s.ke.lng]]),
            kunjunganId: [],
        });
    }

    if (a?.menyimpang) {
        temuan.push({
            kunci: 'gps-menyimpang',
            tingkat: 'waspada',
            judul: `${angka(a.menyimpang)} titik GPS menyimpang`,
            rincian: `Melompat jauh dari posisi sekitarnya lalu langsung kembali. Terjauh ${desimal(a.menyimpang_terjauh.km)} km pada ${a.menyimpang_terjauh.jam}.`,
            titik: (a.titik_menyimpang ?? []).map((t) => [t.lat, t.lng]),
            kunjunganId: [],
        });
    }

    indikasiJarak.forEach((runtun, i) => {
        const terkait = kunjungan.filter((k) => runtun.kunjungan_id.includes(k.id));

        temuan.push({
            kunci: `jarak-dekat-${i}`,
            tingkat: 'waspada',
            judul: 'Kunjungan berturutan berjarak dekat',
            rincian: runtun.saran.catatan,
            titik: titikKunjungan(terkait),
            kunjunganId: runtun.kunjungan_id,
        });
    });

    const ditandai = kunjungan.filter((k) => k.outlet_ditandai);
    if (ditandai.length) {
        temuan.push({
            kunci: 'outlet-ditandai',
            tingkat: 'bahaya',
            judul: `${angka(ditandai.length)} kunjungan ke outlet yang ditandai`,
            rincian: ditandai.map((k) => k.outlet).join(', '),
            titik: titikKunjungan(ditandai),
            kunjunganId: ditandai.map((k) => k.id),
        });
    }

    const diLuarRute = kunjungan.filter((k) => k.di_luar_rute);
    if (diLuarRute.length) {
        temuan.push({
            kunci: 'di-luar-rute',
            // Outlet harus di polygon motoris itu sendiri; kunjungan di luar rute mestinya mustahil bila aplikasi mobile benar.
            tingkat: 'bahaya',
            judul: `${angka(diLuarRute.length)} kunjungan di luar rute`,
            rincian: diLuarRute.map((k) => k.outlet).join(', '),
            titik: titikKunjungan(diLuarRute),
            kunjunganId: diLuarRute.map((k) => k.id),
        });
    }

    const tanpaKoordinat = kunjungan.filter((k) => k.lat === null || k.lng === null);
    if (tanpaKoordinat.length) {
        temuan.push({
            kunci: 'tanpa-koordinat',
            tingkat: 'info',
            judul: `${angka(tanpaKoordinat.length)} kunjungan tanpa koordinat`,
            rincian: 'Posisi kunjungan tidak tercatat sehingga tidak muncul di peta.',
            titik: [],
            kunjunganId: tanpaKoordinat.map((k) => k.id),
        });
    }

    return temuan.sort((x, y) => BOBOT_TINGKAT[y.tingkat] - BOBOT_TINGKAT[x.tingkat]);
}
