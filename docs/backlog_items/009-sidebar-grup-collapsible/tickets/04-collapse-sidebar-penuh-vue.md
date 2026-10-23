## Parent

docs/backlog_items/009-sidebar-grup-collapsible/spec.md

## What to build

Tambah mode collapse untuk **seluruh sidebar** jadi strip ikon-saja di stack Vue (`resources/js/Components/ui/organisms/Sidebar.vue` + layout pembungkusnya), dengan state yang kompatibel dengan versi Blade (ticket 03) lewat `localStorage` key yang sama (`sidebar-collapsed-full`, `'1'`/tidak ada):

1. Tambah `ref` `collapsed` di `Sidebar.vue`, diinisialisasi dari `localStorage.getItem('sidebar-collapsed-full') === '1'` saat `onMounted` (pola sama seperti `SCROLL_KEY`, baris 14-23).
2. Tambah tombol toggle di `.brand` (template, dekat judul `{{ page.props.appName ?? 'Enesis Canvassing' }}`, baris 38) yang men-toggle `collapsed.value` dan menulis `'1'`/menghapus key di `localStorage`.
3. Cari layout pembungkus Sidebar (`resources/js/Layouts/AppLayout.vue`) — tambah class `sidebar-collapsed` ke elemen root layout (bukan ke `<nav class="sidebar">` itu sendiri) secara reaktif, dibaca dari state `collapsed` yang di-emit/provide dari `Sidebar.vue` (gunakan `provide`/`inject` atau event sederhana, konsisten dengan cara state lain dilempar antar komponen Vue di proyek ini — cek pola existing sebelum menambah mekanisme baru).
4. Tambah `:title="item.title"` / `:title="child.title"` pada tiap `<Link>`/`<a class="nav-link">` di template (baris 44-68) supaya tooltip native muncul saat mode icon-only.
5. Saat `collapsed.value` true dan user klik item berChildren (`toggleSubmenu`, baris 31-33): set `collapsed.value = false` (dan update `localStorage`) dulu sebelum toggle submenu-nya, supaya sidebar expand dulu — sama seperti behavior Blade (ticket 03).
6. CSS yang dipakai sama dengan yang ditambahkan di `app.css` oleh ticket 03 (`body.sidebar-collapsed ...` atau selector root layout yang dipakai di langkah 3 — samakan nama class dengan yang sudah didefinisikan ticket 03 supaya tidak duplikasi aturan CSS).

## Acceptance criteria

- [x] Tombol toggle di `.brand` sidebar Vue mempersempit/melebarkan seluruh sidebar jadi strip ikon-saja dan kembali, tanpa reload, perilaku sama dengan versi Blade (ticket 03) untuk input yang sama.
- [x] Toggle menulis ke `localStorage` key `sidebar-collapsed-full` dengan format yang sama seperti yang dibaca/ditulis versi Blade (ticket 03) — collapse di satu stack lalu pindah ke stack lain (atau reload) menghasilkan state yang sama.
- [x] Saat mode icon-only aktif, hover ikon menu menampilkan tooltip native (`title`) berisi nama menu.
- [x] Klik item berChildren saat mode icon-only aktif meng-expand sidebar kembali ke mode normal lebih dulu (bukan flyout).
- [x] Navigasi Inertia antar halaman tidak mereset state collapsed (sidebar di-mount ulang tiap navigasi, state harus tetap terbaca dari `localStorage` setiap kali).
- [x] Tidak ada exception di console saat `localStorage` kosong/belum pernah diisi (first visit).

## Blocked by

- Ticket 03 (collapse sidebar penuh Blade) — perlu format `localStorage` (key, value) dan nama class CSS yang sudah ditetapkan di sana supaya kedua stack kompatibel dan tidak duplikasi aturan CSS.
