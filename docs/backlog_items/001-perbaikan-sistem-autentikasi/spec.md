# Perbaikan Sistem Autentikasi

## Problem Statement

Aplikasi ini saat ini **tidak punya sistem autentikasi sama sekali**. `routes/web.php` mendaftarkan semua rute (`/{slug}` catch-all untuk 119 menu, `tracking_koordinator_history_location`, `tracking_sales_history_location`, `master_area/design_area`, dsb.) tanpa middleware `auth` apa pun, dan tidak ada route `login`/`logout` yang terdaftar. Tabel `users` dan model `App\Models\User` sudah ada (bawaan skeleton Laravel) tapi tidak dipakai — tidak ada controller, view, atau proses login yang memanfaatkannya.

Akibatnya siapa pun yang tahu URL bisa membuka seluruh data Marketama (termasuk nantinya data transaksi, karyawan, outlet, dan titik GPS koordinator/sales yang bersifat pribadi) tanpa perlu login. Ini bertentangan dengan sifat data di aplikasi (lihat `docs/PRD.md` baris 24 dan 75 soal data pribadi) dan dengan peran "Super Admin PT. Marketama" yang disebut PRD sebagai pengelola user.

## Solution

Bangun sistem autentikasi session-based standar Laravel (guard `web`, provider Eloquent `users` — keduanya sudah dikonfigurasi di `config/auth.php` tapi belum dipakai):

- Halaman login (`/login`) dan proses logout (`/logout`).
- Middleware `auth` diterapkan ke seluruh rute aplikasi yang sudah ada (catch-all menu, tracking, design area, halaman `coming_soon`), kecuali rute login itu sendiri.
- Redirect ke `/login` untuk pengguna yang belum login, dan redirect balik ke halaman yang dituju (`intended`) setelah login berhasil.
- Rate limiting percobaan login (pakai `RateLimiter`/throttle middleware bawaan Laravel) untuk mencegah brute force.
- Tombol logout di topbar (`resources/views/components/ui/organisms/topbar.blade.php` dan `resources/js/Components/ui/organisms/Topbar.vue`) yang menampilkan nama user yang sedang login.

Tidak perlu membangun fitur registrasi mandiri (self-service signup) — user dibuat lewat seeder/tinker/artisan oleh admin, konsisten dengan PRD yang menyebut "Super Admin ... mengelola user" sebagai proses admin, bukan signup publik.

## User Stories

1. Sebagai pengguna yang belum login, saya ingin diarahkan ke halaman login ketika mengakses URL menu apa pun (`/{slug}`, `/tracking_koordinator_history_location`, `/master_area/design_area`, dll.), supaya data Marketama tidak bisa dibuka tanpa autentikasi.
2. Sebagai pengguna, saya ingin memasukkan email dan password di halaman login, supaya saya bisa masuk ke sistem dengan kredensial yang diberikan admin.
3. Sebagai pengguna yang baru login, saya ingin diarahkan kembali ke halaman yang tadinya saya tuju (bukan selalu ke `/home`), supaya tautan yang saya buka langsung dari luar tetap berfungsi setelah login.
4. Sebagai pengguna yang memasukkan email/password salah, saya ingin melihat pesan error yang jelas dalam Bahasa Indonesia ("Email atau password salah."), supaya saya tahu harus mengecek kembali kredensial saya.
5. Sebagai pengguna yang mencoba login berulang kali dengan cepat, saya ingin dibatasi (rate limit) setelah beberapa kali gagal, supaya sistem terlindungi dari brute force.
6. Sebagai pengguna yang sudah login, saya ingin melihat nama saya dan tombol "Keluar" di topbar, supaya saya tahu sedang login sebagai siapa dan bisa logout dengan mudah.
7. Sebagai pengguna yang menekan "Keluar", saya ingin session saya diakhiri dan diarahkan ke halaman login, supaya akun saya tidak bisa dipakai orang lain di perangkat yang sama.
8. Sebagai pengguna yang sudah login dan membuka `/login` lagi, saya ingin langsung diarahkan ke `/home` (bukan melihat form login lagi), supaya tidak ada login ganda yang membingungkan.
9. Sebagai admin, saya ingin membuat akun user baru lewat `php artisan tinker` atau seeder (password di-hash lewat cast `password => hashed` yang sudah ada di `User` model), supaya saya bisa mengelola siapa yang boleh akses tanpa perlu UI registrasi.
10. Sebagai developer yang menjalankan test, saya ingin helper `actingAs()` tetap berfungsi normal di semua test Feature yang sudah ada (`MenuTest`, dll.), supaya menambahkan middleware `auth` tidak merusak test suite yang sudah ada — test tersebut perlu diupdate untuk login lebih dulu.
11. Sebagai pengguna yang sudah login tapi session-nya habis/expired, saya ingin diarahkan ke login dan setelah login kembali ke halaman semula, supaya saya tidak kehilangan konteks kerja saya.

## Implementation Decisions

- **Guard & provider**: pakai default bawaan `config/auth.php` (`guard: web`, `provider: users`, Eloquent `App\Models\User`) — tidak perlu guard/provider baru.
- **Controller**: `App\Http\Controllers\Auth\LoginController` (atau `AuthenticatedSessionController` kalau mengikuti konvensi Breeze) dengan method `create` (tampilkan form) dan `store` (proses login via `Auth::attempt()`), plus `destroy` untuk logout (`Auth::logout()` + `$request->session()->invalidate()` + `regenerateToken()`).
- **Routes** (`routes/web.php`): tambah di luar grup middleware `auth`:
  ```php
  Route::get('/login', [LoginController::class, 'create'])->name('login')->middleware('guest');
  Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
  Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
  ```
  Semua route lain (termasuk catch-all `/{slug}`, tracking, design-area, `coming_soon`) dibungkus `Route::middleware('auth')->group(...)`.
- **View login**: Blade baru di `resources/views/auth/login.blade.php`, pakai komponen UI yang sudah ada (`x-ui.atoms.input`, `x-ui.atoms.label`, `x-ui.atoms.button`, `x-ui.molecules.card`, `x-ui.molecules.alert` untuk pesan error) sesuai aturan "Komponen UI (atomic design) — WAJIB dipakai" di `CLAUDE.md`. Layout minimal (tanpa sidebar/topbar) karena halaman ini di luar `layouts.app`.
- **Topbar**: tambah info user login + form logout (`POST /logout`) di `resources/views/components/ui/organisms/topbar.blade.php` dan `resources/js/Components/ui/organisms/Topbar.vue`, pakai `Auth::user()->name` / props Inertia `auth.user` (perlu tambah `HandleInertiaRequests::share()` untuk mengirim data user login ke semua halaman Inertia).
- **Rate limiting**: pakai `Illuminate\Support\Facades\RateLimiter` di `store()`, kunci berdasarkan email + IP, mengikuti pola default Laravel Breeze (`login.{email}|{ip}`, lockout 1 menit setelah 5 percobaan).
- **Redirect "intended"**: pakai `redirect()->intended('/home')` bawaan Laravel — otomatis bekerja karena middleware `auth` sudah menyimpan URL tujuan di session sebelum redirect ke login.
- **Seeder user**: tambah `UserSeeder` kecil (dipanggil dari `DatabaseSeeder`) yang membuat satu akun admin default untuk environment lokal/dev saja (bukan data personal production), supaya `composer setup` tetap menghasilkan aplikasi yang bisa langsung dicoba.
- **Tidak ada tabel/migrasi baru** — tabel `users` dari migrasi default Laravel (`0001_01_01_000000_create_users_table.php`) sudah cukup (`name`, `email`, `password`, `remember_token`).

## Testing Decisions

- Seam: HTTP routes, lewat `$this->post('/login', [...])` dan `$this->get('/{slug}')`, assert redirect/response status dan Inertia props (`assertRedirect`, `assertInertia`) — konsisten dengan `tests/Feature/MenuTest.php`.
- Test baru di `tests/Feature/Auth/LoginTest.php`:
  - Pengguna belum login diarahkan ke `/login` saat mengakses menu mana pun.
  - Login dengan kredensial benar berhasil dan redirect ke halaman yang dituju (`intended`).
  - Login dengan kredensial salah menampilkan error, tidak membuat session.
  - Logout mengakhiri session dan mengarahkan ke `/login`.
  - Rate limiting mengembalikan error setelah percobaan gagal berulang (pakai `RateLimiter::clear()` di awal test untuk isolasi).
- Test yang sudah ada (`MenuTest.php`, test untuk `TrackingKoordinatorController`/`DesignAreaController` kalau ada) perlu diupdate untuk memanggil `$this->actingAs(User::factory()->create())` sebelum memanggil route — ini bagian dari pekerjaan, bukan di luar scope, karena middleware `auth` baru akan membuat semua test itu gagal (redirect ke login) kalau tidak diupdate.

## Out of Scope

- Registrasi mandiri (self-service signup) — user dibuat oleh admin.
- Reset password lewat email (butuh konfigurasi mailer; bisa jadi backlog item terpisah kalau dibutuhkan).
- Role/permission granular per menu (119 menu, 9 grup) — spec ini hanya soal "bisa login atau tidak", bukan otorisasi per-menu. Itu backlog item terpisah.
- Two-factor authentication / SSO.
- API token authentication untuk aplikasi mobile (sudah disebut PRD sebagai item terpisah, baris 101 `docs/PRD.md`).

## Further Notes

- Ini bukan "perbaikan" dalam arti memperbaiki bug pada sistem yang sudah ada — sistem autentikasinya **belum pernah dibangun**. Judul backlog "perbaikan sistem autentikasi" di sini dibaca sebagai "membangun & mengamankan sistem autentikasi yang saat ini tidak ada".
- Begitu middleware `auth` dipasang di catch-all `/{slug}`, seluruh 119 menu otomatis ikut terlindungi tanpa perlu diubah satu per satu — konsisten dengan pendekatan config-driven di `config/marketama.php`.
- Perlu koordinasi dengan tim mobile (lihat `docs/PRD.md` baris 101) kalau nanti autentikasi token API dibangun, supaya tidak dua sistem auth yang tidak konsisten.

## Keputusan saat implementasi (24 Okt 2026)

- **Login memakai username, bukan email.** Akun Enesis diisi dari akun aplikasi lama lewat `php artisan enesis:sinkron-user` (tabel `mk_user` + `mk_karyawan`), dan hampir semuanya tidak punya email. Username tidak membedakan huruf besar/kecil. Rate limit memakai kunci `username|IP` (5 kali gagal, kunci 60 detik).
- **Hanya akun `aktif` yang bisa login.** Akun milik karyawan nonaktif sudah dinonaktifkan saat migrasi.
- **Wajib ganti password.** Password lama tidak bisa diambil dari aplikasi lama, jadi admin membuat password awal dengan `php artisan enesis:atur-password <username>`. Akun dengan `wajib_ganti_password` hanya bisa membuka `/ganti-password` (middleware `wajib-ganti-password`) sampai password diganti.
- **`UserSeeder`** membuat akun `admin` / `password` untuk lokal/dev. Di production akun ini hanya dibuat bila `SEED_ADMIN_PASSWORD` diisi, dan password itu wajib diganti saat login pertama.
- Topbar Vue melakukan logout lewat form biasa (bukan kunjungan Inertia), karena halaman login adalah Blade.

