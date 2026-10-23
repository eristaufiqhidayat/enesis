# Tech Stack

Dokumen ini merangkum teknologi yang dipakai di aplikasi Enesis Canvassing, berdasarkan kondisi kode saat ini.

## Ringkasan

| Lapisan | Teknologi |
| --- | --- |
| Bahasa server | PHP ^8.3 |
| Framework backend | Laravel 13 (`laravel/framework` ^13.17) |
| Template / rendering | Laravel Blade (server-side rendering) |
| Database | MySQL (default `.env.example`), PostgreSQL 16 (didukung), SQLite (default `config/database.php` dan dipakai di test) |
| Styling | Tailwind CSS 4 (lewat Vite) |
| Ikon | Font Awesome 5 Free |
| JavaScript | Vanilla JS, tanpa framework SPA |
| Peta | Leaflet, tile OpenStreetMap dan Esri |
| Build asset | Vite 8, `laravel-vite-plugin`, `@tailwindcss/vite` |
| Testing | PHPUnit 12 (via `php artisan test`), Mockery, Faker |
| Code style | Laravel Pint |
| Tooling dev | Laravel Pail, Laravel Tinker, Collision |

## Backend

- **Laravel 13** dengan PHP ^8.3. Routing di `routes/web.php`; halaman menu generik ditangani `MenuController@show`.
- **Konfigurasi menu** disimpan di `config/marketama.php` (grup sidebar, halaman, dan label kolom), bukan di database.
- **Tanpa model Eloquent** kecuali `User`. Data tabel diambil lewat query builder (`DB::table`).
- **Migration** untuk sekitar 101 tabel Marketama di `database/migrations/2026_10_22_000001_create_marketama_tables.php`, ditambah migration untuk tracking dan area polygon.
- **Seeder**: `ReferenceDataSeeder` (data referensi tanpa data pribadi) dan `MarketamaDataSeeder` (data hasil tarikan dari `storage/app/marketama`).
- **Command artisan**: `marketama:import` untuk memuat CSV per tabel lewat `App\Support\CsvTableLoader`.
- **Endpoint JSON** untuk fitur area polygon (`options`, `areas`, store, destroy) dan data peta tracking.

## Database

- Default konfigurasi aplikasi: `DB_CONNECTION=sqlite` (`config/database.php`).
- Contoh `.env` (`.env.example`): `DB_CONNECTION=mysql`, database `enesis`, charset `utf8mb4`.
- PostgreSQL 16 didukung; butuh ekstensi PHP `pdo_pgsql`.
- Test memakai SQLite in-memory.
- Data transaksi, karyawan, user, dan outlet tidak ada di repo (data pribadi, sampai 1,7 juta baris per tabel). Folder `storage/app` masuk `.gitignore`.

## Frontend

- **Vue 3 + Inertia.js** untuk halaman menu tabel generik (`MenuController` me-render `Inertia::render('Menu/Show', ...)`, komponen di `resources/js/Pages/Menu/Show.vue`, layout sidebar/topbar di `resources/js/Layouts/AppLayout.vue`).
- **Blade** untuk halaman lain (tracking, design area polygon, coming soon). Layout utama ada di `resources/views/layouts/app.blade.php`, dengan sidebar dan topbar sebagai include.
- **Tailwind CSS 4** (`resources/css/app.css`, lewat Vite) adalah satu-satunya sistem styling — tidak ada Bootstrap. Sebagian komponen umum (card, btn, form, badge, list-group, pagination, alert, modal, dropdown) didefinisikan sebagai class pendek di `app.css` lewat `@layer components` + `@apply`.
- **Font Awesome 5 Free** (`public/vendor/fontawesome/`) untuk ikon sidebar dan topbar. File disalin ke `public/vendor`, bukan diinstal lewat npm.
- Tidak ada lagi JS komponen Bootstrap (tooltip/collapse/dropdown/modal) — diganti helper vanilla JS kecil (toggle class `hidden`) di `layouts/app.blade.php` untuk halaman Blade, dan state reaktif Vue di `AppLayout.vue`.
- **Vanilla JavaScript** dengan `fetch` untuk memanggil endpoint JSON di halaman Blade. Token CSRF dikirim lewat header `X-CSRF-TOKEN`. Tidak ada Livewire atau Alpine.
- **Leaflet** (`public/vendor/leaflet/`) untuk peta tracking koordinator, tracking sales, dan design polygon area. Dimuat statis, bukan lewat npm.
- **Layanan eksternal di browser**: tile OpenStreetMap dan Esri, serta Nominatim (OSM) untuk pencarian dan reverse geocoding alamat. Fitur peta butuh koneksi internet.

## Build asset (Vite + Tailwind)

- `vite.config.js` memakai `laravel-vite-plugin` dengan input `resources/css/app.css` dan `resources/js/app.js`.
- `app.css` mengimpor Tailwind 4 dan mengatur font `Instrument Sans`, yang dimuat lewat `laravel-vite-plugin/fonts` (Bunny Fonts).
- `package.json` (`"type": "module"`): dependensi dev `vite` 8, `tailwindcss` 4, `@tailwindcss/vite`, `laravel-vite-plugin` 3.1, `concurrently`, dan opsional `@laravel/multiplex`.
- Perintah: `npm run build` (produksi) dan `npm run dev` / `composer dev` (development dengan HMR).

## Testing dan kualitas kode

- `composer test` menjalankan `config:clear` lalu `php artisan test`.
- Test memakai PHPUnit 12 dan SQLite in-memory.
- Code style: `vendor/bin/pint` (Laravel Pint).

## Setup lokal

```bash
composer setup    # install dependensi, .env, key, migrate, npm install, dan build
composer dev      # php artisan dev (server + vite dan proses lain)
```

Langkah lengkap ada di `README.md`.

## Catatan dan hal yang perlu diperhatikan

- **Default database tidak konsisten.** `config/database.php` default ke SQLite, sedangkan `.env.example` dan `README.md` memakai MySQL. Pastikan `.env` lokal sesuai dengan database yang dipakai.
- **Dependensi frontend di `public/vendor`** (Font Awesome, Leaflet) tidak tercatat di `package.json`, sehingga update versinya harus dilakukan manual.
