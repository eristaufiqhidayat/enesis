## Parent

docs/backlog_items/016-gabung-menu-motoris-tab/spec.md

## What to build

Tambahkan redirect dari tiga slug lama (`/motoris_coverage`, `/motoris_performance`, `/monitor_absensi_motoris`) ke slug halaman gabungan (`/motoris_report`) dengan query `tab` yang sesuai, supaya bookmark/link lama tidak 404 setelah ketiga menu digabung.

## Acceptance criteria

- [x] GET `/motoris_coverage` me-redirect (302) ke `/motoris_report?tab=<tab coverage>`.
- [x] GET `/motoris_performance` me-redirect (302) ke `/motoris_report?tab=<tab performance>`.
- [x] GET `/monitor_absensi_motoris` me-redirect (302) ke `/motoris_report?tab=<tab absensi>`.
- [x] Query string lain yang dikirim ke slug lama (misal `q`) tetap diteruskan ke redirect target, tidak hilang.
- [x] Feature test di `tests/Feature/MenuTest.php` meng-assert ketiga redirect di atas (status code + target URL/query).

## Blocked by

- Ticket 01 (gabung menu & sidebar) — redirect butuh slug dan `tab` gabungan yang baru tersedia setelah menu digabung.
