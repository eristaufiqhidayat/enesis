# Tab penghubung Last Seen ↔ Riwayat Lokasi

## Parent

Spec: [docs/backlog_items/042-tab-tracking-last-seen-riwayat/spec.md](../spec.md)

## What to build

Baris tab "Last Seen" / "Riwayat Lokasi" di atas halaman `tracking_sales` ↔ `tracking_sales_history_location` dan `tracking_koordinator` ↔ `tracking_koordinator_history_location`, berupa tautan antar-URL yang disaring menurut hak akun.

## Acceptance criteria

- [x] `config/marketama.php` punya `tab_halaman` berisi dua kelompok (sales, koordinator) `slug => label`.
- [x] Shared prop `tabHalaman` (dari `Akses::tabHalaman`) berisi tab kelompok halaman aktif yang boleh dibuka akun, kosong bila kurang dari dua atau halaman tidak berkelompok.
- [x] Molecule `TabsHalaman.vue` merender `Tabs` dengan tab aktif = `currentSlug` dan pindah halaman lewat `router.visit`; dipakai di `Menu/Show.vue` dan `Tracking/History.vue`.
- [x] Role tanpa hak salah satu halaman pasangan tidak melihat baris tab.
- [x] Rute, slug, middleware hak, dan sidebar tidak berubah.

## Implementation steps

- [x] Config `tab_halaman`; `Akses::tabHalaman(?User, string $slug)`; shared prop `tabHalaman` di `HandleInertiaRequests`.
- [x] `resources/js/Components/ui/molecules/TabsHalaman.vue` + `TabsHalaman.spec.js`.
- [x] Pasang di `Menu/Show.vue` (di bawah PageHeader) dan `Tracking/History.vue`.
- [x] Feature test `tabHalaman` (super admin, role terbatas, halaman lain).

## Blocked by

None
