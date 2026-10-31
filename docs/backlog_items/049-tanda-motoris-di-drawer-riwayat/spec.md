# Tanda Motoris di Drawer Riwayat Lacak Motoris

## Problem Statement
Dari drawer Riwayat, koordinator bisa melihat rute GPS, kunjungan outlet, dan nilai penjualan seorang motoris. Bila ada yang janggal (lokasi palsu, kunjungan beruntun tanpa jeda, penjualan yang terlihat fiktif), tidak ada tempat untuk mencatat kecurigaan itu di sistem. Koordinator harus mencatat di luar aplikasi, sehingga temuan tidak terlacak, tidak bisa dilihat koordinator/admin lain, dan tidak ada riwayat siapa menandai siapa dan kapan.

## Solution
Tambahkan fitur **tanda motoris** di drawer Riwayat (khusus Sales/motoris):

- Tombol **"Tandai motoris"** membuka form kecil: **kategori** (pilih satu) dan **catatan** (opsional, maks 500 karakter). Tanda berlaku untuk motoris tersebut pada **tanggal yang sedang dilihat** di drawer.
- Di bawah toolbar drawer tampil daftar tanda aktif pada tanggal itu: kategori (lencana merah), catatan, siapa yang menandai dan kapan.
- Pemberi tanda (dan akun dengan hak Hapus) dapat **mencabut** tanda. Pencabutan bersifat soft: baris tetap tersimpan sebagai jejak, hanya tidak tampil lagi.
- Setiap tambah dan cabut tercatat di riwayat admin (`riwayat_admin`).

Kategori tanda (config, dapat ditambah): **Penjualan fiktif**, **Lokasi / GPS palsu**, **Kunjungan tidak wajar**, **Potensi kecurangan lainnya**.

## Asumsi desain (bisa diubah)
1. Tanda melekat pada **motoris + tanggal** (bukan per kunjungan), karena kecurigaan sering mencakup pola satu hari.
2. Satu motoris-tanggal boleh punya beberapa tanda (kategori sama pun boleh), masing-masing dari pemberi sendiri.
3. Hak akses memakai menu **Tracking Last Seen Sales** (`tracking_sales`): **Lihat** untuk melihat tanda, **Tambah** untuk memberi tanda dan mencabut tanda sendiri, **Hapus** untuk mencabut tanda siapa pun. Role bawaan **Koordinator** otomatis mendapat Tambah (migration data dan `RoleBawaan`); admin dapat mencabutnya dari menu Role.
4. Akun hanya bisa menandai dan melihat tanda motoris dalam **cakupan role**-nya (downline / depo / diri / semua).
5. Tanda tidak boleh bertanggal di masa depan.
6. Tanda belum ditampilkan di tabel Lacak Motoris, laporan, atau aplikasi HP, dan motoris tidak mengetahuinya (lihat Out of Scope).

## User Stories
1. Sebagai koordinator, saya ingin menandai motoris bawahan saya dari drawer Riwayat dengan kategori dan catatan, supaya kecurigaan terekam di sistem.
2. Sebagai koordinator, saya ingin melihat tanda yang sudah diberikan pada tanggal itu (oleh saya atau orang lain), supaya tidak menandai dua kali tanpa sadar.
3. Sebagai koordinator, saya ingin mencabut tanda yang keliru dari akun saya.
4. Sebagai admin, saya ingin tahu siapa yang memberi dan mencabut tanda serta kapan, supaya ada jejak audit.
5. Sebagai admin, saya ingin mengatur lewat menu Role siapa yang boleh memberi tanda dan siapa yang boleh mencabut tanda orang lain.
6. Sebagai koordinator, saya tidak ingin bisa menandai atau melihat tanda motoris di luar cakupan saya.

## Implementation Decisions
- **Tabel baru `tanda_motoris`**: `id`, `karyawan_id` (mk_karyawan, motoris), `tanggal` (date), `kategori` (string 40, kode dari config), `catatan` (text nullable), `dibuat_oleh` (users.id), `dibuat_nama` (snapshot username/nama, agar jejak tetap terbaca bila akun berubah), `dicabut_at`, `dicabut_oleh` (nullable), `timestamps`; index `(karyawan_id, tanggal)`.
- **Config** `marketama.tanda_motoris` (kode => label).
- **Controller** `TandaMotorisController` dengan tiga aksi JSON, rute di grup auth sebelum catch-all:
  - `GET /tracking_sales/tanda?sales_id=&tanggal=` (`hak:tracking_sales`): `{ tanda: [...], bisa_tambah, kategori }`; tiap tanda memuat `bisa_cabut`.
  - `POST /tracking_sales/tanda` (`hak:tracking_sales,tambah`): validasi `sales_id` (motoris kategori sales dalam cakupan, selain itu 404), `tanggal` (date, tidak di masa depan), `kategori` (kunci config), `catatan` (opsional, maks 500). Mengembalikan 201 + tanda.
  - `DELETE /tracking_sales/tanda/{tanda}` (`hak:tracking_sales,tambah`): motoris harus dalam cakupan (404); hanya pemberi tanda atau akun dengan hak Hapus (403 bila bukan); tanda yang sudah dicabut 404.
- **Audit**: `Akses::catat` (`objek = tanda_motoris`, aksi `tambah` / `hapus`) dengan ringkasan nama motoris, tanggal, dan kategori.
- **Hak bawaan**: migration data memberi `tambah` pada `tracking_sales` untuk role bawaan koordinator; `RoleBawaan::pasang` melakukan hal yang sama untuk instalasi baru.
- **Vue**: partial baru `Pages/Menu/partials/TandaMotoris.vue` (daftar, form, cabut; fetch + `X-CSRF-TOKEN`) dipasang di `RuteHarianPeta.vue` lewat prop `tandaUrl` yang hanya diisi untuk tabel Sales (`Menu/Show.vue`); Koordinator tidak punya fitur ini. Gaya lewat kelas komponen di `app.css`. Tombol hanya tampil bila `bisa_tambah`.

## Testing Decisions
- Feature test (`TandaMotorisTest`): daftar kosong dan terisi (hanya tanda aktif, tanggal yang diminta); tambah menyimpan baris + `dibuat_nama` + riwayat admin; role hanya-lihat ditolak 403; koordinator tidak bisa menandai motoris di luar downline-nya (404) atau akun non-sales; validasi (kategori tak dikenal, tanggal masa depan, catatan terlalu panjang); cabut oleh pemberi, ditolak untuk orang lain tanpa hak Hapus, diizinkan dengan hak Hapus; cabut soft (baris tetap, `dicabut_at` terisi) dan tercatat; tanda dicabut tidak tampil lagi; role bawaan koordinator mendapat hak Tambah (migration dan `RoleBawaan`).
- Vitest (`TandaMotoris.spec.js`): daftar tampil, tombol Tandai hanya bila `bisa_tambah`, kirim form dengan header CSRF dan memuat ulang daftar, tampilkan galat validasi, cabut dengan konfirmasi, tombol Cabut sesuai `bisa_cabut`. `RuteHarianPeta.spec.js`: partial hanya dirender bila `tandaUrl` diisi.

## Out of Scope
- Lencana atau filter "ditandai" di tabel Lacak Motoris, laporan rekap tanda, notifikasi, atau persetujuan/tindak lanjut (status investigasi).
- Tanda per kunjungan atau per transaksi.
- Menampilkan tanda di aplikasi HP motoris atau menghalangi motoris bertransaksi.
- Tanda untuk Koordinator (hanya Sales).
- Mengubah isi tanda setelah dibuat (cabut lalu buat ulang).
