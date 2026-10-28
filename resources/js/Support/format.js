// Format angka bahasa Indonesia (pemisah ribuan titik), sama dengan number_format() di Blade.
export const angka = (n) => new Intl.NumberFormat('id-ID').format(n);

// Satu desimal dengan koma (padanan number_format($n, 1, ',', '.')).
export const desimal = (n) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(n);

// Desimal dengan nol di akhir dibuang (padanan rtrim(number_format($n, $maks, ',', '.'), '0'), ',')).
export const angkaRingkas = (n, maks = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: maks }).format(n);

// Tanggal d/m/Y dari string "Y-m-d" atau "Y-m-d H:i:s" (hindari parsing timezone Date() untuk tanggal polos).
export const tanggalPendek = (t) => { const [y, m, d] = t.slice(0, 10).split('-'); return `${d}/${m}/${y}`; };

const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// "22 Okt 2026" dan jam "11:23" dari "Y-m-d H:i:s", dipisah supaya jam bisa ditampilkan sebagai badge.
// Null bila formatnya tidak cocok, termasuk bulan di luar 01–12.
export const tanggalWaktuPendek = (t) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(t ?? '');
    const bulan = BULAN_PENDEK[Number(m?.[2]) - 1];

    return m && bulan ? { tanggal: `${Number(m[3])} ${bulan} ${m[1]}`, jam: m[4] } : null;
};

// Rupiah tanpa desimal, mis. "Rp 10.440.000".
export const rupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
