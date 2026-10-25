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

Default DB is SQLite (`database/database.sqlite`); tests use in-memory SQLite. Frontend: Vite + Tailwind 4 (`resources/js/app.js`).

## Architecture

- **Config-driven menus**: `config/marketama.php` is the single source of truth. `menu` defines sidebar groups/items (slug, title, icon); `pages` maps slug → tables to show; `tables` gives each table's title and column labels (column keys are also the searchable columns). `routes/web.php` registers the catch-all `/{slug}` route restricted via `whereIn` to the keys of `marketama.pages`, handled by the generic `MenuController@show` (queries tables with `DB::table`, skipping tables that don't exist — there are no Eloquent models besides `User`). Adding a plain data menu = edit the config (+ migration if a new table). A table entry may set `'sumber' => SomeClass::class` (an `App\Support\Menu\SumberTabel`) when the page needs joins, e.g. `outlet_all` reads `mk_outlet` / `trx_outlet_baru` (the same tables as the mobile API) joined to area/depo/karyawan names.
- **Custom pages** take precedence over the catch-all because their routes are declared first in `routes/web.php`: `TrackingKoordinatorController` (GPS route map), `DesignAreaController` (polygon area designer, JSON endpoints `options`/`areas` plus store/destroy), and `Route::view` placeholders (`menu.coming_soon`) for unbuilt "Tambah" forms. Map views use Leaflet served from `public/vendor/leaflet` and need internet for OSM/Esri tiles.
- **Two rendering stacks**: `MenuController@show` (the catch-all `/{slug}` page) renders through Inertia + Vue (`resources/js/Pages/Menu/Show.vue`). The custom pages above (`tracking/`, `area/`, `menu.coming_soon`) render as plain Blade views under `layouts.app`. Both stacks share the same Tailwind component classes defined in `resources/css/app.css` (`.card`, `.btn`, `.badge`, `.form-control`, `.data-table`, `.pagination`, `.alert`, `.modal-container`, …).
- **Data loading**: `database/migrations/2026_10_22_000001_create_marketama_tables.php` defines the ~101 Marketama tables; the tracking and area-polygon tables are in the later two migrations. `ReferenceDataSeeder` seeds non-personal reference data from CSVs in `database/seeders/data/`. Transaction/employee/user/outlet data is intentionally NOT in the repo (personal data, up to 1.7M rows/table) and is loaded by `ImportMarketama` via `App\Support\CsvTableLoader` (one CSV per table, filename = table name; truncates the table first, ignores CSV columns missing from the table, strips BOM).
- Views: `resources/views/layouts/app.blade.php` (shell, includes the `ui.organisms.sidebar`/`ui.organisms.topbar` components), `menu/`, `tracking/`, `area/`. Vue pages: `resources/js/Pages/`, `resources/js/Layouts/AppLayout.vue`.

## Komponen UI (atomic design) — WAJIB dipakai

Semua markup UI **harus** disusun dari komponen reusable, bukan HTML mentah yang diulang-ulang di setiap view. Ada dua pustaka komponen paralel karena ada dua stack rendering (lihat di atas), tapi strukturnya sama-sama atomic design (atoms → molecules → organisms) dan sama-sama berdiri di atas class CSS di `resources/css/app.css`:

| Layer | Blade (`resources/views/components/ui/`) | Vue (`resources/js/Components/ui/`) |
| --- | --- | --- |
| atoms | `button`, `badge`, `icon`, `label`, `input`, `select`, `textarea` | `Button.vue`, `Badge.vue`, `Icon.vue` |
| molecules | `card`, `alert`, `modal` | `Card.vue`, `Alert.vue`, `Pagination.vue` |
| organisms | `sidebar`, `topbar` | `Sidebar.vue`, `Topbar.vue`, `DataTable.vue` |

Aturan:
- **Jangan** menulis ulang markup `<div class="card">...`, `<button class="btn ...">`, `<span class="badge ...">`, dst. Pakai komponennya: `<x-ui.molecules.card>` / `<Card>`, `<x-ui.atoms.button>` / `<Button>`, dst.
- Atom/molecule baru yang dipakai berkali-kali (≥2 tempat) wajib diekstrak ke folder yang sesuai sebelum di-copy-paste ke view lain.
- Komponen Blade adalah anonymous component (`@props` di baris pertama) sehingga atribut tambahan (`id`, `name`, `data-*`, `value`, dst.) otomatis diteruskan ke elemen root lewat `$attributes->merge()` — penting karena banyak halaman (`tracking/`, `area/`) memakai `id`/`data-*` tersebut dari JS vanilla.
- Komponen Vue meneruskan atribut non-prop ke elemen root secara otomatis (Vue attribute fallthrough) selama komponennya single-root.
- Halaman Inertia (`Menu/Show.vue`) tidak boleh menulis tabel/pagination manual — pakai organism `DataTable` yang sudah membungkus `Card` + `Pagination`.
- Kalau sebuah tampilan butuh variasi yang tidak ditangani props komponen yang ada, tambah prop baru pada komponennya (misal `bodyClass`, `headerClass`) — jangan bikin salinan komponen baru untuk variasi kecil.
