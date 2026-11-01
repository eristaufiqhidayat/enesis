# Tabel, endpoint, hak, dan audit tanda outlet

## Parent

Spec: [docs/backlog_items/053-tanda-outlet/spec.md](../spec.md)

## What to build

Tabel `tanda_outlet` (melekat ke `mk_outlet`, bukan ke kunjungan/motoris), tiga endpoint JSON (daftar, tambah, cabut) dengan pengecekan hak menu `outlet_all`, dan pencatatan riwayat admin — mengikuti pola `tanda_motoris` (spec 049) tapi tanpa pembatasan cakupan role dan tanpa kolom tanggal/karyawan.

## Acceptance criteria

- [x] Daftar hanya memuat tanda aktif pada `outlet_id` yang diminta, lengkap dengan `bisa_tambah`, `bisa_cabut`, dan daftar kategori.
- [x] Tambah ditolak 403 tanpa hak Tambah (`outlet_all`), 404 bila `outlet_id` tidak ada di `mk_outlet`, 422 untuk kategori tak dikenal dan catatan lebih dari 500 karakter.
- [x] Cabut hanya oleh pemberi tanda atau akun dengan hak Hapus (`outlet_all`); soft (baris tetap, `dicabut_at` dan `dicabut_oleh` terisi).
- [x] Tambah dan cabut tercatat di `riwayat_admin`.

## Implementation steps

- [x] Migration `tanda_outlet` dan config `marketama.tanda_outlet` (kategori: Outlet fiktif, Alamat/lokasi tidak sesuai, Pola kunjungan tidak wajar, Potensi kecurangan lainnya).
- [x] `TandaOutletController` (`index`, `store`, `cabut`) + tiga rute (`/outlet_all/tanda`) sebelum catch-all, `hak:outlet_all` / `hak:outlet_all,tambah`.
- [x] Migration data hak Tambah koordinator pada `outlet_all` + `RoleBawaan::pasang` (mengikuti pola tanda motoris).
- [x] Feature test `TandaOutletTest`.

## Blocked by

None
