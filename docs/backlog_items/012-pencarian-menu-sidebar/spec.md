# Input Pencarian Menu di Top Sidebar

## Problem Statement

Sidebar punya 9 grup dan 119 menu (`config('marketama.menu')`, dibaca lewat `App\Support\Akses\Akses::menu($user)`). Ticket 009/010 sudah menambah collapse per grup dan tombol collapse/expand semua grup di `.brand` (Blade: `resources/views/components/ui/organisms/sidebar.blade.php:3-7`; Vue: `resources/js/Components/ui/organisms/Sidebar.vue:129-141`), tapi itu hanya membantu meringkas tampilan, bukan mencari. Kalau user tahu nama menu yang dicari (misal "Checkin") tapi tidak hafal grup mana tempatnya, satu-satunya cara adalah scroll manual atau expand semua grup lalu baca satu-satu dari 119 menu.

Saat ini bagian atas sidebar (`.brand`) hanya berisi dua tombol collapse, langsung disambung daftar grup/menu di `.sidebar-scroll` tanpa pembatas visual (`app.css:24`).

## Solution

Tambahkan satu input pencarian menu di bagian paling atas sidebar (dalam `.brand`, di bawah baris tombol collapse yang sudah ada), yang memfilter seluruh 119 menu (termasuk menu di dalam `children`) berdasarkan judul, dan menampilkan hasilnya sebagai dropdown kecil tepat di bawah input — bukan memfilter/collapse daftar grup yang sudah ada, supaya tidak bertabrakan dengan mekanisme collapse per grup (009/010).

1. **Markup & penempatan:** tambah `<div class="sidebar-search">` di dalam `.brand`, di bawah `.brand-actions`, berisi `<input type="search" data-sidebar-search placeholder="Cari menu...">` dan sebuah container dropdown hasil (`<ul data-sidebar-search-results class="sidebar-search-results hidden">`) yang posisinya `position: absolute` menempel tepat di bawah input (bukan ikut mendorong layout grup di bawahnya).
2. **Sumber data pencarian:** flatten seluruh menu (judul + slug + ikon) dari `Akses::menu($user)` jadi array datar di sisi masing-masing stack — Blade: `@json(...)` sekali di awal (mengikuti pola `currentSlug` yang sudah ada di baris 1), Vue: `computed` yang meratakan `menu.value` (item biasa + `item.children`) — bukan request AJAX baru, karena data menu ini sudah tersedia di render pertama di kedua stack.
3. **Behavior ketik:** tiap `input` event, filter array datar dengan `title.toLowerCase().includes(query.toLowerCase())` (case-insensitive, substring, tanpa fuzzy match), tampilkan maksimal 8 hasil teratas di dropdown; dropdown tersembunyi (`hidden`) saat query kosong atau input belum difokus.
4. **Klik/Enter hasil:** klik salah satu baris hasil akan navigasi ke menu tersebut — Blade: `<a href="/{{ $item['slug'] }}">` biasa (full page load, konsisten dengan nav link lain di Blade); Vue: `<Link :href="`/${item.slug}`">` dari Inertia (konsisten dengan `nav-link` lain di `Sidebar.vue`). Tidak ada hasil (`title` tidak match apa pun) → tampilkan satu baris non-klik "Tidak ditemukan".
5. **Menutup dropdown:** `Escape` pada input, atau klik di luar area `.sidebar-search` (listener `click` di `document`, cek `event.target.closest('.sidebar-search')`), menyembunyikan dropdown tanpa mengubah isi query di input.
6. **Tampilan (CSS, `app.css`):** tambah `border-bottom: 1px solid rgba(255, 255, 255, .2)` pada `.brand` (reuse warna border yang sama dengan `.section-label` di `app.css:35`) supaya area tombol+pencarian di atas kelihatan terpisah dari daftar grup/menu di `.sidebar-scroll` di bawahnya. Dropdown hasil (`.sidebar-search-results`) diberi `position: absolute`, lebar penuh sidebar, background sedikit lebih gelap dari `.sidebar` (`rgba(0, 0, 0, .25)`), `border-radius`, dan `box-shadow` tipis supaya terlihat "mengambang" di atas daftar menu — pola dropdown kecil standar, bukan modal/overlay penuh.
7. **Mode sidebar collapsed (icon-only, `body.sidebar-collapsed .sidebar:not(:hover)`):** input pencarian ikut disembunyikan (`display: none`) bersama `.nav-link-text`/`.section-label`/`.nav-chevron` yang sudah disembunyikan di mode itu (`app.css:46-48`) — tidak ada mode "search icon-only" baru; search hanya relevan saat sidebar dalam lebar penuh (termasuk saat hover sementara, sesuai perilaku existing di `app.css:51`).
8. **Breakpoint mobile (≤991px):** tidak perlu ditangani khusus — pada breakpoint itu `.sidebar .brand` sudah `display: none` (`app.css:118`), jadi input pencarian otomatis tidak tampil, konsisten dengan dua tombol collapse yang sudah disembunyikan di breakpoint yang sama.

## User Stories

1. Sebagai pengguna admin yang tahu nama menu yang dicari tapi tidak tahu ada di grup mana dari 9 grup, saya ingin mengetik sebagian judul menu di sidebar dan langsung melihat daftar menu yang cocok, supaya saya tidak perlu scroll atau expand semua grup satu-satu.
2. Sebagai pengguna admin yang sudah mengetik dan melihat hasil pencarian, saya ingin klik salah satu hasil langsung membuka menu itu, supaya saya tidak perlu menutup dropdown dan cari manual lagi di daftar grup.
3. Sebagai pengguna admin yang mengetik kata yang tidak cocok dengan menu apa pun, saya ingin melihat pesan "tidak ditemukan" yang jelas, bukan dropdown kosong yang membingungkan.
4. Sebagai pengguna admin yang sudah selesai mencari, saya ingin dropdown hasil tertutup saat saya klik di luar area pencarian atau tekan Escape, supaya tidak menutupi daftar menu di bawahnya terus-menerus.
5. Sebagai pengguna admin yang sidebar-nya sedang dipersempit jadi strip ikon, saya tidak ingin area pencarian memakan tempat yang sudah terbatas — cukup sembunyikan sampai sidebar dilebarkan lagi.

## Implementation Decisions

- Pencarian ini **tidak** memfilter/collapse daftar grup yang sudah dirender (beda mekanisme dari collapse per grup di 009/010) — hasil pencarian murni tampil di dropdown terpisah yang mengambang di atas daftar menu, supaya tidak perlu mengubah state `sidebar-collapsed-groups` yang sudah ada.
- Pencocokan hanya berdasarkan `title` menu (bukan `slug`, bukan isi tabel data di dalam menu), case-insensitive substring match — cukup untuk 119 menu dengan judul pendek, tidak perlu library fuzzy-search tambahan.
- Hasil dibatasi 8 baris teratas (urutan sesuai urutan menu di `config('marketama.menu')`, bukan diurutkan ulang) — mencegah dropdown terlalu panjang kalau query terlalu umum (misal huruf tunggal yang cocok ke banyak menu).
- Tidak ada state yang disimpan ke `localStorage`/`sessionStorage` untuk fitur ini — query pencarian murni state sementara di memori, hilang saat pindah halaman (full reload Blade) atau reload browser, konsisten dengan sifat "search box" yang sifatnya sekali pakai per sesi pencarian.
- Menu dengan `children` (punya submenu) yang judulnya cocok dengan query: baris hasil mengarah ke slug parent-nya kalau parent punya slug sendiri, atau — kalau parent tidak punya slug (murni container submenu, lihat pola existing di `Sidebar.vue:162-174`) — item itu dilewati dari hasil flatten dan hanya child-nya (yang punya slug) yang ikut dicocokkan.
- Border pemisah (`border-bottom` pada `.brand`) dan styling dropdown reuse token warna yang sudah ada di `app.css` (`rgba(255, 255, 255, .2)` untuk border, `rgba(0, 0, 0, .25)`-ish untuk background dropdown mengikuti opacity overlay existing) — tidak menambah custom property warna baru.
- Dropdown hasil ditutup otomatis setelah navigasi (klik hasil atau Enter) karena halaman akan reload penuh (Blade) atau sidebar akan unmount lalu mount ulang (Vue, karena Inertia navigasi mengganti halaman) — tidak perlu logic penutupan eksplisit tambahan untuk kasus ini.

## Testing Decisions

- Test Feature Laravel yang mem-assert markup Blade sidebar memuat elemen `[data-sidebar-search]` (input) dan `[data-sidebar-search-results]` (container dropdown) di dalam `.brand`.
- Verifikasi manual (didokumentasikan di PR, konsisten dengan keputusan testing 009/010 untuk Vue tanpa test runner JS khusus komponen): ketik judul menu yang ada → dropdown tampil berisi menu yang cocok, klik salah satu → navigasi ke menu tersebut; ketik kata yang tidak cocok → tampil "Tidak ditemukan"; tekan Escape atau klik di luar area pencarian → dropdown tertutup; perbesar/perkecil sidebar (mode icon-only) → input pencarian ikut tersembunyi/tampil sesuai lebar sidebar; verifikasi berlaku di kedua stack (Blade & Vue/Inertia).

## Out of Scope

- Pencarian lintas isi tabel data (nama outlet, nama sales, dst.) — ticket ini hanya mencari di antara 119 judul menu, bukan data di dalam masing-masing menu.
- Riwayat pencarian (recent searches) atau highlight kata yang cocok di dalam hasil — cukup daftar judul menu yang match, tanpa fitur tambahan itu.
- Navigasi dengan keyboard arrow (↑/↓) untuk memilih antar hasil dropdown — interaksi pada ticket ini cukup klik mouse atau Enter untuk hasil teratas; navigasi keyboard penuh antar hasil bisa jadi perbaikan terpisah kalau dibutuhkan.
- Mengubah mekanisme collapse per grup, collapse semua grup, atau collapse seluruh sidebar jadi icon-only yang sudah ada (009/010) — fitur ini murni menambah cara baru menemukan menu, tidak mengubah mekanisme collapse yang sudah ada.
- Fuzzy matching / typo-tolerant search — cukup substring match sederhana untuk ticket ini.
