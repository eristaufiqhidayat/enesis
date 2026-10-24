## Parent

docs/backlog_items/016-gabung-menu-motoris-tab/spec.md

## What to build

Gabungkan tiga entri `pages` (`motoris_coverage`, `motoris_performance`, `monitor_absensi_motoris`) jadi satu entri `pages.motoris_report` bertab (satu tab per tabel lama: Coverage, Performance, Absensi), memakai mekanisme tab yang sudah ada dari tiket 011 (atau versi grup dari tiket 015 kalau sudah tersedia). Satu item `menu` baru menggantikan tiga item lama, dan sidebar (Blade + Vue) diperbarui.

## Acceptance criteria

- [x] `config/marketama.php`: satu entri `menu` baru (`motoris_report`) menggantikan tiga entri lama (`motoris_coverage`, `motoris_performance`, `monitor_absensi_motoris`).
- [x] `config/marketama.php`: entri `pages.motoris_report` dengan `'tabs' => true` dan tiga tab (`tab_label`: Coverage, Performance, Absensi), masing-masing satu tabel sesuai tabel lama.
- [x] Entri `pages.motoris_coverage`, `pages.motoris_performance`, `pages.monitor_absensi_motoris` lama dihapus.
- [x] GET `/motoris_report` menampilkan tab pertama (Coverage) secara default, dan tab lain menampilkan tabelnya masing-masing saat `?tab=` sesuai.
- [x] Hanya tabel milik tab aktif yang tampil (bukan ketiganya sekaligus); pencarian (`q`) hanya diterapkan ke tabel tab aktif.
- [x] Sidebar (`resources/views/components/ui/organisms/sidebar.blade.php` dan `resources/js/Components/ui/organisms/Sidebar.vue`) hanya punya satu item menu untuk halaman gabungan ini.
- [x] Feature test di `tests/Feature/MenuTest.php` meng-assert: default tab, isi tabel per tab, dan bahwa tab lain tidak ikut tampil sekaligus.

## Blocked by

None (can start immediately — pakai mekanisme tab tiket 011 langsung karena tiap tab di sini cuma satu tabel; tidak perlu menunggu generalisasi tiket 015).
