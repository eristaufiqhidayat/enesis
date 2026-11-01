# Hapus halaman dan pindahkan izin modal ke slugRiwayat

## Parent

Spec: [spec.md](../spec.md)

## What to build

Hapus halaman, endpoint `counts`, entri sidebar/tab/pencarian untuk Riwayat Lokasi sales; pertahankan slug sebagai hak akses; modal Riwayat membaca izin dari shared prop `slugRiwayat`.

## Acceptance criteria

- [x] `/tracking_sales_history_location` dialihkan ke `/tracking_sales`; `/counts` tidak ada (404); `/locations` tetap berfungsi dengan hak yang sama.
- [x] Sidebar, tab, dan pencarian tidak lagi memuat Riwayat Lokasi sales; matriks hak role masih memuatnya.
- [x] Tombol "Lihat riwayat" dan modal rekap Ditandai tetap tampil hanya bagi akun yang punya hak `tracking_sales` dan `tracking_sales_history_location`.
- [x] Halaman riwayat koordinator tidak berubah.

## Blocked by

None
