// Format angka bahasa Indonesia (pemisah ribuan titik), sama dengan number_format() di Blade.
export const angka = (n) => new Intl.NumberFormat('id-ID').format(n);

// Satu desimal dengan koma (padanan number_format($n, 1, ',', '.')).
export const desimal = (n) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(n);

// Desimal dengan nol di akhir dibuang (padanan rtrim(number_format($n, $maks, ',', '.'), '0'), ',')).
export const angkaRingkas = (n, maks = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: maks }).format(n);

// Tanggal d/m/Y dari string "Y-m-d" atau "Y-m-d H:i:s" (hindari parsing timezone Date() untuk tanggal polos).
export const tanggalPendek = (t) => { const [y, m, d] = t.slice(0, 10).split('-'); return `${d}/${m}/${y}`; };

const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// "31 Okt 2026" dari string "Y-m-d" (atau "Y-m-d H:i:s"); string kosong/tidak cocok -> ''.
export const tanggalSingkat = (t) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(t ?? '');
    const bulan = BULAN_PENDEK[Number(m?.[2]) - 1];

    return m && bulan ? `${Number(m[3])} ${bulan} ${m[1]}` : '';
};

// "22 Okt 2026" dan jam "11:23" dari "Y-m-d H:i:s", dipisah supaya jam bisa ditampilkan sebagai badge.
// Null bila formatnya tidak cocok, termasuk bulan di luar 01–12.
export const tanggalWaktuPendek = (t) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(t ?? '');
    const bulan = BULAN_PENDEK[Number(m?.[2]) - 1];

    return m && bulan ? { tanggal: `${Number(m[3])} ${bulan} ${m[1]}`, jam: m[4] } : null;
};

// Rupiah tanpa desimal, mis. "Rp 10.440.000".
export const rupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);

// Durasi dalam menit: "kurang dari 1 menit", "12 menit", "1 j 5 mnt", "2 j".
export const durasiSingkat = (menit) => {
    if (menit < 1) return 'kurang dari 1 menit';
    if (menit < 60) return `${menit} menit`;

    const sisa = menit % 60;

    return `${Math.floor(menit / 60)} j${sisa ? ` ${sisa} mnt` : ''}`;
};

// Jarak dalam meter: "850 m" di bawah 1 km, selebihnya kilometer satu desimal ("3,4 km").
export const jarakSingkat = (meter) => (meter < 1000 ? `${Math.round(meter)} m` : `${desimal(meter / 1000)} km`);

// Ringkasan penjualan satu kunjungan: "Rp 35.500 · 3 jenis · 5 unit", diawali "2 transaksi · " bila lebih dari satu.
export const ringkasanPenjualan = (p) => [
    p.transaksi > 1 ? `${p.transaksi} transaksi` : null,
    rupiah(p.total),
    `${p.sku} jenis`,
    `${angkaRingkas(p.qty)} unit`,
].filter(Boolean).join(' · ');
