# Pengaturan ambang dan halaman konfigurasi

## Parent

Spec: [docs/backlog_items/054-indikasi-jarak-dekat-kunjungan/spec.md](../spec.md)

## What to build

Ambang (jarak dekat meter + minimum kunjungan berturutan) disimpan di `pengaturan_admin` (tabel kunci→nilai yang sudah ada, bukan tabel baru), lewat `App\Support\Tracking\PengaturanDeteksiKunjungan`, controller, dan halaman admin untuk mengubahnya — supaya ambang bisa disesuaikan tanpa ubah kode, sesuai permintaan eksplisit pengguna.

## Acceptance criteria

- [x] Instalasi baru punya baris default (`jarak_dekat_meter` 5, `min_kunjungan_berturutan` 4) dari migration seed.
- [x] Halaman menampilkan nilai aktif dan bisa menyimpan nilai baru (integer positif saja).
- [x] Perubahan tercatat di `riwayat_admin` dengan nilai lama/baru.
- [x] Ditolak 403 tanpa hak ubah — disesuaikan ke menu tersendiri `deteksi_kunjungan` (bukan `outlet_all`), supaya tampil sebagai halaman sendiri di sidebar dengan hak lihat/ubah normal, konsisten dengan menu "Pengaturan" lain (`target_setting`, `holiday_dates`); lihat Asumsi desain di spec.

## Implementation steps

- [x] `App\Support\Tracking\PengaturanDeteksiKunjungan` (`ambil`/`simpan`, kunci `deteksi_kunjungan.*` di `pengaturan_admin`, pola yang sama dengan `RekapPencapaianSku::target`/`simpanTarget`) — dipakai sebagai pengganti tabel baru yang semula direncanakan di spec; config fallback `marketama.deteksi_jarak_kunjungan`.
- [x] `PengaturanDeteksiKunjunganController` (`index`, `update`) + rute, `hak:deteksi_kunjungan` / `hak:deteksi_kunjungan,ubah`.
- [x] `Pages/Pengaturan/DeteksiKunjungan.vue` (form dua angka), terdaftar di menu Master Data & Pengaturan > Konfigurasi.
- [x] Feature test `PengaturanDeteksiKunjunganTest`.
- [ ] Vitest `DeteksiKunjungan.spec.js` — dilewati: codebase ini belum punya preseden menguji komponen Page penuh (yang membungkus AppLayout/usePage/router) secara terisolasi, hanya partial seperti TandaMotoris/TandaOutlet. Perilaku halaman (nilai tampil, submit, validasi, hak) sudah tercakup penuh oleh `PengaturanDeteksiKunjunganTest` lewat Inertia testing.

## Blocked by

None
