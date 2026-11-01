# Banner, lencana runtun, dan saran ke Tandai outlet

## Parent

Spec: [docs/backlog_items/054-indikasi-jarak-dekat-kunjungan/spec.md](../spec.md)

## What to build

Banner indikasi di `RuteHarianPeta.vue` (mirip pola `AnomaliGps`), lencana pada baris kunjungan yang termasuk runtun, dan `saran` yang terisi otomatis ke form Tandai outlet (spec 053) untuk outlet dalam runtun itu.

## Acceptance criteria

- [x] Banner (variant warning) tampil **satu per runtun** saat `indikasi_jarak_kunjungan` berisi item, masing-masing berisi ringkasan jumlah dan rentang jam, dan catatan "indikasi dari pola data, bukan bukti" — diperbaiki dari rencana awal (satu banner/objek) setelah `indikasi_jarak_kunjungan` berubah jadi daftar (ticket 02), supaya semua runtun dalam sehari terlihat, bukan hanya satu.
- [x] Tidak ada banner saat `indikasi_jarak_kunjungan` kosong.
- [x] Baris kunjungan dengan id termasuk `kunjungan_id` runtun MANA PUN menampilkan lencana/penanda runtun ("Jarak dekat").
- [x] Tombol Tandai outlet pada outlet dalam salah satu runtun terbuka dengan kategori "Pola kunjungan tidak wajar" dan catatan ringkasan runtun yang bersangkutan terisi otomatis (lewat `saran`, bisa diubah di form sebelum disimpan — perilaku ubah form sudah dites di `TandaOutlet.spec.js`); outlet di luar semua runtun membuka form kosong.

## Implementation steps

- [x] `RuteHarianPeta.vue`: render satu `<Alert>` per runtun (`v-for`), lencana baris berdasar keanggotaan di runtun mana pun (`.some()`), dan `bukaTandaOutlet(k)` mencari runtun yang memuat `k.outlet_id` untuk saran.
- [x] Vitest: banner tampil/tidak dan satu banner per runtun bila lebih dari satu, lencana pada baris yang tepat, `saran` diteruskan untuk outlet dalam runtun dan kosong untuk di luar runtun.

## Blocked by

- [02-analisis-jarak-dan-kunci-indikasi.md](02-analisis-jarak-dan-kunci-indikasi.md)
- Spec 053 tiket 03 (`TandaOutlet.vue` harus sudah ada untuk menerima `saran`)
