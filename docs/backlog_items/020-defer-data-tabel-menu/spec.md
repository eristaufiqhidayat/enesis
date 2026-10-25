# Pakai Inertia `defer()` untuk Data Tabel Menu

## Problem Statement

Semua halaman menu di aplikasi ini dirender lewat satu stack Inertia + Vue: controller mengembalikan `Inertia::render(...)` dan baru mengirim respons setelah **seluruh** data props (termasuk query tabel yang berat — join, paginasi, KPI) selesai dihitung di server. Ini berlaku untuk:

- `MenuController@show` ([app/Http/Controllers/MenuController.php:16-148](../../../app/Http/Controllers/MenuController.php)) — menu config-driven generik, query tiap tabel di `$konfigTabel` (termasuk lewat kelas `SumberTabel` yang join ke tabel lain) dijalankan dalam satu request sebelum render.
- Controller custom yang query berat: `SummaryDepoController`, `RekapPencapaianSkuController`, `MotorisReportController`, `KuotaMotorisController`.

Navigasi sidebar memakai `<Link>` Inertia ([resources/js/Components/ui/organisms/partials/SidebarNavList.vue:61](../../../resources/js/Components/ui/organisms/partials/SidebarNavList.vue)), yang request-nya menunggu *seluruh* payload di atas selesai sebelum Vue me-render halaman baru. Tidak ada progress bar/indicator di [resources/js/app.js](../../../resources/js/app.js), jadi saat query lambat (tabel besar, join, atau banyak tab), user hanya melihat halaman "diam" tanpa feedback sampai transisi selesai.

Root cause: tidak ada pemisahan antara data yang murah (konfigurasi halaman, daftar tab, filter) dan data yang mahal (baris tabel hasil query DB), padahal keduanya dikirim sekaligus dalam satu respons Inertia.

## Solution

Pakai fitur `Inertia::defer()` (Inertia v2, tersedia di `inertiajs/inertia-laravel` ^3.5 dan `@inertiajs/vue3` ^3.9.1 yang sudah terpasang di proyek ini) untuk memisahkan prop berat (`tables`) dari sisa payload halaman:

1. Payload awal (page config, slug, activeTab, search, filter) dikirim secepatnya — halaman tujuan langsung tampil (termasuk tab, form filter, sidebar) tanpa menunggu query tabel.
2. Prop `tables` dibungkus `Inertia::defer(fn () => ..., group: ...)` sehingga dikirim lewat request susulan setelah halaman ter-mount.
3. Di sisi Vue, bagian yang menampilkan tabel dibungkus komponen `<Deferred>` dari `@inertiajs/vue3` dengan skeleton/placeholder loading sebagai fallback selama data belum datang.
4. Pola yang sama diterapkan ke seluruh controller yang query berat sebelum render: `MenuController`, `SummaryDepoController`, `RekapPencapaianSkuController`, `MotorisReportController`, `KuotaMotorisController` — bukan hanya halaman menu generik.

Catatan penting dari riset awal (lihat diskusi yang melatari spec ini):
- `defer()` hanya berlaku pada **initial visit** (klik `<Link>` sidebar atau load langsung). Partial reload yang sudah dipakai sekarang untuk "Cari" (`submitSearch`), ganti tab (`pindahTab`), dan pagination di [resources/js/Pages/Menu/Show.vue](../../../resources/js/Pages/Menu/Show.vue) memakai `router.get(...)` biasa — ini **tidak diubah** oleh tiket ini, hanya transisi awal antar menu yang jadi lebih cepat.
- Kontrol filter (search box, date filter, dropdown depo/koordinator) saat ini di-gate oleh `tables.length` ([resources/js/Pages/Menu/Show.vue:143](../../../resources/js/Pages/Menu/Show.vue) & [:151](../../../resources/js/Pages/Menu/Show.vue)) — kalau `tables` deferred, kontrol-kontrol ini harus direstruktur supaya tidak ikut menunggu data tabel datang (supaya tidak "muncul lalu geser" begitu data masuk).

## User Stories

1. Sebagai admin yang berpindah-pindah menu lewat sidebar, saya ingin halaman tujuan langsung tampil (judul, tab, form filter) tanpa menunggu query tabel besar selesai, supaya navigasi terasa responsif.
2. Sebagai admin yang membuka menu dengan data besar (join, banyak baris), saya ingin melihat skeleton/placeholder loading di area tabel selagi data diambil, supaya saya tahu halaman tidak macet.
3. Sebagai admin yang melakukan pencarian, ganti tab, atau pindah halaman paginasi di dalam satu menu, saya ingin perilaku yang sudah ada (reload tabel aktif saja) tetap sama seperti sekarang — tiket ini tidak boleh memperlambat atau mengubah interaksi tersebut.
4. Sebagai developer yang menambah menu baru di `config/marketama.php`, saya ingin pola defer+skeleton ini otomatis berlaku lewat mekanisme generik `MenuController`/`Show.vue`, tanpa perlu menulis ulang logic defer di setiap menu baru.

## Implementation Decisions

- **Scope awal (pola dasar):** terapkan dulu di `MenuController@show` + `Menu/Show.vue` karena ini mencakup mayoritas (~119) menu lewat satu config generik — jadi dampaknya paling luas untuk effort paling kecil.
- **Pembungkus:** `Inertia::defer(fn () => $tables, 'tables')` menggantikan nilai langsung `'tables' => $tables` di `MenuController::show` ([app/Http/Controllers/MenuController.php:141](../../../app/Http/Controllers/MenuController.php)). Closure menjalankan loop query yang sudah ada (baris 57-133), tidak ada perubahan logic query.
- **Vue:** bungkus blok tabel (form filter perlu direstruktur agar tidak bergantung pada `tables.length`, lihat di atas) dengan `<Deferred>` dari `@inertiajs/vue3`; tambah komponen skeleton baru di `resources/js/Components/ui/molecules/` (mis. `TableSkeleton.vue`) mengikuti aturan atomic design di `CLAUDE.md` — dipakai lewat slot `fallback` milik `<Deferred>`.
- **Rollout ke controller custom:** setelah pola di `MenuController` terbukti jalan, terapkan pola defer+skeleton yang sama (prop berat dibungkus `defer()`, halaman Vue terkait pakai `<Deferred>` + skeleton) ke `SummaryDepoController`, `RekapPencapaianSkuController`, `MotorisReportController`, `KuotaMotorisController` satu per satu.
- **Tidak mengubah mekanisme partial reload yang sudah ada** (`submitSearch`, `pindahTab`, pagination `links`) — defer hanya mempengaruhi initial page visit.
- **Grouping:** evaluasi saat implementasi apakah tabel-tabel dalam satu halaman bertab perlu di-defer sebagai group terpisah (supaya tab dengan tabel ringan tidak menunggu tab lain) — default awal: satu group `tables` per halaman, cukup untuk mayoritas menu yang hanya punya satu tabel aktif per saat.

## Testing Decisions

- Feature test (`tests/Feature/MenuTest.php`) untuk memverifikasi response awal (sebelum deferred props di-resolve) tidak mengandung `tables`, dan response ke header `X-Inertia-Partial-Data` yang menyertakan `tables` mengembalikan data yang sama seperti sebelumnya (regresi nilai, bukan cuma struktur).
- Verifikasi manual di browser (pola yang sudah dipakai di tiket migrasi Inertia lain di proyek ini, belum ada test runner JS): pindah menu lewat sidebar menampilkan skeleton sebelum data tabel muncul, form filter/tab tetap berfungsi normal begitu data datang, pencarian/pindah tab/pagination di dalam satu menu tidak menampilkan skeleton (karena bukan initial visit).

## Out of Scope

- Mengubah logic query tabel itu sendiri (join, filter, KPI) — tiket ini murni soal kapan dan bagaimana data dikirim ke client, bukan apa yang di-query.
- Menambahkan caching/optimasi query database (index, eager loading tambahan) — di luar scope, bisa jadi tiket terpisah kalau ditemukan menu yang masih lambat setelah defer diterapkan.
- Progress bar/indicator global untuk navigasi Inertia (mis. `@inertiajs/progress`) — di luar scope spec ini, meski bisa melengkapi UX defer di masa depan.

## Further Notes

- Spec ini berasal dari riset ad-hoc (bukan laporan bug) yang mengonfirmasi bahwa navigasi menu memang menunggu seluruh query tabel tujuan selesai sebelum berpindah — lihat kutipan kode di Problem Statement.
- Versi Inertia yang terpasang (`@inertiajs/vue3` ^3.9.1, `inertiajs/inertia-laravel` ^3.5) sudah mendukung `defer()`/`<Deferred>` (fitur Inertia v2), jadi tidak perlu upgrade dependency untuk tiket ini.
