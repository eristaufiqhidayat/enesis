# Pendaftar Outlet Baru di Drawer Riwayat Lacak Motoris

## Problem Statement
Daftar kunjungan outlet di drawer Riwayat hanya menampilkan nama outlet, tanpa membedakan kunjungan ke outlet master (`mk_outlet`) dan kunjungan yang **sekaligus pendaftaran outlet baru** dari HP (`trx_outlet_baru`, belum disetujui). Untuk kunjungan jenis kedua, admin/koordinator tidak tahu siapa yang mendaftarkan outlet itu tanpa membuka menu New Open Outlet secara terpisah dan mencari manual — padahal datanya (`trx_outlet_baru.karyawan_id`) sudah ada. Ini berguna terutama saat pendaftarnya **bukan** motoris yang sedang dilihat di drawer (outlet didaftarkan sales lain, lalu dikunjungi motoris ini kemudian).

## Solution
Untuk kunjungan ke outlet baru yang belum disetujui (`outlet_baru_id` terisi), daftar kunjungan drawer menampilkan nama sales yang mendaftarkannya, mis. **"Didaftarkan oleh: Budi Santoso"**, di bawah nama outlet. Tidak ditampilkan untuk kunjungan ke outlet master (`mk_outlet`), karena kolom pendaftar di sana (`mk_outlet.karyawan_id`) adalah data historis awal outlet, bukan konteks kunjungan ini — di luar cakupan (lihat Out of Scope).

## User Stories
1. Sebagai koordinator, saya ingin tahu siapa mendaftarkan outlet baru yang dikunjungi, langsung dari daftar kunjungan drawer, tanpa membuka menu lain.
2. Sebagai koordinator, saya ingin segera tahu bila pendaftarnya bukan motoris yang sedang saya lihat, karena itu bisa jadi petunjuk pola kunjungan yang perlu diperiksa.
3. Sebagai koordinator, saya tidak ingin baris "Didaftarkan oleh" muncul untuk outlet yang sudah di master, karena tidak relevan dengan kunjungan itu.

## Implementation Decisions
- **`KunjunganHarian::untuk`** menambah `leftJoin` dari `trx_outlet_baru` (alias `ob`, sudah ada) ke `mk_karyawan` (alias `obk`) lewat `obk.id = ob.karyawan_id`, pilih `obk.nama as pendaftar_outlet_baru`, kirim sebagai `pendaftar_outlet_baru` (string atau `null`) — null baik untuk kunjungan ke outlet master maupun bila sales pendaftarnya sudah tidak ada di `mk_karyawan`.
- **Daftar kiri** (`RuteHarianPeta.vue`): baris `<span class="block text-xs text-gray-500">Didaftarkan oleh: {{ k.pendaftar_outlet_baru }}</span>` di bawah nama outlet, hanya tampil bila `pendaftar_outlet_baru` ada.
- **Popover** (`Support/rute.js`, `popupKunjungan`): baris serupa (di-escape) ditambahkan ke `baris[]`, hanya bila ada.
- Tidak ada perubahan skema, hak akses, atau pencarian.

## Testing Decisions
- Feature test (nama file mengikuti pola `KunjunganDrawerRiwayatTest` yang sudah ada, atau ditambahkan di sana): `pendaftar_outlet_baru` terisi nama sales untuk kunjungan ke outlet baru, `null` untuk kunjungan ke outlet master, dan `null` bila `trx_outlet_baru.karyawan_id` tidak match `mk_karyawan` mana pun.
- Vitest: daftar kiri menampilkan baris "Didaftarkan oleh" hanya bila `pendaftar_outlet_baru` ada; popover memuat baris yang sama, di-escape, dan tidak ada baris kosong bila `null`.

## Out of Scope
- Menampilkan pendaftar untuk outlet yang sudah di master (`mk_outlet.karyawan_id`) — data historis awal outlet, bukan konteks kunjungan ini; bisa jadi item backlog terpisah bila dibutuhkan.
- Tautan ke profil/riwayat sales pendaftar, atau filter/pencarian berdasarkan pendaftar.
- Menandai atau memberi peringatan otomatis ketika pendaftar berbeda dari motoris yang sedang dilihat (murni informasi, bukan deteksi kecurangan).
