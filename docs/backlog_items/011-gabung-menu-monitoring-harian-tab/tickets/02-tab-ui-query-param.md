## Parent

docs/backlog_items/011-gabung-menu-monitoring-harian-tab/spec.md

## What to build

Tambahkan tab navigasi di bagian atas halaman gabungan (satu tab per tabel/menu lama), dengan tab aktif dikendalikan lewat query string (`?tab=`) sehingga URL selalu mencerminkan tab yang sedang dibuka. Hanya tabel, form pencarian/filter tanggal, dan KPI card milik tab aktif yang ditampilkan — bukan ketiganya sekaligus seperti sekarang. Sidebar diperbarui: tiga item menu lama diganti satu item menu menuju slug gabungan.

## Acceptance criteria

- [x] Komponen tab reusable baru (bukan khusus tiga menu ini) dipakai di halaman gabungan, mengikuti aturan komponen atomic design (CLAUDE.md) — bisa dipakai lagi untuk halaman multi-tab lain di masa depan.
- [x] `MenuController@show` membaca query `tab`, memvalidasinya terhadap daftar tabel yang valid, default ke tab pertama kalau kosong/tidak valid, dan mengirim `activeTab` ke Inertia.
- [x] Hanya `DataTable` + form pencarian/filter tanggal + KPI card untuk tab aktif yang dirender; tab lain tidak ikut tampil sekaligus.
- [x] Klik tab lain memicu navigasi (`router.get`) yang mengubah query `tab` di URL dan memuat data tab tersebut.
- [x] Sidebar (Blade `sidebar.blade.php` dan Vue `Sidebar.vue`) hanya punya satu item menu untuk halaman gabungan ini, tiga item lama dihapus.
- [x] Feature test di `tests/Feature/MenuTest.php` meng-assert: default `activeTab` saat `tab` kosong, `activeTab` berubah sesuai query `tab` yang valid, dan `activeTab` fallback ke default saat query `tab` tidak valid.

## Blocked by

- Ticket 01 (konfigurasi & MenuController filter per-tabel) — butuh struktur config/tabel per-tabel sudah ada sebelum tab bisa memilih di antaranya.
