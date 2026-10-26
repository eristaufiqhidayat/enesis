// Format angka bahasa Indonesia (pemisah ribuan titik), sama dengan number_format() di Blade.
export const angka = (n) => new Intl.NumberFormat('id-ID').format(n);

// Satu desimal dengan koma (padanan number_format($n, 1, ',', '.')).
export const desimal = (n) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(n);

// Desimal dengan nol di akhir dibuang (padanan rtrim(number_format($n, $maks, ',', '.'), '0'), ',')).
export const angkaRingkas = (n, maks = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: maks }).format(n);

// Tanggal d/m/Y dari string "Y-m-d" atau "Y-m-d H:i:s" (hindari parsing timezone Date() untuk tanggal polos).
export const tanggalPendek = (t) => { const [y, m, d] = t.slice(0, 10).split('-'); return `${d}/${m}/${y}`; };
