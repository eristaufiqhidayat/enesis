## Parent

docs/backlog_items/015-gabung-rekap-pencapaian-sku-tab/spec.md

## What to build

Tambahkan redirect dari dua slug lama (`/rekap_pencapaian_sku_motoris`, `/rekap_pencapaian_depo_sku`) ke slug halaman gabungan (`/rekap_pencapaian_sku`) dengan query `tab` yang sesuai, supaya bookmark/link lama tidak 404 setelah kedua menu digabung.

## Acceptance criteria

- [x] GET `/rekap_pencapaian_sku_motoris` me-redirect (302) ke `/rekap_pencapaian_sku?tab=motoris`.
- [x] GET `/rekap_pencapaian_depo_sku` me-redirect (302) ke `/rekap_pencapaian_sku?tab=depo`.
- [x] Query string lain yang dikirim ke slug lama (misal `q`) tetap diteruskan ke redirect target, tidak hilang.
- [x] Feature test di `tests/Feature/MenuTest.php` meng-assert kedua redirect di atas (status code + target URL/query).

## Blocked by

- Ticket 02 (gabung menu & sidebar) — redirect butuh slug dan `tab_key` gabungan yang baru tersedia setelah menu digabung.
