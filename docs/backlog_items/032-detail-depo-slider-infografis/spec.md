# Detail Depo: Drawer Infografis

## Problem Statement

Di halaman `master_depo` ([config/marketama.php](../../../config/marketama.php), tabel di [Menu/Show.vue](../../../resources/js/Pages/Menu/Show.vue)), setiap depo punya 26 kolom data (identitas, wilayah, tim, kuota, target, insentif). Tabel lebar ini sulit dibaca sekaligus: user harus menggulir ke kanan dan mencocokkan angka dengan kolom judul yang jauh. Tidak ada cara untuk melihat gambaran utuh satu depo dalam satu layar.

## Solution

Nama depo di kolom Nama menjadi tombol. Klik tombol tersebut membuka drawer setinggi layar yang masuk dari kanan (memakai molecule `Modal` dengan `placement="kanan"`). Isi drawer adalah infografis satu halaman yang digulir. Semua bagian tampil berurutan, tanpa slide atau navigasi:

1. **Ringkasan**: angka sales aktif, koordinator, dan wilayah depo; kode, projek, kota besar, status (badge). Metadata "Diupdate oleh" dan "Diupdate pada" tampil di footer drawer, di sebelah tombol Edit.
2. **Lokasi**: provinsi, kota/kab, kecamatan, kelurahan/desa, alamat, koordinat (latitude, longitude), dan multi polygon sales.
3. **Tim & Kuota**: batang progress sales aktif terhadap kuota sales motoris, dan koordinator terhadap kuota koordinator; durasi tracking GPS.
4. **Target & Insentif**: target call, target EC, outlet 1 SKU, minimum SKU, insentif per poin, dan minimal value transaksi.

Penutupan lewat tombol tutup atau Esc (sudah ditangani `Modal`).

## User Stories

1. Sebagai manajer, saya ingin klik nama depo dan langsung melihat ringkasan lengkapnya tanpa menggulir tabel.
2. Sebagai manajer, saya ingin melihat seberapa dekat jumlah sales aktif dan koordinator dengan kuotanya secara visual.
3. Sebagai manajer, saya ingin menggulir drawer untuk melihat lokasi, tim, dan target depo.

## Implementation Decisions

- Tidak ada query baru: drawer memakai baris (`row`) yang sudah ada di tabel `master_depo`, karena 26 kolom tabel itu sudah memuat semua data yang ditampilkan. Data relasi tambahan (mis. daftar sales per depo) tidak termasuk.
- Pemicu: slot `cell-nama` di `Menu/Show.vue` untuk tabel `master_depo` (pola yang sama dengan `table.name === 'checkin_pagi'`). Kolom nama tetap sticky sesuai spec sebelumnya.
- Infografis ada di `Pages/Menu/partials/DetailDepo.vue` karena hanya dipakai satu halaman. Dialog memakai molecule `Modal` yang sudah ada, tidak membuat dialog baru.
- Format angka memakai `Intl` `id-ID`. Kolom insentif per poin dan minimal value transaksi ditampilkan sebagai rupiah (`Rp 15.000`); nilai kosong ditampilkan `-`.
- Progress bar: persentase = nilai / kuota, dibatasi 0–100%. Bila kuota 0 atau kosong, tampilkan "Kuota belum diisi." tanpa bar.
- Styling berulang (tombol nama depo, kartu angka, batang progress) lewat class komponen di `resources/css/app.css`, bukan utility Tailwind panjang di template.

## Testing Decisions

- Spec Vitest `DetailDepo.spec.js`: keempat bagian tampil sekaligus tanpa navigasi, lokasi tampil, persentase progress benar dan dibatasi 100, kuota kosong menampilkan catatan, format rupiah benar.
- Verifikasi manual di browser: klik nama depo membuka drawer dari kanan setinggi layar, drawer bisa digulir, Esc dan tombol tutup menutupnya, tampilan di lebar sempit.

## Out of Scope

- Peta lokasi depo dan grafik historis.
- Daftar sales, koordinator, atau outlet milik depo (butuh query relasi baru).
- Mengubah kolom atau urutan tabel `master_depo` selain yang sudah dikerjakan di spec ini.
- Menyimpan perubahan data depo. Drawer punya tombol Edit yang membuka modal Edit (form terisi data depo), tetapi tombol Simpan masih nonaktif sampai tujuan penyimpanannya diputuskan (data master_depo berasal dari impor Marketama).
- Navigasi slide atau wizard bertahap: informasi ditampilkan lewat scroll.
