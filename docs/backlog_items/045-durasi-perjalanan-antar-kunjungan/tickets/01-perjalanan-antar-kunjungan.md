# Perjalanan antar kunjungan: hitung di backend, tampil di daftar drawer

## Parent

Spec: [docs/backlog_items/045-durasi-perjalanan-antar-kunjungan/spec.md](../spec.md)

## What to build

`perjalanan` (menit dan jarak garis lurus) pada tiap kunjungan di `KunjunganHarian`, dan baris penghubung antar kunjungan di daftar drawer Riwayat.

## Acceptance criteria

- [x] `perjalanan` = `{menit, jarak_m}` dihitung dari checkout kunjungan sebelumnya ke checkin berikutnya; `null` untuk kunjungan pertama, bila checkout sebelumnya kosong, atau bila checkin lebih awal dari checkout.
- [x] `jarak_m` null bila salah satu kunjungan tanpa koordinat.
- [x] Daftar drawer menampilkan "↓ 12 menit di jalan · 3,4 km" di antara dua kunjungan; tanpa jarak bila null; tersembunyi bila `perjalanan` null.
- [x] Format: kurang dari 1 menit, "N menit", "1 j 5 mnt"; "850 m", "3,4 km".

## Implementation steps

- [x] `KunjunganHarian`: hitung `perjalanan` + haversine privat.
- [x] `Support/format.js`: `durasiSingkat`, `jarakSingkat` + `format.spec.js`.
- [x] `RuteHarianPeta.vue`: baris `.rute-perjalanan`; kelas di `app.css`; perluas `RuteHarianPeta.spec.js`.
- [x] Feature test di `KunjunganDrawerRiwayatTest`.

## Blocked by

None
