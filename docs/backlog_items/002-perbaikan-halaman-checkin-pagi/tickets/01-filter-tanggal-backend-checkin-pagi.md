## Parent

docs/backlog_items/002-perbaikan-halaman-checkin-pagi/spec.md

## What to build

Tambah opsi `date_filter_column` pada config halaman (`config/marketama.php`), diisi untuk `checkin_pagi` (`tanggal_data`), dan baca opsi ini secara generik di `MenuController@show` supaya hanya baris dengan `tanggal_data` sama dengan parameter `tanggal` (default hari ini kalau tidak diisi) yang dikembalikan. Filter ini digabung (AND) dengan pencarian teks `q` yang sudah ada. Belum perlu UI date picker — bisa diverifikasi lewat query string `?tanggal=YYYY-MM-DD` langsung, karena date picker frontend adalah ticket terpisah.

## Acceptance criteria

- [ ] `GET /checkin_pagi` tanpa parameter `tanggal` hanya mengembalikan baris dengan `tanggal_data` = hari ini.
- [ ] `GET /checkin_pagi?tanggal=YYYY-MM-DD` hanya mengembalikan baris di tanggal tersebut.
- [ ] `GET /checkin_pagi?tanggal=YYYY-MM-DD&q=...` menggabungkan filter tanggal dan pencarian teks.
- [ ] Halaman lain yang tidak mengisi `date_filter_column` (misal `/provinsi`) tidak terpengaruh sama sekali.
- [ ] Props Inertia membawa informasi tanggal yang sedang difilter (dan bahwa halaman ini punya filter tanggal) supaya bisa dipakai frontend di ticket berikutnya.
- [ ] Feature test baru membuktikan keempat skenario query di atas, mengikuti pola `tests/Feature/MenuTest.php`.

## Blocked by

None (can start immediately).
