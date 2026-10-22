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

- **Config-driven menus**: `config/marketama.php` is the single source of truth. `menu` defines sidebar groups/items (slug, title, icon); `pages` maps slug → tables to show; `tables` gives each table's title and column labels (column keys are also the searchable columns). `routes/web.php` registers the catch-all `/{slug}` route restricted via `whereIn` to the keys of `marketama.pages`, handled by the generic `MenuController@show` (queries tables with `DB::table`, skipping tables that don't exist — there are no Eloquent models besides `User`). Adding a plain data menu = edit the config (+ migration if a new table).
- **Custom pages** take precedence over the catch-all because their routes are declared first in `routes/web.php`: `TrackingKoordinatorController` (GPS route map), `DesignAreaController` (polygon area designer, JSON endpoints `options`/`areas` plus store/destroy), and `Route::view` placeholders (`menu.coming_soon`) for unbuilt "Tambah" forms. Map views use Leaflet served from `public/vendor/leaflet` and need internet for OSM/Esri tiles.
- **Data loading**: `database/migrations/2026_10_22_000001_create_marketama_tables.php` defines the ~101 Marketama tables; the tracking and area-polygon tables are in the later two migrations. `ReferenceDataSeeder` seeds non-personal reference data from CSVs in `database/seeders/data/`. Transaction/employee/user/outlet data is intentionally NOT in the repo (personal data, up to 1.7M rows/table) and is loaded by `ImportMarketama` via `App\Support\CsvTableLoader` (one CSV per table, filename = table name; truncates the table first, ignores CSV columns missing from the table, strips BOM).
- Views: `resources/views/layouts/` (app, sidebar, topbar), `menu/`, `tracking/`, `area/`.
