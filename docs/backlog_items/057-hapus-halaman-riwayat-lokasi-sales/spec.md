# Hapus halaman Riwayat Lokasi sales (digantikan modal Riwayat)

## Latar belakang

Spec 042 menambah modal Riwayat di tabel Lacak Motoris (Last Seen) di samping halaman penuh `/tracking_sales_history_location`. Setelah spec 056 (pusat kendali), modal memuat semua yang ada di halaman itu dan jauh lebih banyak (kunjungan outlet, KPI, temuan, tanda, navigasi hari). Halaman penuh hanya menggambar rute, jadi tinggal duplikasi yang harus dirawat dua kali.

## Cakupan

- Halaman `GET /tracking_sales_history_location` dihapus; tautan lama dialihkan (redirect) ke `/tracking_sales`.
- Endpoint `counts` (jumlah titik per sales per tanggal, hanya dipakai halaman itu) dihapus.
- Endpoint `locations` tetap: dipakai modal Riwayat, dengan middleware hak yang sama.
- Slug `tracking_sales_history_location` tetap ada sebagai **hak akses** di matriks role (judul "Riwayat Lokasi Motoris"), ditandai `tanpa_halaman` di config menu sehingga tidak tampil di sidebar, tab, maupun pencarian. Role yang sudah punya hak itu tidak berubah perilakunya.
- Tab Lacak Motoris tinggal Last Seen dan Ditandai untuk sales.
- Izin membuka modal dari tabel tidak lagi dibaca dari tab halaman, tetapi dari shared prop baru `slugRiwayat` (slug di `marketama.slug_riwayat` yang boleh diakses akun).
- Halaman Riwayat Lokasi koordinator (`/tracking_koordinator_history_location`) tidak berubah; komponen `Tracking/History.vue` tetap dipakai olehnya.

## Di luar cakupan

- Menghapus halaman koordinator.
- Membuka modal otomatis dari tautan lama berparameter (`?sales_id=…&tanggal=…`); redirect hanya membawa ke Lacak Motoris.
- Mengubah slug atau nama hak di database role.

## Asumsi desain

- Hak `tracking_sales_history_location` tetap diperlukan bersama `tracking_sales` untuk modal Sales (aturan lama dipertahankan).
- Memilih motoris untuk dilihat riwayatnya cukup lewat tabel Last Seen: setiap motoris yang pernah punya data lokasi punya baris di sana, dan tanggal dapat diganti di modal.
