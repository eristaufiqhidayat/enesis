# Tabel, endpoint, hak, dan audit tanda motoris

## Parent

Spec: [docs/backlog_items/049-tanda-motoris-di-drawer-riwayat/spec.md](../spec.md)

## What to build

Tabel `tanda_motoris`, tiga endpoint JSON (daftar, tambah, cabut) dengan pengecekan hak dan cakupan role, pencatatan riwayat admin, dan hak Tambah bawaan untuk role Koordinator.

## Acceptance criteria

- [x] Daftar hanya memuat tanda aktif pada motoris dan tanggal yang diminta, lengkap dengan `bisa_tambah`, `bisa_cabut`, dan daftar kategori.
- [x] Tambah ditolak 403 tanpa hak Tambah, 404 untuk motoris di luar cakupan atau bukan kategori sales, 422 untuk kategori tak dikenal, tanggal masa depan, dan catatan lebih dari 500 karakter.
- [x] Cabut hanya oleh pemberi tanda atau akun dengan hak Hapus; soft (baris tetap, `dicabut_at` dan `dicabut_oleh` terisi).
- [x] Tambah dan cabut tercatat di `riwayat_admin`.
- [x] Role bawaan Koordinator mendapat hak Tambah pada `tracking_sales` (migration data dan `RoleBawaan::pasang`).

## Implementation steps

- [x] Migration `tanda_motoris` dan config `marketama.tanda_motoris`.
- [x] `TandaMotorisController` + tiga rute sebelum catch-all.
- [x] Migration data hak koordinator + `RoleBawaan`.
- [x] Feature test `TandaMotorisTest`.

## Blocked by

None
