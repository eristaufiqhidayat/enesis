# Tampilkan Ikon Menu Beranda, Monitoring Harian Sales, Rekap Pencapaian SKU, Summary All Depo, Motoris Report

## Problem Statement

Lima menu di grup pertama sidebar (`label => null`, `config/marketama.php:8-37`) — "Beranda" (`home`), "Monitoring Harian Sales" (`monitoring_harian_sales`), "Rekap Pencapaian SKU" (`rekap_pencapaian_sku`), "Summary All Depo" (`summary_all_depo`), "Motoris Report" (`motoris_report`) — sudah punya key `icon` terisi di config (`home`, `clock`, `boxes`, `chart-pie`, `bike`), tapi ikon itu tidak pernah tampil di sidebar.

Penyebabnya di markup, bukan di config: ikon per-item cuma dirender untuk (a) judul grup berlabel (`sidebar.blade.php:31,39`) dan (b) header flyout item ber-children (`sidebar-nav-items.blade.php:29-31`). Item daun tanpa children — cabang `@else` di `sidebar-nav-items.blade.php:38-42` — cuma merender `$item['title']`, tidak pernah membaca `$item['icon']`. Versi Vue punya cacat yang sama: `SidebarNavList.vue:41-45` merender `<Link>` leaf tanpa `<Icon>`, berbeda dari `SidebarSubmenu.vue` (dipakai untuk item ber-children/grup) yang sudah merender ikon.

Karena hanya grup pertama (`label => null`) yang isinya item daun langsung tanpa children, lima menu inilah satu-satunya menu sidebar yang kehilangan ikon — semua menu lain ada di dalam grup berlabel atau punya children, jadi ikonnya sudah tampil lewat jalur flyout/grup yang sudah benar.

## Solution

Render ikon per-item pada cabang item daun (leaf, tanpa children) di kedua partial navigasi — Blade (`sidebar-nav-items.blade.php`) dan Vue (`SidebarNavList.vue`) — sama seperti cara header flyout item ber-children sudah merender ikonnya (`<x-ui.atoms.icon :name="$item['icon']" />` / `<Icon :name="item.icon" />`), dengan fallback aman kalau suatu item daun lain (di grup berlabel) tidak punya `icon` di config.

## User Stories

1. Sebagai admin yang membuka sidebar, saya ingin melihat ikon di depan "Beranda", "Monitoring Harian Sales", "Rekap Pencapaian SKU", "Summary All Depo", dan "Motoris Report", supaya tampilan lima menu ini konsisten secara visual dengan menu lain yang sudah punya ikon (baik ikon grup maupun ikon item ber-children).
2. Sebagai admin yang menyempitkan sidebar jadi strip ikon-saja, saya ingin kelima menu ini tetap bisa dikenali lewat ikonnya, supaya saya tidak perlu melebarkan sidebar untuk tahu menu mana yang mana.
3. Sebagai developer yang menambah menu baru ke grup tanpa label (`label => null`) di masa depan, saya ingin cukup menambahkan key `icon` di config seperti lima menu ini, supaya ikonnya otomatis tampil tanpa perlu menyentuh markup sidebar lagi.

## Implementation Decisions

- **`resources/views/components/ui/organisms/partials/sidebar-nav-items.blade.php`** (cabang `@else`, baris 37-42): tambah `<x-ui.atoms.icon :name="$item['icon']" />` sebelum `<span class="nav-link-text">`, dibungkus `@if (! empty($item['icon']))` — mengikuti pola yang sudah dipakai di header flyout item ber-children (baris 28-31) pada file yang sama, supaya item daun di grup berlabel yang kebetulan tidak punya `icon` tidak merender elemen ikon kosong.
- **`resources/js/Components/ui/organisms/partials/SidebarNavList.vue`** (cabang `v-else`, baris 41-45): tambah `<Icon v-if="item.icon" :name="item.icon" />` sebelum `<span class="nav-link-text">`, import `Icon` dari `@/Components/ui/atoms/Icon.vue` (sudah diimpor di `Sidebar.vue`, belum di file ini) — mengikuti pola `SidebarSubmenu.vue` yang sudah merender ikon untuk item ber-children.
- Tidak ada perubahan di `config/marketama.php` — kelima menu sudah punya `icon` terisi; perubahan ini murni di layer rendering.
- Tidak ada perubahan di CSS — class ikon yang dipakai mengikuti kelas yang sudah ada untuk ikon item lain di `.nav-link` (cek `resources/css/app.css` untuk nama class yang konsisten, mis. kelas yang dipakai header flyout `section-label-icon` kemungkinan tidak pas untuk ikon inline di `.nav-link`; kalau belum ada kelas untuk kasus ini, tambah kelas baru seperti `nav-link-icon` dan styling minimal — ukuran/spacing selaras dengan ikon flyout lain).

## Testing Decisions

- Tidak ada test otomatis baru yang bermakna untuk perubahan Blade/Vue murni tampilan ini (konsisten dengan tiket ikon sebelumnya, [013](../013-ganti-ikon-lucide/spec.md)) — proyek ini tidak punya test runner JS, dan assertion HTML mentah terhadap markup ikon rapuh.
- Verifikasi manual didokumentasikan di PR: screenshot sidebar sebelum/sesudah menunjukkan ikon tampil di depan kelima menu grup pertama, baik sidebar dalam keadaan lebar maupun disempitkan (strip ikon-saja), dan screenshot sidebar untuk halaman yang dirender lewat Blade maupun Inertia/Vue untuk memastikan kedua stack konsisten.

## Out of Scope

- Mengubah ikon yang sudah dipilih (`home`, `clock`, `boxes`, `chart-pie`, `bike`) di `config/marketama.php` — ikonnya sudah benar, masalahnya cuma tidak dirender.
- Menambah `icon` ke item daun lain yang saat ini tidak punya key `icon` di config — tiket ini hanya memperbaiki rendering untuk item yang *sudah* punya `icon` di config.
- Styling ulang ikon grup/flyout yang sudah tampil benar sekarang.

## Further Notes

- Pola yang sama (cek `! empty($item['icon'])` / `item.icon` sebelum render) bisa langsung dipakai kalau menu lain di grup berlabel nanti juga ingin diberi ikon per-item, bukan cuma ikon grup — tiket ini tidak menutup kemungkinan itu, cuma tidak mengerjakannya karena tidak diminta.
