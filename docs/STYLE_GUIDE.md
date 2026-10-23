# Style Guide

Panduan desain aplikasi ini — hasil pengamatan dari layout, view, dan CSS yang sudah ada. Tujuannya agar halaman baru konsisten dengan yang sudah dibuat, tanpa perlu menebak-nebak lagi.

## Stack UI

- **Bootstrap 5.3** (`public/vendor/bootstrap`) adalah sistem komponen utama — grid, card, form, modal, dropdown, badge, tombol, pagination (`pagination::bootstrap-5`). Dimuat lewat `<link>`/`<script>` statis di [layouts/app.blade.php](../resources/views/layouts/app.blade.php), bukan lewat Vite.
- **Font Awesome 5** (`public/vendor/fontawesome`) untuk semua ikon (`fas fa-*`).
- **Tailwind 4** ada di [resources/css/app.css](../resources/css/app.css) via Vite, tapi saat ini halaman tidak memakainya — jangan tambah class Tailwind di view baru, ikuti pola Bootstrap yang sudah ada supaya tidak tercampur dua sistem.
- **Leaflet** untuk semua tampilan peta (tracking GPS, design area polygon).
- **Vue 3 + Inertia.js** dipakai untuk halaman menu tabel generik (`MenuController` → `Inertia::render('Menu/Show', ...)`, komponen di [resources/js/Pages/Menu/Show.vue](../resources/js/Pages/Menu/Show.vue), sidebar/topbar direplikasi di [resources/js/Layouts/AppLayout.vue](../resources/js/Layouts/AppLayout.vue)). Halaman lain (tracking, design area, coming soon) masih Blade biasa — interaktivitasnya ditulis vanilla JS inline di `@push('scripts')`, memakai `fetch` ke endpoint JSON. Jangan campur keduanya di satu halaman: halaman Inertia pakai komponen `.vue` di `resources/js/Pages`, halaman Blade pakai `@extends('layouts.app')` seperti biasa.

## Warna

Tema mengikuti Falcon "navbar-vibrant", sama seperti Marketama asli. Didefinisikan sebagai CSS biasa di `<style>` block dalam [layouts/app.blade.php](../resources/views/layouts/app.blade.php) — tidak ada file token warna terpisah.

| Token | Nilai | Pemakaian |
|---|---|---|
| Latar halaman | `#edf2f9` | `body` |
| Sidebar gradient | `linear-gradient(-45deg, rgba(0,160,255,.86), #0048a2)` | `.sidebar` |
| Biru utama (Falcon primary) | `#2c7be5` | tombol shortcut bulat, notif bell hover |
| Teks abu sekunder | `#5e6e82` | ikon notif bell default |
| Bootstrap primary | `#0d6efd` | tombol `.btn-primary`, polyline rute tracking, titik GPS tengah |
| Hijau (start / sukses) | `#198754` | marker "Start" di peta tracking, `.btn-success` |
| Merah (end / danger) | `#dc3545` | marker "End", mode gambar polygon aktif |
| Ungu (depo) | `#6f42c1` | marker ikon depo di peta |

Warna lain (abu badge, info, warning) pakai kelas bawaan Bootstrap (`bg-secondary`, `bg-light`, `text-muted`, `btn-outline-warning`, dst.) — jangan hardcode hex baru kecuali memang mengikuti identitas Marketama di atas.

## Tipografi

- Font family default Bootstrap (sans-serif sistem) — tidak ada override font khusus untuk halaman yang dirender.
- Ukuran dasar: `font-size: .875rem` di `body` (sedikit lebih kecil dari default Bootstrap, menyesuaikan tabel data padat ala Falcon).
- Judul halaman: `<h5 class="mb-0">` atau `<h5 class="mb-3">` — bukan `<h1>`/`<h2>`. Sub-card pakai `<h6 class="mb-0">`.
- Teks kecil/meta pakai `small` atau `text-muted`, bukan ukuran custom.

## Bahasa & copy

- **Semua teks UI dan komentar kode dalam Bahasa Indonesia** (lihat [CLAUDE.md](../CLAUDE.md)). Contoh pola kalimat yang sudah dipakai:
  - Placeholder search: `"Cari..."`
  - Tombol: `"Cari"`, `"Tampilkan"`, `"Reset"`, `"Simpan"`, `"Batal"`, `"Hapus"`, `"Kembali"`
  - Status kosong: `"Tidak ada data."`, `"Belum ada data."`
  - Konfirmasi: `confirm('Hapus area ini?')`

## Layout

- **Struktur fixed**: `.sidebar` (260px, fixed, full height) + `main` (`margin-left: 260px`). Di bawah 991px, sidebar jadi statis dan stack di atas konten (lihat media query di `app.blade.php`).
- **Topbar**: baris ikon di kanan atas, kiri kosong — tidak ada breadcrumb/logo kedua. Diisi tombol shortcut dan dropdown notifikasi, bukan judul halaman (judul ada di `@section('content')` masing-masing halaman).
- **Konten halaman**: header baris (judul + aksi kanan seperti search/filter) lalu satu atau lebih `.card`. Pola umum:

```blade
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <h5 class="mb-0">Judul Halaman</h5>
    {{-- form filter/search di kanan --}}
</div>
<div class="card mb-3"> ... </div>
```

- **Grid filter + peta/daftar**: dua kolom pakai `row g-2`/`row g-3` dengan `col-lg-*` (contoh: filter 5/7 di design area, filter 4/konten 8 di tracking).

## Komponen

### Card
Wadah utama setiap blok konten. Header pakai `bg-light py-2` untuk panel kecil (`<h6 class="mb-0">`), atau `fw-semibold` tanpa `bg-light` untuk judul tabel data. Body padat (`py-2`) untuk panel kontrol, normal untuk isi lain.

### Tabel data
```blade
<div class="table-responsive">
    <table class="table table-sm table-striped table-hover mb-0">
        <thead class="table-light"><tr><th class="text-nowrap">...</th></tr></thead>
        <tbody>...</tbody>
    </table>
</div>
```
Footer card menampilkan total data (`number_format(..., 0, ',', '.')`) di kiri dan pagination Bootstrap-5 di kanan (`d-flex justify-content-between align-items-center`). Baris kosong: `<td class="text-center text-muted py-4">Tidak ada data.</td>`.

### Form & kontrol
- Semua input/select/textarea dalam konten utama pakai ukuran `-sm` (`form-control-sm`, `form-select-sm`, `btn-sm`) agar padat seperti Marketama. Label pakai `form-label small mb-1` di panel filter.
- Field wajib ditandai `<span class="text-danger">*</span>` setelah label, bukan atribut `required` tanpa indikator visual.
- Tombol aksi sekunder: `btn-outline-secondary`/`btn-outline-danger`/`btn-outline-warning`. Aksi utama: `btn-primary`. Aksi konstruktif ("mulai", "tambah"): `btn-success`.
- Ikon tombol selalu di depan teks dengan jarak `me-1` (contoh: `<i class="fas fa-search me-1"></i>Tampilkan`).

### Badge
`badge bg-secondary` untuk status netral, `badge bg-danger` untuk mode aktif/peringatan, `badge rounded-pill` untuk hitungan (jumlah titik GPS dsb.).

### List group
Daftar yang bisa diklik (area tersimpan, daftar sales/koordinator) pakai `list-group list-group-flush` + item `list-group-item-action`, dengan state custom `.active`/`.no-data` didefinisikan per halaman (lihat `.person-item` di [tracking/history.blade.php](../resources/views/tracking/history.blade.php)).

### Modal
Modal kecil untuk form simpan (`modal-dialog` default, bukan `-lg`), header `<h6 class="modal-title">` + tombol close, footer `btn-sm btn-light` (batal) + `btn-sm btn-primary` (simpan).

### Alert
`alert alert-info py-2` untuk info non-blocking (contoh: "data menu ini sama dengan ...").

### Peta (Leaflet)
- Base tile OpenStreetMap, layer alternatif Esri World Imagery (satelit) + World_Transportation (garis jalan). Kontrol layer pakai `L.control.layers` bawaan atau custom `L.Control.extend` dengan tombol Bootstrap bergaya `btn-group shadow-sm`.
- Marker custom pakai `L.divIcon` dengan SVG inline lingkaran berwarna + ikon putih di dalam (motor untuk tracking, rumah untuk depo) — bukan marker default Leaflet.
- Tinggi peta didefinisikan per halaman lewat `@push('head')` style (`height: 620px` untuk area statis, `calc(100vh - 160px)` untuk tracking agar mengisi viewport).
- Popup memakai HTML string manual (bukan template engine), selalu escape input dinamis lewat helper `esc()`/`escapeHtml()` sebelum disisipkan ke `innerHTML` — wajib dipertahankan untuk mencegah XSS dari data yang berasal dari user/CSV import.

## Pola halaman

Empat jenis halaman di `routes/web.php`:
1. **Tabel generik** (`resources/js/Pages/Menu/Show.vue`, dirender lewat Inertia dari `MenuController`) — otomatis dari `config/marketama.php`, tidak perlu view/komponen baru untuk menu data biasa.
2. **Placeholder "coming soon"** (`menu/coming_soon.blade.php`) — untuk form Tambah yang belum dibangun; selalu beri link `"Kembali"` ke halaman data terkait.
3. **Halaman custom Blade** (tracking, design area) — view sendiri, tapi tetap `@extends('layouts.app')` dan ikuti konvensi card/tombol/warna di atas.
4. **Halaman custom Vue/Inertia** — komponen baru di `resources/js/Pages/<Nama>/<Halaman>.vue`, dibungkus `AppLayout` (lihat `Menu/Show.vue`), props dikirim dari controller lewat `Inertia::render(...)`.

Saat membuat halaman Blade baru: `@extends('layouts.app')`, isi `@section('title', ...)`, taruh CSS halaman di `@push('head')` dan JS di `@push('scripts')` — jangan taruh `<style>`/`<script>` di luar stack ini karena akan bocor ke halaman lain lewat `@yield`.

Saat membuat halaman Vue/Inertia baru: bungkus dengan `<AppLayout>` dari [resources/js/Layouts/AppLayout.vue](../resources/js/Layouts/AppLayout.vue) agar sidebar/topbar konsisten, gunakan `<Head :title="...">` dari `@inertiajs/vue3` untuk judul tab, dan `<Link>`/`router` Inertia untuk navigasi — bukan `<a href>` biasa — supaya tetap SPA (tanpa reload penuh).
