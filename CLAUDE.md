# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 13 (PHP ^8.3) clone of the "Marketama Canvassing System" admin UI. The sidebar (9 groups, 119 menus) mirrors Marketama; each menu shows a searchable, paginated table of data pulled from Marketama. UI text and code comments are in Indonesian — match that. Product background is in `docs/PRD.md` and `README.md`.

## Commands

```bash
composer setup                 # install, .env, key, migrate, npm install + build
composer dev                   # php artisan dev (server + vite etc.)
composer test                  # config:clear + php artisan test
php artisan test --filter=NamaTest          # single test class/method
php artisan test tests/Feature/MenuTest.php # single file
vendor/bin/pint                # code style (Laravel Pint)
php artisan migrate --seed     # schema + reference data (no personal data)
php artisan marketama:import /path/to/csv-folder [--only=table ...]   # load full data
```

Default DB is SQLite (`database/database.sqlite`); tests use in-memory SQLite. Frontend: Vue 3 + Inertia.js, built with Vite + Tailwind 4 (`resources/js/app.js`). `npm test` runs Vitest specs for Vue components (`*.spec.js` next to the component).

## Architecture

- **Config-driven menus**: `config/marketama.php` is the single source of truth. `menu` defines sidebar groups/items (slug, title, icon); `pages` maps slug → tables to show; `tables` gives each table's title and column labels (column keys are also the searchable columns). `routes/web.php` registers the catch-all `/{slug}` route restricted via `whereIn` to the keys of `marketama.pages`, handled by the generic `MenuController@show` (queries tables with `DB::table`, skipping tables that don't exist — there are no Eloquent models besides `User`). Adding a plain data menu = edit the config (+ migration if a new table). A table entry may set `'sumber' => SomeClass::class` (an `App\Support\Menu\SumberTabel`) when the page needs joins, e.g. `outlet_all` reads `mk_outlet` / `trx_outlet_baru` (the same tables as the mobile API) joined to area/depo/karyawan names.
- **Custom pages** take precedence over the catch-all because their routes are declared first in `routes/web.php`: `TrackingKoordinatorController` (GPS route map), `DesignAreaController` (polygon area designer, JSON endpoints `options`/`areas` plus store/destroy), and `Route::inertia` placeholders (`Menu/ComingSoon`) for unbuilt "Tambah" forms. Map pages (`Tracking/History.vue`, `Area/Design.vue`) use Leaflet served from `public/vendor/leaflet` and need internet for OSM/Esri tiles; icon and popup HTML helpers live in `resources/js/Support/peta.js`.
- **One rendering stack**: every web page renders through Inertia + Vue. A controller returns `Inertia::render('<Area>/<Halaman>', [...])` (e.g. `Menu/Show`, `Motoris/Report`), and the page lives in `resources/js/Pages/`. Shared props (menu, searchMenu, currentSlug, flash, auth) come from `App\Http\Middleware\HandleInertiaRequests`. Pages use `Layouts/AppLayout.vue`; login and ganti password use `Layouts/AuthLayout.vue`. The only Blade file is the root template `resources/views/app.blade.php`. Tailwind component classes (`.card`, `.btn`, `.badge`, `.form-control`, `.data-table`, `.pagination`, `.alert`, `.modal-container`, …) are defined in `resources/css/app.css`.
- **Data loading**: `database/migrations/2026_10_22_000001_create_marketama_tables.php` defines the ~101 Marketama tables; the tracking and area-polygon tables are in the later two migrations. `ReferenceDataSeeder` seeds non-personal reference data from CSVs in `database/seeders/data/`. Transaction/employee/user/outlet data is intentionally NOT in the repo (personal data, up to 1.7M rows/table) and is loaded by `ImportMarketama` via `App\Support\CsvTableLoader` (one CSV per table, filename = table name; truncates the table first, ignores CSV columns missing from the table, strips BOM).
- Views: `resources/views/app.blade.php` (root Inertia template, `@inertia` + `@vite`). Vue: `resources/js/Pages/` (per area: `Akun/`, `Area/`, `Auth/`, `Kehadiran/`, `Menu/`, `Motoris/`, `Role/`, `Tracking/`), `resources/js/Layouts/`, `resources/js/Components/ui/`.

## Komponen UI (atomic design) — WAJIB dipakai

Semua markup UI **harus** disusun dari komponen reusable, bukan HTML mentah yang diulang-ulang di setiap view. Ada satu pustaka komponen Vue di `resources/js/Components/ui/`, disusun dengan atomic design (atoms → molecules → organisms) dan berdiri di atas class CSS di `resources/css/app.css`:

| Layer | Komponen (`resources/js/Components/ui/`) |
| --- | --- |
| atoms | `Button`, `Badge`, `Icon`, `Label`, `Input`, `Select`, `Textarea`, `Checkbox`, `Radio`, `FieldError`, `Tooltip` |
| molecules | `Card`, `Alert`, `Modal`, `Pagination`, `PageHeader`, `PasswordInput`, `Flash`, `Breadcrumb`, `Tabs` |
| organisms | `Sidebar` (dengan `partials/`), `Topbar`, `DataTable` |

Aturan:
- **Jangan** menulis ulang markup `<div class="card">...`, `<button class="btn ...">`, `<span class="badge ...">`, dst. Pakai komponennya: `<Card>`, `<Button>`, `<Badge>`, dst.
- Atom/molecule baru yang dipakai berkali-kali (≥2 tempat) wajib diekstrak ke folder yang sesuai sebelum di-copy-paste ke halaman lain. Partial khusus satu halaman boleh tinggal di `Pages/<Area>/partials/`.
- Komponen Vue meneruskan atribut non-prop ke elemen root secara otomatis (Vue attribute fallthrough) selama komponennya single-root, jadi `id`, `name`, `aria-*`, dan `data-*` tetap sampai ke elemen yang benar.
- Halaman Inertia (`Menu/Show.vue`) tidak boleh menulis tabel/pagination manual — pakai organism `DataTable` yang sudah membungkus `Card` + `Pagination`.
- Kalau sebuah tampilan butuh variasi yang tidak ditangani props komponen yang ada, tambah prop baru pada komponennya (misal `bodyClass`, `headerClass`) — jangan bikin salinan komponen baru untuk variasi kecil.
