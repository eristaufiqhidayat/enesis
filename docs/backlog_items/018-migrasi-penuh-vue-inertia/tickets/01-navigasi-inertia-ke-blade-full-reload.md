## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Selama migrasi berjalan, halaman Inertia (menu tabel) dan halaman Blade (role, akun, kehadiran, tracking, design area, coming soon) hidup berdampingan. Sidebar Vue memakai `<Link>` Inertia untuk semua menu, termasuk menu yang tujuannya masih halaman Blade, dan aplikasi Inertia tidak menangani respons non-Inertia. Akibatnya, klik menu Blade dari halaman tabel kemungkinan menampilkan HTML halaman itu di dalam modal Inertia, bukan membuka halamannya.

Tiket ini memastikan navigasi dari halaman Inertia ke halaman yang bukan Inertia selalu berakhir sebagai pindah halaman biasa (full reload) ke URL tujuan.

## Acceptance criteria

- [x] Klik menu Blade (mis. Role, Akun, Kehadiran, Tracking) dari halaman Inertia (mis. `/home`) membuka halaman itu secara utuh, tanpa modal berisi HTML.
- [x] Hasil pencarian menu di sidebar Vue yang menuju halaman Blade juga membuka halaman secara utuh.
- [x] Navigasi antar halaman Inertia tetap tanpa full reload.
- [ ] Respons error asli (mis. 403 dari middleware `hak`, 500) tidak tertelan diam-diam: user tetap melihat halaman error-nya.
  - Status: sebagian. Akun Koordinator (demo masuk cepat) membuka `/role` langsung dan mendapat halaman 403 asli. Belum diuji: 403 yang muncul dari kunjungan Inertia (navigasi di dalam aplikasi), karena menu tanpa hak tidak tampil di sidebar.

## Implementation steps

- [x] Reproduksi dulu di browser: buka `/home`, klik menu Role/Akun di sidebar, catat apa yang terjadi (modal HTML atau tidak). Kalau tidak terjadi, tulis temuannya di tiket dan tutup tanpa perubahan kode.
  - Hasil: tidak terjadi. Dengan penanganan non-Inertia dinonaktifkan sementara, klik menu Role dari halaman Inertia (`/monitoring_harian_sales`) membuka `/role` sebagai halaman utuh tanpa modal HTML (Inertia 3.9.1). Uji klik mouse sungguhan belum bersih karena grup sidebar bolak-balik terbuka/tertutup, sehingga hasil ini belum sepenuhnya meyakinkan.
  - Tindak lanjut: penanganan non-Inertia tidak ditambahkan, sesuai aturan tiket. Tiket ditutup tanpa perubahan kode.
- [ ] Tangani event respons non-Inertia di inisialisasi aplikasi Inertia (`resources/js/app.js`): batalkan modal bawaan dan arahkan browser ke URL respons dengan pindah halaman biasa.
  - Status: tidak dikerjakan. Bug tidak terbukti terjadi (lihat langkah reproduksi di atas), jadi tiket ditutup tanpa perubahan kode.
- [ ] Verifikasi manual: navigasi Inertia → Blade (sidebar & pencarian), Inertia → Inertia, dan akses menu tanpa hak.
  - Hasil (tanpa penanganan non-Inertia, sesuai kode saat ini): Inertia `/monitoring_harian_sales` → klik "Role & Hak Akses" di sidebar membuka `/role` utuh tanpa modal. Pencarian sidebar "Tracking" dari halaman Inertia yang sama membuka `/tracking_sales` utuh tanpa modal. Inertia → Inertia (`/monitoring_harian_sales` → `/rekap_pencapaian_sku`) tetap tanpa full reload. Akses tanpa hak: lihat AC 4.
- [x] `npm run build` lulus.

## Blocked by

None (can start immediately).
