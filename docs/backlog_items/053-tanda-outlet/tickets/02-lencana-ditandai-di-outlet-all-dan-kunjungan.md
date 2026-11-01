# Lencana "Ditandai" di All Outlet dan daftar kunjungan

## Parent

Spec: [docs/backlog_items/053-tanda-outlet/spec.md](../spec.md)

## What to build

Kolom/lencana "Ditandai" di menu Master Data > All Outlet (`outlet_all`), dan properti `outlet_ditandai` pada tiap baris `KunjunganHarian::untuk` supaya daftar kunjungan drawer dan popover peta bisa menampilkan lencana yang sama.

## Acceptance criteria

- [x] `outlet_all` menampilkan lencana "Ditandai" untuk outlet dengan tanda aktif; hilang setelah tanda itu dicabut.
- [x] `KunjunganHarian::untuk` menyertakan `outlet_ditandai` (bool) per kunjungan, sesuai status tanda outlet saat ini.

## Implementation steps

- [x] Tambah exists-subquery ke `tanda_outlet` aktif pada sumber tabel `outlet_all` (`SumberOutletMaster`, kolom `ditandai` berupa COUNT, bukan boolean mentah — DataTable menampilkannya sebagai badge jumlah).
- [x] `KunjunganHarian::untuk` join/exists ke `tanda_outlet` aktif berdasar `outlet_id`.
- [x] Feature test: lencana muncul/hilang di `outlet_all` dan di `kunjungan.outlet_ditandai`.

## Blocked by

- [01-tabel-endpoint-hak-dan-audit.md](01-tabel-endpoint-hak-dan-audit.md)
