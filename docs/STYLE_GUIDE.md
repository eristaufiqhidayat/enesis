# Style Guide

Panduan desain aplikasi ini — hasil pengamatan dari layout, view, dan CSS yang sudah ada. Tujuannya agar halaman baru konsisten dengan yang sudah dibuat, tanpa perlu menebak-nebak lagi.

## Stack UI

- **Tailwind CSS 4** (via Vite, [resources/css/app.css](../resources/css/app.css)) adalah satu-satunya sistem styling. Tidak ada Bootstrap di aplikasi ini lagi — jangan tambahkan kembali `public/vendor/bootstrap` atau class `data-bs-*`.
- Sebagian komponen umum (card, btn, form-control/select, badge, list-group, pagination, alert, modal, dropdown) didefinisikan sebagai class pendek di `app.css` lewat `@layer components` + `@apply`, supaya markup tidak perlu menulis utility Tailwind panjang berulang-ulang. Nama classnya sengaja mirip Bootstrap lama (`card`, `btn-primary`, `badge`, dst.) agar familiar, tapi semuanya murni Tailwind di baliknya — lihat definisi lengkapnya di `app.css` sebelum menambah varian baru.
- Untuk tata letak (grid, flex, spacing, warna teks, dll.) langsung pakai utility Tailwind biasa (`flex`, `grid grid-cols-*`, `gap-2`, `text-gray-500`, dst.), bukan kelas Bootstrap (`d-flex`, `row`/`col-*`, `text-muted`).
- **Lucide** (SVG self-hosted di `public/vendor/lucide/`) untuk semua ikon. Pakai komponen `<x-ui.atoms.icon name="...">` di Blade atau `<Icon name="...">` di Vue — nama ikon adalah nama file SVG tanpa `.svg` (mis. `store`, `file-text`), bukan `fas fa-*`.
- **Leaflet** untuk semua tampilan peta (tracking GPS, design area polygon).
- **Vue 3 + Inertia.js** dipakai untuk halaman menu tabel generik (`MenuController` → `Inertia::render('Menu/Show', ...)`, komponen di [resources/js/Pages/Menu/Show.vue](../resources/js/Pages/Menu/Show.vue), sidebar/topbar direplikasi di [resources/js/Layouts/AppLayout.vue](../resources/js/Layouts/AppLayout.vue)). Halaman lain (tracking, design area, coming soon) masih Blade biasa — interaktivitasnya ditulis vanilla JS inline di `@push('scripts')`, memakai `fetch` ke endpoint JSON. Jangan campur keduanya di satu halaman: halaman Inertia pakai komponen `.vue` di `resources/js/Pages`, halaman Blade pakai `@extends('layouts.app')` seperti biasa.
- Tidak ada lagi Bootstrap JS (tooltip/collapse/dropdown/modal). Sidebar submenu, dropdown notifikasi, dan modal pakai toggle class `hidden` buatan sendiri: di Blade lewat helper kecil di [layouts/app.blade.php](../resources/views/layouts/app.blade.php) (atribut `data-collapse-toggle`, `data-dropdown-toggle`, `data-modal-dismiss`), di Vue lewat state reaktif di [AppLayout.vue](../resources/js/Layouts/AppLayout.vue).

## Warna

Tema mengikuti Falcon "navbar-vibrant", sama seperti Marketama asli. Didefinisikan sebagai CSS biasa di [resources/css/app.css](../resources/css/app.css) — tidak ada file token warna terpisah.

| Token | Nilai | Pemakaian |
|---|---|---|
| Latar halaman | `#edf2f9` | `body` |
| Sidebar gradient | `linear-gradient(-45deg, rgba(0,160,255,.86), #0048a2)` | `.sidebar` |
| Biru utama (Falcon primary) | `#2c7be5` | tombol shortcut bulat, notif bell hover |
| Teks abu sekunder | `#5e6e82` | ikon notif bell default |
| Biru rute | `#0d6efd` | tombol `.btn-primary`, polyline rute tracking, titik GPS tengah |
| Hijau (start / sukses) | `#198754` | marker "Start" di peta tracking, `.btn-success` |
| Merah (end / danger) | `#dc3545` | marker "End", mode gambar polygon aktif |
| Ungu (depo) | `#6f42c1` | marker ikon depo di peta |

Warna lain (abu badge, info, warning) pakai palet Tailwind bawaan (`bg-gray-100`, `text-gray-500`, `border-amber-300`, dst., lihat class komponen di `app.css`) — jangan hardcode hex baru kecuali memang mengikuti identitas Marketama di atas.

## Tipografi

- Font sans-serif sistem bawaan (lewat Tailwind), tidak ada override font khusus untuk halaman yang dirender.
- Ukuran dasar: `font-size: .875rem` di `body` (menyesuaikan tabel data padat ala Falcon).
- Judul halaman: `<h5 class="mb-0">` atau `<h5 class="mb-3">` — bukan `<h1>`/`<h2>`. Sub-card pakai `<h6 class="mb-0">`.
- Teks kecil/meta pakai `small`/`text-xs` atau `text-gray-500`, bukan ukuran custom.

## Bahasa & copy

- **Semua teks UI dan komentar kode dalam Bahasa Indonesia** (lihat [CLAUDE.md](../CLAUDE.md)). Contoh pola kalimat yang sudah dipakai:
  - Placeholder search: `"Cari..."`
  - Tombol: `"Cari"`, `"Tampilkan"`, `"Reset"`, `"Simpan"`, `"Batal"`, `"Hapus"`, `"Kembali"`
  - Status kosong: `"Tidak ada data."`, `"Belum ada data."`
  - Konfirmasi: `confirm('Hapus area ini?')`

## Layout

- **Struktur fixed**: `.sidebar` (260px, fixed, full height) + `main` (`margin-left: 260px`). Di bawah 991px, sidebar jadi statis dan stack di atas konten (lihat media query di `app.css`).
- **Topbar**: baris ikon di kanan atas, kiri kosong — tidak ada breadcrumb/logo kedua. Diisi tombol shortcut dan dropdown notifikasi, bukan judul halaman (judul ada di `@section('content')` masing-masing halaman).
- **Konten halaman**: `<PageHeader>` (judul + subtitle deskripsi singkat, lihat [Komponen → PageHeader](#pageheader--wajib-di-setiap-halaman)) lalu satu atau lebih `.card`/`<Card>`. Pola umum (Vue/Inertia):

```vue
<PageHeader :title="judul" :subtitle="deskripsiSingkat" />
<Card class="mb-3"> ... </Card>
```

Halaman Blade lama boleh tetap pakai markup `<h5>` manual sampai dimigrasikan.

- **Grid filter + peta/daftar**: dua kolom pakai `grid grid-cols-1 lg:grid-cols-12 gap-2`/`gap-3` dengan `lg:col-span-*` (contoh: filter 5/7 di design area, filter 4/konten 8 di tracking).

## Komponen

Card, badge, tombol, form, list group, pagination, alert, dan modal pakai class komponen yang didefinisikan di [resources/css/app.css](../resources/css/app.css) (`@layer components`) — lihat file itu untuk daftar lengkap class dan variannya sebelum menambah yang baru.

### Card
Wadah utama setiap blok konten. Header pakai `card-header card-header-compact bg-gray-50` untuk panel kecil (`<h6 class="mb-0 font-semibold">`), atau `card-header font-semibold` tanpa `bg-gray-50` untuk judul tabel data. Body padat (`card-body-compact`) untuk panel kontrol, `card-body` biasa untuk isi lain.

### Tabel data
```blade
<div class="overflow-x-auto">
    <table class="data-table">
        <thead><tr><th>...</th></tr></thead>
        <tbody>...</tbody>
    </table>
</div>
```
`.data-table` sudah termasuk header abu-abu, strip baris ganjil, dan hover — tidak perlu class tambahan di `<thead>`/`<tr>`. Footer card menampilkan total data (`number_format(..., 0, ',', '.')`) di kiri dan `<ul class="pagination">` (item `.page-item`/`.page-link`, state `.active`/`.disabled`) di kanan (`flex justify-between items-center`). Baris kosong: `<td class="text-center text-gray-500 py-4">Tidak ada data.</td>`.

### Form & kontrol
- Semua input/select/textarea dalam konten utama pakai ukuran `-sm` (`form-control-sm`, `form-select-sm`, `btn-sm`) agar padat seperti Marketama. Label pakai `form-label mb-1` di panel filter (`form-label` sudah berukuran kecil).
- Field wajib ditandai `<span class="text-red-600">*</span>` setelah label, bukan atribut `required` tanpa indikator visual.
- Tombol aksi sekunder: `btn-outline-secondary`/`btn-outline-danger`/`btn-outline-warning`. Aksi utama: `btn-primary`. Aksi konstruktif ("mulai", "tambah"): `btn-success`.
- Ikon tombol selalu di depan teks dengan jarak `me-1` (contoh: `<x-ui.atoms.icon name="search" class="me-1" />Tampilkan`).

### PageHeader — wajib di setiap halaman
Setiap halaman Inertia (termasuk `Menu/Show.vue`) wajib dibuka dengan `<PageHeader :title="..." :subtitle="...">` ([resources/js/Components/ui/molecules/PageHeader.vue](../resources/js/Components/ui/molecules/PageHeader.vue)), bukan `<h5>` lepas. `title` adalah judul halaman (biasanya `page.title`/`judul` dari controller), `subtitle` adalah deskripsi singkat satu baris yang menjelaskan isi/cara hitung data halaman tersebut (lihat contoh `subtitle` di [resources/js/Pages/Motoris/RekapSku.vue](../resources/js/Pages/Motoris/RekapSku.vue): "Dihitung dari penjualan aplikasi motoris · ..."). Tombol aksi kanan (Tambah, Export, dll.) masuk lewat slot `#actions`, bukan markup `flex justify-between` manual. Halaman yang belum punya deskripsi singkat yang jelas tetap wajib isi `subtitle` — ringkas sumber data/periode/cara hitungnya, jangan dikosongkan.

**Ikon**: `PageHeader` selalu menampilkan ikon di kiri judul. Secara default ikonnya dicari otomatis lewat `cariIkonMenu()` ([resources/js/Support/menu.js](../resources/js/Support/menu.js)) — yaitu ikon menu sidebar yang sama di `config/marketama.php` (`menu.*.items[].icon`, item anak tanpa ikon mewarisi ikon induknya). Slug yang dipakai untuk pencarian ini adalah `breadcrumbSlug` yang sudah dikirim ke `<AppLayout :breadcrumb-slug="...">` (atau `currentSlug` kalau `breadcrumbSlug` tidak diisi) — `AppLayout` menyediakannya lewat `provide`/`inject` (`PAGE_HEADER_SLUG`, [resources/js/Layouts/pageHeaderContext.js](../resources/js/Layouts/pageHeaderContext.js)), jadi **tidak perlu** mengirim prop `icon` manual di hampir semua halaman — termasuk halaman yang slug URL-nya tidak sama dengan slug menu (form tambah/edit, halaman detail, dsb.), selama `breadcrumb-slug`-nya sudah benar. Cukup pastikan entri menunya di `config/marketama.php` punya `icon` yang relevan (nama file Lucide di `public/vendor/lucide/`). Kirim prop `icon` eksplisit (`<PageHeader icon="...">`) hanya untuk halaman yang benar-benar tidak punya slug menu yang representatif.

### Badge
`badge badge-secondary` untuk status netral, `badge badge-danger` untuk mode aktif/peringatan, `badge badge-primary` untuk hitungan (jumlah titik GPS dsb., sudah bulat penuh/pill lewat `rounded-full`).

### List group
Daftar yang bisa diklik (area tersimpan, daftar sales/koordinator) pakai `list-group list-group-flush` + item `list-group-item list-group-item-action`, dengan state custom `.active`/`.no-data` didefinisikan per halaman (lihat `.person-item` di [tracking/history.blade.php](../resources/views/tracking/history.blade.php)).

### Modal
Modal kecil untuk form simpan: `<div class="modal-container hidden" id="...">` berisi `.modal-content` (header/body/footer), ditampilkan/ditutup lewat `classList.toggle('hidden')` (bukan Bootstrap `bootstrap.Modal`). Tombol/area yang menutup modal pakai `data-modal-dismiss="<id>"`. Header `<h6 class="font-semibold m-0">` + tombol close `.btn-close`, footer `btn-sm btn-light` (batal) + `btn-sm btn-primary` (simpan). Lihat contoh lengkap di [area/design.blade.php](../resources/views/area/design.blade.php).

### Alert
`alert alert-info` untuk info non-blocking (contoh: "data menu ini sama dengan ...").

### Peta (Leaflet)
- Base tile OpenStreetMap, layer alternatif Esri World Imagery (satelit) + World_Transportation (garis jalan). Kontrol layer pakai `L.control.layers` bawaan atau custom `L.Control.extend` dengan tombol `.btn`/`.btn-sm` (class komponen di `app.css`) dibungkus `flex gap-1 shadow-sm rounded-md`.
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
