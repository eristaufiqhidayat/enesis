## Parent

docs/backlog_items/011-gabung-menu-monitoring-harian-tab/spec.md

## What to build

Tambahkan redirect dari tiga slug lama (`/checkin_pagi`, `/sales_tidak_penjualan`, `/sales_tidak_absen`) ke slug halaman gabungan dengan query `tab` yang sesuai, supaya bookmark/link lama (termasuk penyebutan `same_as` di menu lain yang mungkin mengarah ke slug ini) tidak 404 setelah ketiga menu digabung.

## Acceptance criteria

- [x] GET `/checkin_pagi` me-redirect (302) ke slug gabungan dengan `?tab=` yang menunjuk tab checkin.
- [x] GET `/sales_tidak_penjualan` me-redirect (302) ke slug gabungan dengan `?tab=` yang menunjuk tab tidak-penjualan.
- [x] GET `/sales_tidak_absen` me-redirect (302) ke slug gabungan dengan `?tab=` yang menunjuk tab tidak-absen.
- [x] Query string lain yang dikirim ke slug lama (misal `q`, `tanggal`) tetap diteruskan ke redirect target, tidak hilang.
- [x] Feature test di `tests/Feature/MenuTest.php` meng-assert ketiga redirect di atas (status code + target URL/query).

## Blocked by

- Ticket 02 (tab UI & query param) — redirect butuh resolusi `tab` yang valid untuk masing-masing slug lama, yang baru tersedia setelah mekanisme `tab` ada.
