# Kode Outlet di Drawer Riwayat Lacak Motoris

## Problem Statement
Daftar kunjungan dan popover penanda di drawer Riwayat hanya menampilkan nama outlet. Nama outlet tidak unik dan sering ditulis bebas oleh motoris ("Toko Syifa", "toko Wawan"), sehingga admin sulit mencocokkan kunjungan dengan data outlet di menu lain (Outlet All, laporan) yang memakai kode outlet.

## Solution
Tampilkan kode outlet (kolom `mk_outlet.kode`, mis. `OUT-70986`) di bawah nama outlet pada daftar kunjungan dan di bawah judul bernomor pada popover penanda, dengan gaya `.kode-mono` yang sudah ada. Kunjungan ke outlet baru dari HP yang belum disetujui belum punya kode, sehingga baris kode tidak ditampilkan.

## User Stories
1. Sebagai admin, saya ingin melihat kode outlet di setiap kunjungan, supaya bisa mencocokkannya dengan data outlet di menu lain tanpa menebak dari nama.
2. Sebagai admin, saya ingin kunjungan ke outlet baru yang belum punya kode tetap tampil normal, tanpa baris kode kosong.

## Implementation Decisions
- **`KunjunganHarian::untuk`** menambah `o.kode as kode_outlet` pada query yang sudah melakukan join ke `mk_outlet`, dan mengirim `kode_outlet` (string atau `null` untuk outlet baru).
- **Daftar kiri** (`RuteHarianPeta.vue`): `<span class="block kode-mono text-gray-500">` di bawah nama outlet bila `kode_outlet` ada.
- **Popover** (`Support/rute.js`): baris `<small class="kode-mono text-gray-500">` di bawah judul bernomor, di-escape.
- Tidak ada perubahan skema, hak akses, atau pencarian.

## Testing Decisions
- Feature test (`KunjunganDrawerRiwayatTest`): `kode_outlet` terisi untuk outlet master dan `null` untuk outlet baru.
- Vitest: daftar menampilkan kode bila ada dan menyembunyikannya bila `null`; popover memuat kode, di-escape, dan tanpa baris kode bila kosong.

## Out of Scope
- Pencarian atau filter berdasarkan kode di daftar kunjungan.
- Kode untuk outlet baru dari HP sebelum disetujui.
- Alamat, area, atau data outlet lain dari master.
