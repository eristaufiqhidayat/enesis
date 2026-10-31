# Tombol batalkan muatan di drawer detail

## Parent

Spec: [docs/backlog_items/041-batalkan-muatan-disiapkan/spec.md](../spec.md)

## What to build

Di drawer detail muatan (Daftar Muatan Motoris), muatan Disiapkan mendapat tombol "Batalkan muatan" dengan modal konfirmasi. Setelah dibatalkan, drawer menutup, muncul toast sukses, dan baris hilang dari tabel daftar.

## Acceptance criteria

- [x] Tombol "Batalkan muatan" (ikon, varian `outline-danger`) hanya muncul bila `dapat_dibatalkan` dan tidak sedang dalam mode ubah.
- [x] Klik membuka modal konfirmasi yang menyebut nomor muatan dan akibatnya; Batal menutup modal tanpa perubahan.
- [x] Konfirmasi sukses: toast sukses, drawer menutup, tabel daftar dimuat ulang tanpa muatan itu; tombol menampilkan spinner selama proses.
- [x] Bila muatan ternyata sudah diterima (409): toast galat, modal menutup, dan drawer memuat ulang isi terbaru tanpa tombol batalkan.
- [ ] Tampilan benar di lebar sempit. _(belum diverifikasi di browser)_

## Implementation steps

- [x] Tambah tombol dan modal konfirmasi di `DetailMuatanCanvassing.vue` (pakai `Modal`, `Button`, `Icon`, `useToast`); kirim `DELETE /barang_canvassing/{id}` dengan `X-CSRF-TOKEN`; emit `dibatalkan` saat sukses.
- [x] `Show.vue` menutup drawer dan memuat ulang tabel saat menerima `dibatalkan`.
- [ ] Verifikasi manual di browser (alur lengkap, 409 saat status berubah, lebar sempit); `php artisan test`, `npm test`, dan `vendor/bin/pint`. _(Tes PHP, Vitest, pint, alur lengkap, dan 409 di browser sudah; lebar sempit belum.)_

## Blocked by

- `01-migration-endpoint-batalkan-muatan`
