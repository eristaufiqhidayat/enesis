# Kunjungan outlet di respons endpoint locations (Sales)

## Parent

Spec: [docs/backlog_items/044-daftar-kunjungan-di-drawer-riwayat/spec.md](../spec.md)

## What to build

`KunjunganHarian::untuk` dan kunci `kunjungan` pada respons `GET /tracking_sales_history_location/locations`, dibatasi cakupan role.

## Acceptance criteria

- [x] `kunjungan` berisi kunjungan motoris pada tanggal itu, urut jam checkin, dengan outlet, jam masuk/keluar (WIB), durasi menit, koordinat, `beli`, `di_luar_rute`, `lokasi_palsu`, keterangan tidak beli.
- [x] Nama outlet dari `mk_outlet` atau `trx_outlet_baru`; `beli` hanya menghitung penjualan yang tidak dibatalkan.
- [x] Motoris di luar cakupan role akun → `kunjungan` kosong; endpoint koordinator tanpa kunci `kunjungan`.

## Implementation steps

- [x] `app/Support/Tracking/KunjunganHarian.php`.
- [x] `TrackingSalesController@locations`: tambah `kunjungan` dengan cek cakupan.
- [x] Feature test di `TrackingSalesTest`.

## Blocked by

None
