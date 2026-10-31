// Tabel yang punya tautan membuka drawer Riwayat (rute harian, kunjungan, tanda motoris) beserta halaman Riwayat Lokasi
// tujuannya dan nama parameter id-nya. Dipakai Menu/Show.vue untuk tabel Tracking Last Seen dan rekap Motoris Ditandai.
export const riwayatTabel = {
    tracking_last_seen_sales: { slug: 'tracking_sales_history_location', idParam: 'sales_id' },
    tracking_last_seen_koordinator: { slug: 'tracking_koordinator_history_location', idParam: 'koordinator_id' },
    tanda_motoris_rekap: { slug: 'tracking_sales_history_location', idParam: 'sales_id' },
};

// Tabel Tracking Last Seen (kolom Terakhir Terlihat, Lokasi, dan Ditandai berlaku untuk keduanya, Ditandai hanya Sales).
export const tabelLastSeen = ['tracking_last_seen_sales', 'tracking_last_seen_koordinator'];

const POLA_TANGGAL = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Data untuk membuka drawer Riwayat dari satu baris tabel, atau null bila tidak boleh / tidak bisa dibuka.
 *
 * @param {string} namaTabel nama tabel menu (kunci riwayatTabel)
 * @param {object} baris baris tabel
 * @param {Array<{slug: string}>} tabHalaman tab halaman yang boleh dibuka akun (shared prop tabHalaman)
 * @param {string|null} tanggalPilih tanggal yang dibuka; bawaannya tanggal terakhir terlihat (Last Seen) atau tanggal tanda (rekap)
 * @returns {{orang: object, slug: string, idParam: string, tanggal: string}|null}
 */
export function infoRiwayat(namaTabel, baris, tabHalaman, tanggalPilih = null) {
    const tujuan = riwayatTabel[namaTabel];
    const ada = (slug) => (tabHalaman ?? []).some((tab) => tab.slug === slug);

    // Halaman riwayat harus boleh dibuka; drawer Sales juga memuat tanda motoris, yang memakai hak Tracking Last Seen Sales.
    if (!tujuan || !ada(tujuan.slug) || (tujuan.idParam === 'sales_id' && !ada('tracking_sales'))) return null;

    const rekap = namaTabel === 'tanda_motoris_rekap';
    // Last Seen: "YYYY-MM-DD HH:mm:ss" atau "Belum ada data"; rekap: "YYYY-MM-DD".
    const tanggal = tanggalPilih ?? String((rekap ? baris.tanggal : baris.terakhir_terlihat) ?? '').slice(0, 10);
    if (!POLA_TANGGAL.test(tanggal)) return null;

    return {
        orang: rekap ? { id: Number(baris.karyawan_id), nama: baris.motoris } : baris,
        slug: tujuan.slug,
        idParam: tujuan.idParam,
        tanggal,
    };
}
