## Parent

docs/backlog_items/015-gabung-rekap-pencapaian-sku-tab/spec.md

## What to build

Gabungkan `pages.rekap_pencapaian_sku_motoris` dan `pages.rekap_pencapaian_depo_sku` jadi satu entri `pages.rekap_pencapaian_sku` memakai struktur grup dari ticket 01 (tab "Per Motoris" berisi dua tabel achieved/not achieved, tab "Per Depo" berisi dua tabel achieved/not achieved). Perbarui `menu` (satu item sidebar menggantikan dua item lama) dan sidebar (Blade + Vue).

## Acceptance criteria

- [x] `config/marketama.php`: satu entri `menu` baru (`rekap_pencapaian_sku`) menggantikan dua entri lama (`rekap_pencapaian_sku_motoris`, `rekap_pencapaian_depo_sku`).
- [x] `config/marketama.php`: entri `pages.rekap_pencapaian_sku` dengan `'tabs' => true` dan dua grup tab (`motoris`, `depo`), masing-masing berisi dua tabel (`...tableachieved`, `...tablenotachieved`) sesuai yang sudah ada.
- [x] Entri `pages.rekap_pencapaian_sku_motoris` dan `pages.rekap_pencapaian_depo_sku` lama dihapus.
- [x] GET `/rekap_pencapaian_sku` menampilkan tab "Per Motoris" (default) dengan dua tabelnya, dan tab "Per Depo" dengan dua tabelnya saat `?tab=depo`.
- [x] Sidebar (`resources/views/components/ui/organisms/sidebar.blade.php` dan `resources/js/Components/ui/organisms/Sidebar.vue`) hanya punya satu item menu untuk halaman gabungan ini.
- [x] Feature test di `tests/Feature/MenuTest.php` meng-assert: default tab, isi tabel per tab, dan jumlah tabel yang tampil per tab (2, bukan 4).

## Blocked by

- Ticket 01 (generalisasi tab grup tabel) — butuh struktur grup tersedia sebelum menu ini bisa dikonfigurasi memakainya.
