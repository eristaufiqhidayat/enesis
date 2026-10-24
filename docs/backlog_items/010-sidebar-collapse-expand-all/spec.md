# Tombol Toggle Collapse/Expand Semua Grup Sidebar

## Problem Statement

Ticket 009 sudah membuat tiap grup sidebar (`section-label`) collapsible satu-satu, dengan state tersimpan di `localStorage` key `sidebar-collapsed-groups` (Blade: `resources/views/components/ui/organisms/sidebar.blade.php:12,47-80`; Vue: `resources/js/Components/ui/organisms/Sidebar.vue:18-36,83-98,119-133`). Namun dengan 9 grup menu, user yang ingin melihat sidebar dalam keadaan ringkas (atau sebaliknya, membuka semua grup untuk mencari sesuatu) harus klik tiap `section-label` satu per satu. Spec 009 secara eksplisit menandai "collapse/expand semua grup sekaligus (tombol collapse all)" sebagai Out of Scope (`docs/backlog_items/009-sidebar-grup-collapsible/spec.md:55`).

Catatan: mode "collapse seluruh sidebar jadi strip ikon" (`sidebar-collapsed-full` / class `sidebar-collapsed` di `<body>`/root layout, tombol `[data-sidebar-toggle]` di `.brand`) adalah fitur **berbeda** yang sudah ada dan sudah dipakai sejak ticket 009 — itu mempersempit lebar sidebar, bukan mem-expand/collapse isi grup. Fitur ini tidak disentuh oleh ticket ini.

## Solution

Tambahkan satu tombol toggle "Collapse/Expand semua grup" di sidebar yang membuka atau menutup semua grup (`section-label`) sekaligus, reuse mekanisme collapse per grup yang sudah ada di ticket 009 — bukan state/mekanisme baru dari nol.

1. **Penempatan tombol:** di dalam `.brand` sidebar, bersebelahan dengan tombol `[data-sidebar-toggle]` (collapse seluruh sidebar jadi icon-only) yang sudah ada — tombol baru ini punya atribut berbeda, misal `data-toggle-all-groups`, ikon chevron ganda (`fa-angles-up`/`fa-angles-down` atau sejenis) yang berubah sesuai state saat ini.
2. **Behavior klik:**
   - Jika *ada* grup yang sedang collapsed (minimal satu dari grup berlabel), klik tombol ini akan **expand semua grup** (hapus semua label dari `sidebar-collapsed-groups`).
   - Jika *semua* grup berlabel sedang terbuka (tidak ada yang collapsed), klik tombol ini akan **collapse semua grup** (isi `sidebar-collapsed-groups` dengan semua label grup yang punya `section.label`).
   - Pola on/off ini konsisten dengan pola "collapse all/expand all" standar admin panel: tombol selalu menunjukkan aksi yang akan terjadi berikutnya, bukan state statis.
3. **Pengecualian grup aktif:** grup yang memuat menu aktif (`currentSlug`) tetap dipaksa terbuka secara tampilan (override tampilan, bukan override state tersimpan) persis seperti override yang sudah ada untuk collapse per grup satu-satu (`isGroupOpen`/`groupContainsCurrentSlug` di Vue `Sidebar.vue:76-87`; cek `hasActiveMenu` di Blade `sidebar.blade.php:52-54`). Artinya setelah klik "collapse semua", grup yang berisi menu aktif tetap kelihatan terbuka walau labelnya ikut masuk ke `sidebar-collapsed-groups`.
4. **State tombol (ikon/label aria):** dihitung dari apakah ada grup collapsed saat ini (bandingkan jumlah label di `sidebar-collapsed-groups` yang juga ada di `config('marketama.menu')` saat ini terhadap total grup berlabel) — tidak perlu localStorage key baru, cukup derive dari `sidebar-collapsed-groups` yang sudah ada.
5. **Blade (`sidebar.blade.php`):** tambah tombol di `.brand`, dan di script IIFE yang sudah ada (baris 23-82), tambah handler untuk tombol baru yang membaca semua `data-group-label` dari `.section-label[data-collapse-toggle]`, lalu set/hapus `hidden`+`is-open` pada tiap target & toggle sekaligus, dan tulis ulang `sidebar-collapsed-groups` sesuai arah aksi (expand all → `[]` minus grup yang memang tidak termasuk; collapse all → semua label).
6. **Vue (`Sidebar.vue`):** tambah computed `allGroupsCollapsed` (true jika setiap grup berlabel ada di `collapsedGroups`), dan fungsi `toggleAllGroups()` yang men-set `collapsedGroups.value` jadi array semua label (collapse all) atau `[]` (expand all) lalu `writeCollapsedGroups(...)`, dipanggil dari tombol baru di template.
7. **Tidak mengubah** mekanisme collapse per grup satu-satu, collapse seluruh sidebar (icon-only), maupun collapse submenu (`isChildOpen`/`toggleSubmenu`) — fitur ini murni operasi bulk di atas state `sidebar-collapsed-groups` yang sudah ada.

## User Stories

1. Sebagai pengguna admin yang ingin cepat melihat gambaran semua 9 grup menu tanpa harus klik satu-satu, saya ingin satu tombol yang langsung membuka semua grup sekaligus.
2. Sebagai pengguna admin yang sudah selesai memilih menu dan ingin sidebar jadi ringkas lagi, saya ingin satu tombol yang langsung menutup semua grup (kecuali yang memuat menu aktif) tanpa harus klik tiap grup satu-satu.
3. Sebagai pengguna admin yang baru klik "collapse semua grup", saya tetap ingin bisa melihat grup tempat menu yang sedang saya buka berada, supaya tidak kehilangan orientasi di sidebar.
4. Sebagai pengguna admin yang klik tombol ini berulang kali, saya ingin tombolnya langsung mencerminkan aksi berikutnya (expand vs collapse) berdasarkan state grup saat ini, bukan state yang membingungkan.

## Implementation Decisions

- Tombol baru ini terpisah dari tombol `[data-sidebar-toggle]` (collapse seluruh sidebar jadi icon-only) — dua tombol berdampingan di `.brand`, masing-masing mengontrol hal berbeda (lebar sidebar vs isi grup per grup).
- Grup pertama tanpa label (`section.label === null`, selalu terbuka, tidak punya toggle — lihat ticket 009) tidak ikut dihitung dan tidak terpengaruh oleh tombol ini, karena memang tidak punya mekanisme collapse.
- "Semua grup collapsed" dihitung dari grup berlabel yang benar-benar ada di menu saat ini (hasil `Akses::menu($user)` / `page.props.menu`), bukan dari seluruh definisi `config('marketama.menu')` — supaya kalau suatu role tidak punya akses ke salah satu grup, status tombol tidak salah hitung.
- Tombol ini hanya menulis ulang key `localStorage` yang sudah ada (`sidebar-collapsed-groups`); tidak menambah key baru, supaya tidak ada state baru yang perlu disinkronkan antar stack Blade/Vue.
- Scroll ke posisi tombol seperti pada toggle per grup (`toggle.scrollIntoView` / `event.currentTarget.scrollIntoView` di implementasi 009) **tidak** diterapkan untuk aksi bulk ini — karena mengubah banyak grup sekaligus, tidak ada satu posisi scroll yang relevan; sidebar tetap di posisi scroll saat ini.
- Tombol disembunyikan di breakpoint mobile (≤991px) bersama tombol `[data-sidebar-toggle]` yang sudah disembunyikan di breakpoint itu (konsisten dengan keputusan 009) — karena di mobile, sidebar statis full-width dan ruang `.brand` terbatas.

## Testing Decisions

- Test Feature Laravel yang mem-assert markup sidebar Blade memuat tombol toggle baru (mis. elemen dengan `data-toggle-all-groups`) di dalam `.brand`, bersebelahan dengan `[data-sidebar-toggle]` yang sudah ada.
- Verifikasi manual (didokumentasikan di PR, konsisten dengan keputusan testing 009 untuk Vue tanpa test runner JS): klik tombol saat semua grup terbuka → semua grup collapsed kecuali yang memuat menu aktif; klik lagi → semua grup expand; reload halaman → state tetap konsisten dengan `localStorage`; verifikasi berlaku di kedua stack (Blade & Vue/Inertia).

## Out of Scope

- Mengubah mekanisme collapse seluruh sidebar jadi icon-only (`sidebar-collapsed-full`) — tombol itu sudah ada dan tidak disentuh.
- Menambah tingkat granularitas baru (misal "collapse semua submenu" terlepas dari grup) — ticket ini hanya level grup (`section-label`), submenu (`item.children`) tidak terpengaruh.
- Animasi/transisi saat collapse/expand massal — mengikuti keputusan "tanpa animasi" yang sudah ada di ticket 009.
- Sinkronisasi preferensi ini ke server/antar device — tetap `localStorage` per browser, sama seperti ticket 009.
