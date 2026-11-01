# Pendaftar outlet baru di daftar kunjungan dan popover

## Parent

Spec: [docs/backlog_items/055-pendaftar-outlet-baru-di-drawer-riwayat/spec.md](../spec.md)

## What to build

`KunjunganHarian` mengirim `pendaftar_outlet_baru` (nama sales dari `trx_outlet_baru.karyawan_id`), dan drawer menampilkannya di daftar kiri dan popover penanda untuk kunjungan ke outlet baru yang belum disetujui.

## Acceptance criteria

- [ ] `pendaftar_outlet_baru` = nama sales (`mk_karyawan.nama`) untuk kunjungan ke outlet baru (`outlet_baru_id` terisi); `null` untuk kunjungan ke outlet master.
- [ ] `null` juga bila `trx_outlet_baru.karyawan_id` tidak cocok dengan `mk_karyawan` mana pun.
- [ ] Daftar kiri menampilkan "Didaftarkan oleh: {nama}" di bawah nama outlet bila ada.
- [ ] Popover menampilkan baris yang sama (di-escape) bila ada; tidak ada baris kosong bila `null`.

## Implementation steps

- [ ] `KunjunganHarian`: `leftJoin` `trx_outlet_baru` ke `mk_karyawan`, pilih `nama`, kirim `pendaftar_outlet_baru`.
- [ ] `RuteHarianPeta.vue` dan `Support/rute.js`.
- [ ] Feature test dan Vitest.

## Blocked by

None
