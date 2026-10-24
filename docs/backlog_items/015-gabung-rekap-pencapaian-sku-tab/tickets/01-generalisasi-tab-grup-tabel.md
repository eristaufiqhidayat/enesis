## Parent

docs/backlog_items/015-gabung-rekap-pencapaian-sku-tab/spec.md

## What to build

Generalisasi mekanisme tab yang dibangun di tiket 011 (`page.tabs`, `MenuController@show`, `Show.vue`) dari "satu tab = satu tabel" menjadi "satu tab = satu grup berisi 1..n tabel", tanpa mengubah perilaku halaman yang sudah memakainya (`monitoring_harian_sales`). Belum ada slug/menu baru di tiket ini — murni perubahan struktur data & logika yang reusable.

## Acceptance criteria

- [x] `MenuController@show` menerima konfigurasi `tables` berupa daftar grup `{tab_key, tab_label, tables: [...nama tabel...]}` (bukan satu tabel per elemen), membangun flat list tabel untuk dikirim ke Inertia dengan tambahan field `tab_key` per tabel.
- [x] `activeTab` divalidasi/dibandingkan terhadap `tab_key` grup, default ke `tab_key` grup pertama kalau query `tab` kosong/tidak valid.
- [x] `config/marketama.php` untuk `monitoring_harian_sales` disesuaikan ke struktur grup baru (tiap tabel lama jadi grup berisi satu tabel) tanpa mengubah perilaku yang sudah ada.
- [x] `resources/js/Pages/Menu/Show.vue`: `visibleTables` difilter dari `table.tab_key === activeTab` (bukan `table.name`), `tabItems` dibangun dari grup unik `tab_key`/`tab_label`.
- [x] Test existing untuk `monitoring_harian_sales` di `tests/Feature/MenuTest.php` tetap hijau tanpa perubahan assertion (perilaku tidak berubah, hanya struktur internal).
- [x] Feature test baru yang meng-assert satu grup bisa berisi >1 tabel: satu tab menampilkan beberapa tabel sekaligus, tab lain tidak ikut tampil.

## Blocked by

None (can start immediately).
