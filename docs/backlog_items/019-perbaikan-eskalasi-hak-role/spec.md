# Perbaikan Eskalasi Hak Akses lewat Menu Role

## Problem Statement

Security review atas tiket `018-03` (menu Role & hak akses lewat Inertia) menemukan dua jalur eskalasi hak akses di `App\Http\Controllers\Admin\RoleController` yang sudah ada sebelum migrasi Inertia (logika query & validasi tidak diubah tiket itu) dan masih ada sekarang:

1. **`update()`/`store()` tidak membatasi hak yang boleh diberikan ke matriks hak akun yang mengedit.** Rute `PUT /role/{id}` dan `POST /role` hanya dijaga `hak:role,ubah` / `hak:role,tambah` (lihat `routes/web.php:124-131`). Isi `hak` dari request diteruskan ke `normalisasiHak()` lalu `simpanHak()` tanpa memeriksa apakah menu/aksi yang diminta ada dalam hak akun yang mengedit sendiri. Satu-satunya penjagaan (`RoleController.php:82-85`) hanya mencegah akun mencabut `role.ubah` dari role-nya sendiri.

   **Skenario eksploitasi:** Akun dengan role yang hanya punya hak `role.lihat` + `role.ubah` membuka form edit role miliknya sendiri, lalu mengirim `hak` berisi menu/aksi apa pun (`akun.*`, `user_sales.*`, hak menu lain) yang tidak pernah diberikan ke role tersebut. Validasi lolos karena `role.ubah` tetap dipertahankan, dan matriks baru langsung tersimpan serta berlaku di request berikutnya lewat `Akses::bisa()`.

2. **`pindahkan()` tidak membatasi role tujuan.** Validasi `ke` (`RoleController.php:156`) hanya memeriksa `Rule::exists('roles','id')` dan `Rule::notIn([$id])`. Penjagaan "tidak bisa memindahkan diri sendiri" (`RoleController.php:161-163`) tidak mencegah akun memindahkan **akun lain** ke role `super_admin` atau role berhak lebih luas dari role akun yang melakukan aksi.

   **Skenario eksploitasi:** Akun dengan hak `role.ubah` (bukan Super Admin) memindahkan akun yang ia kendalikan (dibuat sendiri bila punya hak `akun.tambah`, atau akun lain) ke role Super Admin lewat `POST /role/{id}/pindah`. Akun tujuan langsung mendapat akses penuh ke semua menu.

Kedua temuan ini adalah **privilege escalation** (kategori HIGH pada security review): akun non-Super-Admin bisa memberi dirinya sendiri atau akun lain hak yang seharusnya tidak pernah diberikan admin.

## Solution

Tambahkan pagar langit (ceiling check) di sisi server untuk kedua endpoint, berbasis hak efektif akun yang melakukan aksi — bukan hanya cek nama-hak tunggal seperti sekarang:

- **Matriks hak (`store`/`update`)**: akun yang bukan Super Admin hanya boleh menyimpan `hak[menu][aksi] = true` untuk kombinasi yang juga `true` pada hak efektif akun itu sendiri (`Akses::hakRole()`/`Akses::bisa()` milik aktor). Permintaan yang mencoba menaikkan hak di luar itu ditolak dengan error validasi per field (`hak`), bukan disenyapkan (silently dropped) — supaya pemakai sah yang salah klik tahu kenapa gagal.
- **Pindah anggota (`pindahkan`)**: tolak `ke` yang mengarah ke role dengan hak lebih luas daripada hak efektif aktor, kecuali aktor sendiri Super Admin (`Akses::penuh()`). "Lebih luas" didefinisikan sebagai: role tujuan punya `true` pada kombinasi menu/aksi yang aktor sendiri tidak punya, atau role tujuan adalah `super_admin` sementara aktor bukan.
- Super Admin tetap bisa melakukan kedua aksi tanpa batasan ini (perilaku sekarang untuk Super Admin tidak berubah).

## User Stories

1. Sebagai Super Admin, saya ingin akun non-Super-Admin dengan hak `role.ubah` tidak bisa memberi role (termasuk role miliknya sendiri) hak akses yang tidak ia miliki sendiri, supaya delegasi pengelolaan role tidak menjadi jalan eskalasi diam-diam.
2. Sebagai akun dengan hak `role.ubah` yang sah (misal admin depo yang hanya mengelola cakupan depo), saya ingin tetap bisa mengubah nama/keterangan/cakupan role dan hak yang memang sudah saya miliki, supaya pekerjaan normal tidak terganggu oleh pagar baru ini.
3. Sebagai Super Admin, saya ingin akun non-Super-Admin tidak bisa memindahkan akun apa pun (termasuk akun buatannya sendiri) ke role Super Admin atau ke role berhak lebih luas dari hak akun itu sendiri, supaya "pindah anggota" tidak dipakai untuk melompat ke akses penuh.
4. Sebagai pengguna yang mencoba menyimpan hak di luar kewenangan saya, saya ingin melihat pesan error yang jelas di field `hak` (bukan error 500/403 generik), supaya saya tahu aksi saya ditolak karena melebihi hak saya sendiri, bukan karena bug.

## Implementation Decisions

- **Sumber kebenaran hak aktor**: pakai `Akses::hakRole($roleIdAktor)` (role aktor sendiri, bukan role yang sedang diedit) sebagai batas atas (ceiling); bandingkan menu demi menu, aksi demi aksi dengan matriks hasil `normalisasiHak()` sebelum `simpanHak()` dipanggil.
- **Super Admin dikecualikan dari ceiling ini di kedua endpoint** — konsisten dengan kode yang sudah ada (`$role->kode === 'super_admin'` dilewati dari matriks di `update()`; cek pakai `Akses::penuh($request->user())` seperti yang sudah dipakai di `pindahkan()` baris 161).
- **Error dilempar sebagai `ValidationException` pada key `hak`** (matriks) dan `ke` (pindah), mengikuti pola yang sudah ada di `RoleController` (`ValidationException::withMessages([...])`), supaya tampil sebagai error per-field di halaman Vue `Role/Form` dan `Role/Anggota` tanpa perubahan di sisi Vue.
- **Tidak mengubah skema atau menambah tabel baru** — ini perbaikan logika otorisasi di controller yang sudah ada.
- **Tidak mengubah `normalisasiHak()`/`simpanHak()` secara struktural** — ceiling check ditambahkan sebagai langkah terpisah sebelum keduanya dipanggil, supaya mudah diuji sendiri.

## Testing Decisions

- Seam: HTTP routes lewat `tests/Feature/Admin/RoleTest.php`, pakai `actingAs()` dengan akun ber-role terbatas (bukan Super Admin) dan assert `assertSessionHasErrors('hak')` / `assertSessionHasErrors('ke')` serta `assertDatabaseMissing`/`assertDatabaseHas` untuk memastikan hak/role_id tidak berubah.
- Test baru:
  - Akun dengan hak terbatas mencoba `PUT /role/{id}` menambah hak menu yang tidak ia miliki → ditolak, hak lama tidak berubah.
  - Akun dengan hak terbatas mencoba `POST /role` (tambah role baru) dengan matriks melebihi haknya sendiri → ditolak.
  - Super Admin tetap bisa menyimpan matriks apa pun (regresi, perilaku tidak berubah).
  - Akun non-Super-Admin mencoba `POST /role/{id}/pindah` dengan `ke` = role `super_admin` → ditolak, `role_id` akun tidak berubah.
  - Akun non-Super-Admin mencoba memindahkan akun ke role lain yang hak-nya masih dalam batas hak aktor → tetap berhasil (regresi, perilaku normal tidak berubah).
  - Super Admin tetap bisa memindahkan akun ke role apa pun termasuk Super Admin (regresi).

## Out of Scope

- Audit menyeluruh endpoint lain yang memakai pola `hak:<menu>,<aksi>` di luar menu Role (di luar temuan security review ini).
- Perubahan model data `roles`/`role_hak` atau desain matriks hak.
- Pembatasan serupa untuk pemindahan role lewat form akun (`AkunController`), kecuali ternyata memakai jalur yang sama — perlu dicek saat implementasi, tapi bukan temuan review ini.

## Further Notes

- Temuan ini berasal dari security review pada branch `BI-018` (tiket `018-04-auth-ke-inertia`), bukan dari laporan pengguna.
- Logika yang bermasalah (`update()`, `store()`, `pindahkan()`) sudah ada sebelum migrasi Inertia — tiket `018-03` memindahkan rendering ke Vue tanpa mengubah logika query/validasi ini, jadi bug ini bukan regresi dari migrasi tersebut, hanya baru terlihat saat review kode migrasinya.
