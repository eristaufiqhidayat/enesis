# Melihat Motoris yang Ditandai (Lencana, Rekap, dan Tanda Tanggal Lain)

## Problem Statement
Tanda motoris (spec 049) hanya bisa dilihat di drawer Riwayat untuk satu motoris dan satu tanggal. Koordinator harus sudah tahu motoris dan tanggalnya; tidak ada tempat untuk melihat siapa saja yang pernah ditandai, tanda di tanggal lain tidak terlihat sebelum tanggalnya dipilih, dan pola kecurigaan lintas hari tidak tampak. Log audit `riwayat_admin` mencatat tambah/cabut tanda tetapi tidak ditampilkan di halaman mana pun.

## Solution
Tiga tampilan yang saling melengkapi:

1. **Lencana "Ditandai" di tabel Lacak Motoris.** Kolom baru "Ditandai" berisi lencana merah "N tanda" (jumlah tanda aktif dalam 30 hari terakhir) pada motoris yang punya tanda. Mengklik lencana membuka drawer Riwayat pada tanggal tanda terakhir.
2. **Halaman rekap "Motoris Ditandai"**, ditempatkan sebagai **tab ketiga** di grup Lacak Motoris (di samping "Last Seen" dan "Riwayat Lokasi"; sidebar tetap satu entri). Tabelnya: tanggal, motoris, depo, koordinator, kategori, catatan, ditandai oleh, ditandai pada, status (Aktif / Dicabut), dan tautan "Lihat riwayat" yang membuka drawer. Bisa dicari (motoris, depo, koordinator, catatan, pemberi) dan disaring menurut status.
3. **"Tanda di tanggal lain"** di bagian Tanda motoris pada drawer: daftar tanda aktif motoris itu pada tanggal lain dalam 30 hari terakhir. Mengklik salah satu langsung memindahkan drawer ke tanggal tersebut.

Semua tampilan mengikuti cakupan role (hanya motoris dalam cakupan akun). Tanda yang dicabut tidak dihitung di lencana dan "tanggal lain", tetapi tetap tampil di rekap (status Dicabut) sebagai jejak.

## User Stories
1. Sebagai koordinator, saya ingin langsung melihat di tabel Lacak Motoris siapa saja yang sedang ditandai, tanpa membuka drawer satu per satu.
2. Sebagai koordinator, saya ingin mengklik lencana itu dan langsung melihat tanda terakhirnya.
3. Sebagai admin atau koordinator, saya ingin satu halaman rekap semua tanda (termasuk yang sudah dicabut) dengan pencarian dan filter status, untuk menelusuri riwayat penandaan.
4. Sebagai koordinator, saya ingin melihat dari rekap lalu membuka drawer motoris dan tanggal yang bersangkutan.
5. Sebagai koordinator yang sedang melihat satu tanggal, saya ingin tahu motoris itu pernah ditandai di tanggal lain dan melompat ke sana dengan sekali klik, supaya pola lintas hari terlihat.
6. Sebagai pemilik role, saya ingin hak lihat rekap diatur lewat menu Role seperti menu lain.

## Implementation Decisions
- **Lencana**: `SumberLastSeen::query` (hanya untuk kategori sales) menambah dua subquery ke `tanda_motoris` (aktif, tanggal dalam 30 hari terakhir): `ditandai` (jumlah) dan `ditandai_tanggal` (tanggal terbaru). Kolom config `ditandai` di `tracking_last_seen_sales` setelah `riwayat`. Jendela hari di config `marketama.tanda_motoris_hari` (30). `Menu/Show.vue` mengisi sel dengan `Badge` merah yang bisa diklik; klik membuka drawer pada `ditandai_tanggal`.
- **Rekap**: `pages.motoris_ditandai` (`Menu/Show` generik) dengan tabel `tanda_motoris_rekap` bersumber `SumberTandaMotoris` (`SumberTabel` + `SumberBerstatus` untuk filter Status), join `tanda_motoris` → `mk_karyawan` (alias `k`, dibatasi `Akses::batasiKaryawan`) → depo dan koordinator, urut tanggal lalu id terbaru. Slug baru `motoris_ditandai` ditambahkan di `menu`, `menu_motoris`, dan sebagai anggota ketiga kelompok `tab_halaman` sales; sidebar menyatukannya otomatis (`Akses::satukanTab`), pencarian sidebar dan breadcrumb tetap bekerja lewat `serumpun`.
- **Hak**: migration data memberi `lihat` pada `motoris_ditandai` untuk semua role yang punya `lihat` pada `tracking_sales` (role baru lewat `RoleBawaan` otomatis). Membuka drawer dari rekap/lencana memerlukan akses ke halaman Riwayat Lokasi dan `tracking_sales` (dibaca dari prop bersama `tabHalaman`).
- **`Menu/Show.vue`**: pemetaan tabel ke halaman riwayat dibuat eksplisit (`tracking_last_seen_sales` dan `tanda_motoris_rekap` → `tracking_sales_history_location` dengan `sales_id`; koordinator → `tracking_koordinator_history_location`) menggantikan asumsi "tab lain pertama", yang tidak lagi benar dengan tiga tab.
- **Tanda tanggal lain**: respons `GET /tracking_sales/tanda` bertambah `lainnya` (maks. 50; id, tanggal, kategori_label, oleh) untuk tanda aktif motoris itu dalam 30 hari terakhir selain tanggal yang diminta. `TandaMotoris.vue` menampilkannya (lipat/buka) dan mengirim event `pilih-tanggal`; `RuteHarianPeta.vue` mengganti tanggal drawer.
- **Backup**: `tanda_motoris` masuk grup "Pengaturan Enesis" dan daftar data pribadi di `DaftarTabel`.
- Tidak ada perubahan skema selain yang sudah ada (tabel `tanda_motoris`).

## Testing Decisions
- Feature test: `SumberLastSeen` mengisi `ditandai` dan `ditandai_tanggal` (hanya tanda aktif, hanya 30 hari terakhir, per motoris, tidak ada untuk koordinator); rekap memuat tanda aktif dan dicabut dengan kolom benar, dibatasi cakupan role, pencarian, dan filter status; akses halaman sesuai hak; `tabHalaman`, sidebar, dan `serumpun` memuat tab baru; `lainnya` pada endpoint tanda (tanggal lain, aktif saja, dalam jendela, cakupan role); migration hak (hanya role yang punya `tracking_sales`).
- Vitest: sel lencana (tampil, klik membuka drawer pada tanggal tanda terakhir, tanpa klik bila tanpa akses), sel riwayat rekap, `TandaMotoris` menampilkan tanda tanggal lain dan mengirim `pilih-tanggal`, `RuteHarianPeta` mengganti tanggal.

## Out of Scope
- Memfilter rekap menurut tanggal atau kategori (hanya pencarian teks dan status).
- Ekspor rekap, notifikasi, atau tindak lanjut (status investigasi).
- Lencana di tabel Tracking Koordinator, Peta Sebaran Sales, atau aplikasi HP.
- Menampilkan siapa yang mencabut dan kapan di rekap (tercatat di tabel, belum ditampilkan).
