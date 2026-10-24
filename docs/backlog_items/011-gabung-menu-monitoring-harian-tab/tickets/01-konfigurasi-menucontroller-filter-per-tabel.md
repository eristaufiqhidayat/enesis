## Parent

docs/backlog_items/011-gabung-menu-monitoring-harian-tab/spec.md

## What to build

Gabungkan tiga entri `pages` (`checkin_pagi`, `sales_tidak_penjualan`, `sales_tidak_absen`) jadi satu slug/page gabungan di `config/marketama.php`, dengan `date_filter_column` dan `kpi` dikonfigurasi per-tabel (bukan per-page seperti sekarang). `MenuController@show` menghitung filter tanggal dan KPI per tabel di dalam loop, bukan sekali di awal untuk seluruh page. Hasilnya: mengunjungi slug gabungan menampilkan ketiga tabel (stacked, memakai layout multi-tabel yang sudah ada sekarang), masing-masing dengan filter/KPI-nya sendiri — tabel checkin tetap punya date filter + KPI, dua tabel lain tidak terpengaruh oleh query tanggal. Belum ada tab UI di tiket ini.

## Acceptance criteria

- [x] `config/marketama.php`: entri `pages.checkin_pagi`, `pages.sales_tidak_penjualan`, `pages.sales_tidak_absen` yang lama diganti satu entri gabungan dengan elemen `tables` berupa array asosiatif per tabel (`table`, `tab_label`, `date_filter_column`, `kpi`).
- [x] `MenuController@show` menghitung `dateColumn`/`tanggal`/`kpi` per elemen tabel dalam loop, bukan sekali di level page.
- [x] GET ke slug gabungan mengembalikan Inertia response dengan prop `tables` berisi tiga entri (nama tabel sesuai tiga tabel lama).
- [x] KPI hanya terisi untuk tabel yang punya `kpi` dikonfigurasi (tabel checkin); tabel lain mengembalikan `kpi: null` untuk dirinya.
- [x] Query `tanggal` hanya memengaruhi tabel yang punya `date_filter_column`; tabel lain di response tidak ikut terfilter oleh `tanggal`.
- [x] Feature test baru di `tests/Feature/MenuTest.php` meng-assert keempat poin di atas lewat response Inertia, bukan query ulang ke database.

## Blocked by

None (can start immediately).
