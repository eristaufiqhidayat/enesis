## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Selama migrasi berjalan, halaman Inertia (menu tabel) dan halaman Blade (role, akun, kehadiran, tracking, design area, coming soon) hidup berdampingan. Sidebar Vue memakai `<Link>` Inertia untuk semua menu, termasuk menu yang tujuannya masih halaman Blade, dan aplikasi Inertia tidak menangani respons non-Inertia. Akibatnya, klik menu Blade dari halaman tabel kemungkinan menampilkan HTML halaman itu di dalam modal Inertia, bukan membuka halamannya.

Tiket ini memastikan navigasi dari halaman Inertia ke halaman yang bukan Inertia selalu berakhir sebagai pindah halaman biasa (full reload) ke URL tujuan.

## Acceptance criteria

- [ ] Klik menu Blade (mis. Role, Akun, Kehadiran, Tracking) dari halaman Inertia (mis. `/home`) membuka halaman itu secara utuh, tanpa modal berisi HTML.
- [ ] Hasil pencarian menu di sidebar Vue yang menuju halaman Blade juga membuka halaman secara utuh.
- [ ] Navigasi antar halaman Inertia tetap tanpa full reload.
- [ ] Respons error asli (mis. 403 dari middleware `hak`, 500) tidak tertelan diam-diam: user tetap melihat halaman error-nya.

## Implementation steps

- [ ] Reproduksi dulu di browser: buka `/home`, klik menu Role/Akun di sidebar, catat apa yang terjadi (modal HTML atau tidak). Kalau tidak terjadi, tulis temuannya di tiket dan tutup tanpa perubahan kode.
- [ ] Tangani event respons non-Inertia di inisialisasi aplikasi Inertia (`resources/js/app.js`): batalkan modal bawaan dan arahkan browser ke URL respons dengan pindah halaman biasa.
- [ ] Verifikasi manual: navigasi Inertia → Blade (sidebar & pencarian), Inertia → Inertia, dan akses menu tanpa hak.
- [ ] `npm run build` lulus.

## Blocked by

None (can start immediately).
