## Parent

docs/backlog_items/001-perbaikan-sistem-autentikasi/spec.md

## What to build

Ganti form login minimal dari ticket pertama dengan tampilan yang memakai komponen UI atomic design yang sudah ada di repo (`x-ui.atoms.input`, `x-ui.atoms.label`, `x-ui.atoms.button`, `x-ui.molecules.card`, `x-ui.molecules.alert`), sesuai aturan wajib di `CLAUDE.md`. Tidak ada perubahan pada logika login/logout, hanya tampilannya.

## Acceptance criteria

- [x] Halaman `/login` disusun dari komponen `x-ui.*` yang sudah ada, bukan markup mentah (`<div class="card">`, `<button class="btn">`, dst. yang ditulis ulang).
- [x] Pesan error validasi ditampilkan lewat komponen `x-ui.molecules.alert`.
- [x] Tampilan responsif dan konsisten dengan gaya visual aplikasi (lihat `resources/css/app.css`).
- [x] Test feature dari ticket pertama tetap lulus tanpa perubahan assertion yang bergantung pada markup.

## Blocked by

- Ticket "Rute login & logout dasar"
