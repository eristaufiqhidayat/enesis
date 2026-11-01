# Banner, lencana runtun, dan saran ke Tandai outlet

## Parent

Spec: [docs/backlog_items/054-indikasi-jarak-dekat-kunjungan/spec.md](../spec.md)

## What to build

Banner indikasi di `RuteHarianPeta.vue` (mirip pola `AnomaliGps`), lencana pada baris kunjungan yang termasuk runtun, dan `saran` yang terisi otomatis ke form Tandai outlet (spec 053) untuk outlet dalam runtun itu.

## Acceptance criteria

- [x] Banner (variant warning) tampil saat `indikasi_jarak_kunjungan` ada, berisi ringkasan jumlah dan rentang jam, dan catatan "indikasi dari pola data, bukan bukti".
- [x] Tidak ada banner saat `indikasi_jarak_kunjungan` null.
- [x] Baris kunjungan dengan id dalam `kunjungan_id` hasil analisis menampilkan lencana/penanda runtun ("Jarak dekat").
- [x] Tombol Tandai outlet pada outlet dalam runtun terbuka dengan kategori "Pola kunjungan tidak wajar" dan catatan ringkasan terisi otomatis (lewat `saran`, bisa diubah di form sebelum disimpan — perilaku ubah form sudah dites di `TandaOutlet.spec.js`); outlet di luar runtun membuka form kosong.

## Implementation steps

- [x] `RuteHarianPeta.vue`: render banner, lencana baris, dan teruskan `saran` ke `TandaOutlet.vue` lewat `bukaTandaOutlet(k)`.
- [x] Vitest: banner tampil/tidak, lencana pada baris yang tepat, `saran` diteruskan untuk outlet dalam runtun dan kosong untuk di luar runtun.

## Blocked by

- [02-analisis-jarak-dan-kunci-indikasi.md](02-analisis-jarak-dan-kunci-indikasi.md)
- Spec 053 tiket 03 (`TandaOutlet.vue` harus sudah ada untuk menerima `saran`)
