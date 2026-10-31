# Kode outlet di daftar kunjungan dan popover

## Parent

Spec: [docs/backlog_items/048-kode-outlet-di-drawer-riwayat/spec.md](../spec.md)

## What to build

`KunjunganHarian` mengirim `kode_outlet`, dan drawer menampilkannya di daftar kiri dan popover penanda.

## Acceptance criteria

- [x] `kode_outlet` = `mk_outlet.kode` untuk outlet master, `null` untuk outlet baru dari HP.
- [x] Daftar kiri menampilkan kode (font mono) di bawah nama outlet bila ada.
- [x] Popover menampilkan kode di bawah judul bernomor bila ada; di-escape.
- [x] Tanpa kode, tidak ada baris kosong.

## Implementation steps

- [x] `KunjunganHarian`: pilih `o.kode`, kirim `kode_outlet`.
- [x] `RuteHarianPeta.vue` dan `Support/rute.js`.
- [x] Feature test dan Vitest.

## Blocked by

None
