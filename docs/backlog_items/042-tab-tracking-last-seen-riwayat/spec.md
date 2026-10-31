# Tab Penghubung Tracking Last Seen ↔ Tracking History Location

## Problem Statement
Grup Motoris punya dua pasang menu tracking yang menjawab pertanyaan yang sama ("sales/koordinator ada di mana?") dari dua sudut:

- **Tracking Last Seen Sales / Koordinator** (`tracking_sales`, `tracking_koordinator`): tabel posisi GPS terakhir tiap karyawan aktif (`Menu/Show` + `SumberLastSeen`).
- **Tracking History Sales / Koordinator Location** (`tracking_sales_history_location`, `tracking_koordinator_history_location`): peta rute satu orang pada satu tanggal (`Tracking/History.vue`, `TrackingSalesController` / `TrackingKoordinatorController`).

Alur kerja yang wajar berurutan: lihat siapa yang terakhir terlihat lama atau di tempat aneh, lalu buka rutenya. Saat ini pindah harus lewat sidebar, dan di halaman History orang serta tanggalnya harus dipilih ulang dari daftar.

Kedua halaman tidak bisa dilebur jadi satu rute bertab seperti spec 011/015/016: render stack-nya berbeda (tabel generik vs peta Leaflet), dan hak aksesnya terpisah (`hak:tracking_sales` vs `hak:tracking_sales_history_location`) sehingga role yang hanya punya salah satunya tidak boleh tiba-tiba mendapat yang lain.

## Solution
Satukan dari sisi navigasi saja, tanpa mengubah rute, slug, maupun hak:

1. **Tab penghubung** di atas kedua halaman pada setiap pasangan: "Last Seen" dan "Riwayat Lokasi". Tab adalah tautan antar-URL (kunjungan Inertia), bukan state di satu halaman. Tab hanya menampilkan halaman yang boleh dibuka akun; kalau akun hanya boleh membuka satu dari pasangan itu, baris tab tidak tampil sama sekali.
2. **Tombol "Lihat riwayat"** di kolom biasa "Riwayat" (bukan kolom aksi) pada setiap baris tabel Last Seen yang sudah punya posisi. Tombol membuka drawer dari kanan berisi peta rute orang itu pada tanggal posisi terakhirnya (tanggal bisa diganti di drawer), dengan tautan "Buka halaman Riwayat Lokasi" ke halaman penuh (`?sales_id=…&tanggal=…` atau `?koordinator_id=…&tanggal=…`). Tombol hanya muncul bila akun boleh membuka halaman Riwayat.
3. **Halaman Riwayat menerima pilihan awal** dari query string: kalau `sales_id`/`koordinator_id` ada di daftar, orang itu yang terpilih saat halaman dibuka (walaupun tidak punya titik GPS di tanggal itu), menggantikan pilihan otomatis "orang pertama yang punya data".

4. **Sidebar satu entri per pasangan**: "Lacak Motoris" dan "Tracking Koordinator" menggantikan empat entri lama. Entri riwayat disembunyikan dari sidebar selama akun boleh membuka Last Seen-nya; akun yang hanya punya hak riwayat tetap melihat entri riwayat sendiri. Penanda menu aktif dan breadcrumb tetap benar di halaman riwayat, dan pencarian sidebar tetap menemukan judul lama ("Tracking History Sales Location"). Matriks hak role tetap per slug (dua hak terpisah).

## User Stories
1. Sebagai admin yang memantau sales, saya ingin berpindah dari Last Seen ke Riwayat Lokasi (dan sebaliknya) dengan satu klik tab, supaya tidak perlu mencari menunya di sidebar.
2. Sebagai admin yang melihat sales terakhir terlihat jam 10 pagi, saya ingin klik "Lihat riwayat" di barisnya dan langsung melihat rute sales itu pada tanggal tersebut, tanpa memilih ulang nama dan tanggal.
3. Sebagai admin yang sama, untuk koordinator saya ingin perilaku yang sama persis.
4. Sebagai pemilik role yang hanya memberi hak Last Seen (tanpa Riwayat), saya tidak ingin anggota role itu melihat tab atau tautan ke halaman yang tidak boleh mereka buka.
5. Sebagai pengguna yang membagikan tautan "Lihat riwayat" (URL berisi `sales_id` dan `tanggal`), saya ingin penerimanya membuka orang dan tanggal yang sama.

## Implementation Decisions
- **Config** (`config/marketama.php`): kunci baru `tab_halaman`, daftar kelompok halaman yang saling terhubung lewat tab, masing-masing `slug => label` dalam urutan tab:
  - `tracking_sales` → "Last Seen", `tracking_sales_history_location` → "Riwayat Lokasi"
  - `tracking_koordinator` → "Last Seen", `tracking_koordinator_history_location` → "Riwayat Lokasi"
- **Shared prop** `tabHalaman` di `HandleInertiaRequests`: untuk `currentSlug`, cari kelompok yang memuatnya, saring dengan `Akses::bisa($user, $slug)`, kembalikan `[{slug, label}]`; kosong bila slug tidak di kelompok mana pun atau sisa tab kurang dari dua. Logika di `Akses::tabHalaman()` supaya bisa diuji.
- **Molecule baru** `resources/js/Components/ui/molecules/TabsHalaman.vue`: membaca `tabHalaman` dan `currentSlug` dari page props, merender `<Tabs>` yang sudah ada dan `router.visit('/' + slug)` saat tab dipilih. Dipakai di `Menu/Show.vue` dan `Tracking/History.vue` (dua tempat → komponen, sesuai aturan atomic design). Tidak tampil bila `tabHalaman` kosong.
- **Tombol "Lihat riwayat"**: di `Menu/Show.vue`, kolom `riwayat` (config) dengan slot sel `#cell-riwayat` untuk tabel `tracking_last_seen_sales` / `tracking_last_seen_koordinator` berisi `<Button>` yang mengisi `riwayatTerpilih` (orang, slug riwayat, parameter id, tanggal dari 10 karakter pertama `terakhir_terlihat`); baris "Belum ada data" tanpa tombol. `Modal` placement kanan memuat partial baru `Pages/Menu/partials/RuteHarianPeta.vue`, yang mengambil titik dari endpoint `/{slug-riwayat}/locations` dan menggambarnya dengan `gambarRute` (`resources/js/Support/rute.js`, diekstrak dari `Tracking/History.vue` dan dipakai bersama). Tujuan dan izinnya dibaca dari `tabHalaman`, jadi tidak ada prop hak tambahan.
- **Pilihan awal di History**: `TrackingSalesController@index` / `TrackingKoordinatorController@index` menambah prop `pilihId` = `(int) query(idParam)` atau null. `History.vue` mengisi `aktifId` awal dari `pilihId`; `pilihOtomatis` pada pemanggilan pertama memilih orang itu (dan menggulirnya ke tampilan) bila ada di daftar, baru setelahnya kembali ke aturan biasa.
- **Sidebar**: `Akses::menu()` memanggil `satukanTab()` yang memakai urutan `tab_halaman`: slug pertama kelompok (induk) tampil dengan `judul_sidebar` (config item menu) dan membawa `serumpun` (slug lain yang bisa dibuka akun); slug lain disembunyikan bila induknya bisa dibuka. `SidebarNavList.vue` dan `Breadcrumb.vue` menganggap item aktif bila `currentSlug` ada di `serumpun`. `Akses::menuPencarian` menambah entri `serumpun` dengan judul aslinya. `daftarMenu()` (matriks hak role) tidak berubah.
- Tidak ada perubahan rute, slug, middleware hak, skema, maupun data.

## Testing Decisions
- Feature test:
  - `/tracking_sales` dan `/tracking_sales_history_location` (super admin) membawa `tabHalaman` dua entri dengan urutan Last Seen, Riwayat Lokasi; sama untuk koordinator.
  - Role yang hanya punya hak `tracking_sales` mendapat `tabHalaman` kosong di `/tracking_sales`.
  - Halaman lain (mis. `/home`) mendapat `tabHalaman` kosong.
  - Sidebar admin berisi `tracking_sales`/`tracking_koordinator` (judul "Lacak Motoris"/"Tracking Koordinator", `serumpun` berisi slug riwayat) tanpa slug riwayat; role yang hanya punya hak riwayat tetap melihat entri riwayat; pencarian sidebar masih menemukan halaman riwayat; matriks hak role (`Akses::semuaSlug`) tetap memuat keduanya.
  - `/tracking_sales_history_location?sales_id=11&tanggal=…` mengirim `pilihId` = 11 dan `tanggal` sesuai; tanpa query `pilihId` null. Sama untuk koordinator.
- Vitest `TabsHalaman.spec.js`: merender tab dari props, menandai tab aktif, memanggil `router.visit` ke slug lain, tidak merender apa pun bila kosong.
- Vitest `RuteHarianPeta.spec.js`: memuat titik orang & tanggal, menggambar rute, pesan bila tanpa titik, tautan halaman penuh.
- Verifikasi manual di browser dev server user: klik tab dua arah, klik "Lihat riwayat" membuka drawer berisi rute, ganti tanggal di drawer, buka halaman penuh.

## Out of Scope
- Mengubah matriks hak role (hak tetap per slug).
- Menjadikan Peta Sebaran Sales tab ketiga.
- Mengubah tampilan tabel Last Seen atau peta History selain tab dan tautan di atas.
