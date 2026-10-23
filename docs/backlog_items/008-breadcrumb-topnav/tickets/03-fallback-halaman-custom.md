## Parent

docs/backlog_items/008-breadcrumb-topnav/spec.md

## What to build

Pastikan breadcrumb tetap tampil benar di halaman-halaman yang slug URL-nya tidak otomatis match ke `config('marketama.menu')` lewat `request()->path()`:

1. `menu.coming_soon` (`Route::view('/loading_barang/add', 'menu.coming_soon', ['title' => ..., 'back' => 'loading_barang'])` dan sejenisnya, `routes/web.php:32-33`) — pass parameter `back` sebagai atribut `slug` ke `<x-ui.molecules.breadcrumb>` di view `menu/coming_soon.blade.php`, supaya breadcrumb menunjukkan grup/menu asal (`loading_barang`/`barang_canvassing`) bukan breadcrumb kosong.
2. `TrackingKoordinatorController`/`TrackingSalesController` (`/tracking_koordinator_history_location`, `/tracking_sales_history_location`) dan `DesignAreaController` (`/master_area/design_area`, route name `design-area.index`) — verifikasi apakah `request()->path()` pada route ini match langsung ke slug yang ada di `config('marketama.menu')`. Kalau tidak match (mis. karena prefix `master_area/design_area` berbeda dari slug datar di config), pass atribut `slug` eksplisit ke komponen breadcrumb di view masing-masing (`resources/views/tracking/*.blade.php`, `resources/views/area/*.blade.php`) dengan slug yang benar dari `config('marketama.menu')`.

## Acceptance criteria

- [x] Mengunjungi `/loading_barang/add` menampilkan breadcrumb yang sama seolah berada di menu `loading_barang` (bukan kosong).
- [x] Mengunjungi `/barang_canvassing/add` menampilkan breadcrumb yang sama seolah berada di menu `barang_canvassing`.
- [x] Mengunjungi halaman tracking koordinator dan tracking sales masing-masing menampilkan breadcrumb yang benar (diverifikasi manual terhadap slug di `config/marketama.php`, dipass eksplisit kalau `request()->path()` tidak match).
- [x] Mengunjungi `/master_area/design_area` menampilkan breadcrumb yang benar untuk menu Design Polygon Area.
- [x] Feature test yang menutupi ketiga rute custom ini plus kedua route `coming_soon`.

## Blocked by

- Ticket 01 (breadcrumb helper & komponen Blade) — butuh komponen dan parameter `slug` eksplisit yang disediakan di sana.
