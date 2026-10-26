# Tampilan Tabel untuk Summary All Depo

## Problem Statement

Halaman `summary_all_depo` ([Motoris/SummaryDepo.vue](../../../resources/js/Pages/Motoris/SummaryDepo.vue)) menampilkan ringkasan tiap depo sebagai kartu, meniru desain asli Marketama. Dengan 30 depo dan ±17 metrik per depo, kartu sulit dipakai untuk membandingkan depo satu sama lain: user harus menggulir banyak kartu dan membandingkan angka dengan mata. User kurang menyukai tampilan kartu, tetapi desain kartu adalah desain asli Marketama sehingga tidak boleh dihapus.

## Solution

Tambahkan tampilan tabel sebagai tab kedua di halaman `summary_all_depo` yang sama (URL sama), dengan tombol tab **Kartu | Tabel** memakai komponen `Tabs` ([022](../022-tabs-gaya-tombol/spec.md)).

Orientasi tabel: **depo di baris, metrik di kolom**, dipilih karena:

- Jumlah depo (30, bisa bertambah) lebih banyak daripada jumlah metrik yang dibandingkan di layar; tabel panjang ke bawah lebih mudah dipakai daripada 30 kolom yang harus digeser ke samping.
- Pola baca user adalah per depo (satu baris menggantikan satu kartu) sekaligus per metrik (satu kolom bisa diurutkan dan diberi warna capaian).
- Sejalan dengan organism `DataTable` yang sudah punya sort dan filter per kolom ([023](../023-filter-sort-per-kolom-datatable/spec.md)).

Rincian tampilan:

1. Kolom pertama: nama depo + distributor, *sticky* di kiri saat tabel digeser horizontal.
2. Kolom dikelompokkan seperti kartu: Personil (SK, Motoris), Aktivitas periode (Sales, Call, EC), Rata-rata per hari kerja (AVG Hari Kerja, AVG Call, Call Achievement, AVG EC, EC Achievement, AVG Sales, AVG IPT, IPT Achievement, AVG Point), Kunjungan SK (SK Visit, Visit SK, AVG Visit SK).
3. Kolom Achievement diberi warna dengan `warnaCapai` yang sama dengan kartu.
4. Kolom Sales berupa tautan ke halaman Detail EC & Penjualan depo; nama depo berupa tautan ke halaman detail depo (sama seperti kartu).
5. Baris total di bawah (jumlah untuk kolom total, rata-rata berbobot untuk kolom AVG).
6. Filter tanggal dan kotak cari depo dipakai bersama oleh kedua tab; pilihan tab disimpan di query string (`?tampilan=tabel`) sehingga bisa di-bookmark dan tetap terjaga saat filter diganti.

## User Stories

1. Sebagai manajer, saya ingin melihat semua depo dalam satu tabel supaya bisa membandingkan angka antar depo tanpa menggulir kartu.
2. Sebagai manajer, saya ingin mengurutkan depo berdasarkan satu metrik (mis. Call Achievement) untuk menemukan depo terbaik/terburuk.
3. Sebagai manajer, saya ingin tetap bisa beralih ke tampilan kartu asli Marketama kapan saja.
4. Sebagai manajer, saya ingin nama depo tetap terlihat saat menggeser tabel ke kanan.

## Implementation Decisions

- Tidak ada perhitungan baru: tabel memakai data `SummaryDepo::kartu()` yang sudah dikirim controller sebagai prop `kartu` (deferred). Tab Tabel dan Kartu berbagi prop yang sama sehingga pindah tab tidak memicu request baru.
- Tab diimplementasikan dengan komponen `Tabs` yang ada; render tabel memakai organism `DataTable` bila mendukung kolom sticky dan baris total, bila tidak tambahkan prop baru (mis. `stickyFirstColumn`, `footerRow`) pada `DataTable` — jangan membuat salinan komponen.
- Styling kolom sticky dan sel capaian lewat class komponen di `resources/css/app.css` (`@layer components`), bukan utility Tailwind panjang di template.
- Baris total dihitung di frontend dari `kartu` (jumlah untuk total; rata-rata berbobot hari kerja untuk AVG) atau, bila perhitungan berbobot rumit/berisiko selisih, ditambahkan di `SummaryDepo::kartu()` sebagai prop `totalNasional`; putuskan saat implementasi dengan mencocokkan hasilnya ke angka kartu.
- Menu sidebar dan config `marketama.php` tidak berubah (tetap satu menu `summary_all_depo`).

## Testing Decisions

- Feature test `SummaryDepoTest`: respons `/summary_all_depo` tetap berisi prop yang dibutuhkan tab Tabel (tidak ada prop baru yang rusak) dan `tampilan` tidak valid jatuh ke `kartu`.
- Spec Vitest untuk komponen tabel: render satu baris per depo, urutan kolom sesuai kelompok, warna capaian sesuai `warnaCapai`, baris total sesuai.
- Verifikasi manual di browser: pindah tab Kartu/Tabel, ganti filter tanggal, cari depo, sort kolom, geser tabel horizontal (kolom depo tetap terlihat), lebar sempit.

## Out of Scope

- Menghapus atau mengubah tampilan kartu asli Marketama.
- Grafik batang per depo dan form target nasional di halaman yang sama.
- Ekspor tabel ke CSV/Excel.
- Mengubah perhitungan metrik di `SummaryDepo`.
