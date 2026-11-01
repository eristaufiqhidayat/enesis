# Tanda Outlet

## Problem Statement
Dari daftar kunjungan outlet di drawer Riwayat Lacak Motoris, koordinator bisa melihat outlet mana saja yang dikunjungi seorang motoris. Bila ada outlet yang terlihat janggal (mis. jarak antar kunjungan yang mustahil dekat, lihat spec 054, atau indikasi lain seperti outlet fiktif/alamat tidak sesuai), tidak ada tempat untuk mencatat kecurigaan itu di sistem — sama seperti kondisi motoris sebelum fitur **tanda motoris** (spec 049) dibuat. Berbeda dari motoris, kecurigaan terhadap outlet biasanya melekat pada **outlet itu sendiri**, bukan pada satu kunjungan: outlet fiktif tetap fiktif siapa pun yang mengunjunginya dan kapan pun.

## Solution
Tambahkan fitur **tanda outlet**, melekat pada **outlet (master `mk_outlet`), bukan per kunjungan**:

- Dari daftar kunjungan outlet di drawer Riwayat (Sales), setiap baris outlet punya tombol/aksi **"Tandai outlet"** yang membuka form kecil: **kategori** (pilih satu) dan **catatan** (opsional, maks 500 karakter).
- Outlet yang sudah punya tanda aktif menampilkan lencana di baris kunjungannya (di daftar drawer dan di popover peta), dan juga di menu **Master Data > All Outlet** (`outlet_all`) sebagai kolom/lencana "Ditandai" — karena tanda melekat ke outlet, bukan ke drawer tertentu.
- Pemberi tanda (dan akun dengan hak Hapus) dapat **mencabut** tanda. Pencabutan bersifat soft: baris tetap tersimpan sebagai jejak.
- Setiap tambah dan cabut tercatat di riwayat admin (`riwayat_admin`), mengikuti pola `tanda_motoris`.

Kategori tanda (config, dapat ditambah): **Outlet fiktif**, **Alamat/lokasi tidak sesuai**, **Pola kunjungan tidak wajar** (lihat spec 054), **Potensi kecurangan lainnya**.

## Asumsi desain (bisa diubah)
1. Tanda melekat pada **outlet (mk_outlet.id)**, bukan per kunjungan atau per motoris — dikonfirmasi oleh pengguna. Satu outlet boleh punya beberapa tanda aktif (kategori sama pun boleh), masing-masing dari pemberi sendiri.
2. Tombol "Tandai outlet" tampil dari drawer Riwayat Lacak Motoris (tempat kecurigaan biasanya pertama terlihat) **dan** dari menu Master Data > All Outlet (tempat outlet dikelola).
3. Hak akses memakai menu **All Outlet** (`outlet_all`): **Lihat** untuk melihat tanda, **Tambah** untuk memberi tanda dan mencabut tanda sendiri, **Hapus** untuk mencabut tanda siapa pun — konsisten dengan tanda melekat ke entitas outlet (dikelola lewat menu outlet), bukan ke menu tracking. Role bawaan yang sudah punya hak Tambah di `outlet_all` otomatis bisa menandai; tidak perlu migration hak tambahan.
4. Hanya outlet yang sudah ada di **master `mk_outlet`** yang bisa ditandai. Outlet baru dari HP yang belum disetujui (`trx_outlet_baru`, belum punya baris `mk_outlet`) tidak bisa ditandai sampai disetujui — kunjungan ke outlet seperti ini di drawer tidak menampilkan tombol Tandai.
5. Tanda tidak dibatasi cakupan role (berbeda dari tanda motoris): karena melekat ke outlet yang bisa dikunjungi motoris mana pun, semua akun dengan hak Lihat `outlet_all` bisa melihatnya; hanya pemberian/pencabutan yang dibatasi hak Tambah/Hapus seperti biasa.
6. Tanda belum ditampilkan di laporan rekap atau aplikasi HP, dan outlet/motoris tidak mengetahuinya.

## User Stories
1. Sebagai koordinator, saya ingin menandai outlet yang mencurigakan langsung dari drawer Riwayat, dengan kategori dan catatan, supaya kecurigaan terekam di sistem dan terlihat siapa pun yang mengunjungi outlet itu berikutnya.
2. Sebagai koordinator, saya ingin melihat lencana outlet yang sudah ditandai di daftar kunjungan dan popover peta, supaya langsung tahu tanpa membuka form.
3. Sebagai admin, saya ingin melihat semua outlet yang ditandai dari menu All Outlet, lepas dari motoris atau tanggal kunjungannya.
4. Sebagai pemberi tanda, saya ingin mencabut tanda yang keliru.
5. Sebagai admin, saya ingin tahu siapa yang memberi dan mencabut tanda serta kapan, supaya ada jejak audit.

## Implementation Decisions
- **Tabel baru `tanda_outlet`**: `id`, `outlet_id` (mk_outlet.id), `kategori` (string 40, kode dari config), `catatan` (text nullable), `dibuat_oleh` (users.id), `dibuat_nama` (snapshot), `dicabut_at`, `dicabut_oleh` (nullable), `timestamps`; index `(outlet_id)`.
- **Config** `marketama.tanda_outlet` (kode => label).
- **Controller** `TandaOutletController` dengan tiga aksi JSON, rute di grup auth sebelum catch-all:
  - `GET /outlet_all/tanda?outlet_id=` (`hak:outlet_all`): `{ tanda: [...], bisa_tambah, kategori }`; tiap tanda memuat `bisa_cabut`.
  - `POST /outlet_all/tanda` (`hak:outlet_all,tambah`): validasi `outlet_id` (harus ada di `mk_outlet`, selain itu 404), `kategori` (kunci config), `catatan` (opsional, maks 500). 201 + tanda.
  - `DELETE /outlet_all/tanda/{tanda}` (`hak:outlet_all,tambah`): hanya pemberi tanda atau akun dengan hak Hapus (403 bila bukan); tanda yang sudah dicabut 404.
- **Audit**: `Akses::catat` (`objek = tanda_outlet`, aksi `tambah` / `hapus`) dengan ringkasan nama outlet dan kategori.
- **Lencana "Ditandai"**: `SumberTabel` untuk `outlet_all` (lihat `App\Support\Menu\Motoris\SumberOutletNoo`/sejenis untuk pola join serupa) menambah kolom boolean `ditandai` dari exists-subquery ke `tanda_outlet` aktif. `KunjunganHarian::untuk` menambah `outlet_ditandai` (bool) per baris; `RuteHarianPeta.vue` dan popover peta (`rute.js`) menampilkan lencana bila true.
- **Vue**: partial baru `Pages/Menu/partials/TandaOutlet.vue` (form + daftar tanda aktif outlet itu + cabut), dibuka sebagai modal/drawer kecil dari tombol pada baris kunjungan outlet (`RuteHarianPeta.vue`) dan dari kolom aksi `outlet_all` (`Menu/Show.vue`, pola yang sama dengan drawer lain di halaman itu).

## Testing Decisions
- Feature test (`TandaOutletTest`): daftar kosong dan terisi (hanya tanda aktif outlet yang diminta); tambah menyimpan baris + `dibuat_nama` + riwayat admin; 404 untuk `outlet_id` yang tidak ada di `mk_outlet`; role hanya-lihat ditolak 403; validasi (kategori tak dikenal, catatan terlalu panjang); cabut oleh pemberi, ditolak untuk orang lain tanpa hak Hapus, diizinkan dengan hak Hapus; cabut soft dan tercatat; tanda dicabut tidak tampil lagi dan lencana `ditandai` hilang; lencana muncul di `outlet_all` dan di `kunjungan.outlet_ditandai` dari `KunjunganHarian` untuk outlet yang ditandai.
- Vitest (`TandaOutlet.spec.js`): daftar tampil, tombol Tandai hanya bila `bisa_tambah`, kirim form dengan header CSRF dan memuat ulang daftar, tampilkan galat validasi, cabut dengan konfirmasi. `RuteHarianPeta.spec.js`: lencana "Ditandai" tampil pada baris kunjungan dengan `outlet_ditandai: true`.

## Out of Scope
- Tanda per kunjungan/transaksi (ditolak secara eksplisit oleh pengguna — tanda melekat ke outlet).
- Menandai outlet baru (`trx_outlet_baru`) yang belum disetujui — hanya outlet yang sudah ada di `mk_outlet`.
- Notifikasi otomatis, memblokir transaksi di outlet itu, atau status investigasi/tindak lanjut.
- Menampilkan tanda di aplikasi HP motoris.
- Mengubah isi tanda setelah dibuat (cabut lalu buat ulang).
- Laporan/rekap "Outlet Ditandai" tersendiri (bisa menyusul, mengikuti pola spec 051 untuk motoris, sebagai item backlog lain bila dibutuhkan).
