# Indikasi Jarak Dekat Antar Kunjungan

## Problem Statement
Drawer Riwayat Lacak Motoris sudah mendeteksi titik GPS yang tidak wajar (spec 050) dan menampilkan jarak/durasi perjalanan antar kunjungan outlet (spec 045), tapi tidak ada yang menilai pola **jarak antar kunjungan itu sendiri**. Pengguna menemukan motoris Ade Suherman mengunjungi 4 outlet berturutan dengan jarak 0 meter — mencurigakan karena outlet yang berbeda seharusnya punya koordinat berbeda, kecuali memang berdekatan (pasar, mal, ruko sederet).

**Kalibrasi dari data saat ini (264.769 kunjungan, seluruhnya `sumber = 'lama'` hasil impor Marketama, belum ada data HP langsung/`mock_location`)**: dari 258.499 pasang kunjungan berurutan, **8,3%** berjarak < 5 meter dari kunjungan sebelumnya. Pola berturutan (≥ 1 menit antar checkin, jarak < 5 m) jauh lebih umum daripada lompatan GPS: 601 dari 6.265 kombinasi motoris-tanggal (9,6%) punya ≥ 3 kunjungan berturutan seperti itu, 285 (4,5%) punya ≥ 4, dan beberapa kasus ekstrem punya 20–27 kunjungan berturutan pada koordinat yang nyaris identik dalam satu jam (mis. karyawan 718, 7 Okt 2026). Karena **semua data saat ini hasil impor lama** (tanpa `mock_location`, tanpa `device_id`), pola ini bisa jadi sebagian berasal dari cara data lama dicatat (koordinat dibulatkan/digabung per area), bukan murni kecurangan — sehingga ambang deteksi **harus bisa diubah pengguna**, bukan ditanam tetap di kode, supaya bisa disesuaikan begitu data HP langsung (dengan `mock_location` akurat) lebih dominan.

## Solution
Drawer Riwayat (Sales) menganalisis **jarak antar kunjungan outlet berurutan pada tanggal yang sedang dilihat** (menggunakan `perjalanan.jarak_m` yang sudah dihitung oleh `KunjunganHarian`, spec 045) dan menampilkan peringatan bila jumlah kunjungan berturutan yang jaraknya sangat dekat melewati ambang:

- **Kunjungan berturutan berjarak dekat**: `perjalanan.jarak_m` dua kunjungan berurutan di bawah ambang jarak (default dapat diubah, lihat di bawah).
- **Indikasi**: jumlah kunjungan berturutan seperti itu mencapai ambang jumlah minimum. Satu hari bisa punya **lebih dari satu runtun terpisah** (mis. klaster outlet di pagi hari dan klaster lain di siang hari) — semuanya dilaporkan, bukan hanya yang terpanjang, supaya runtun yang lebih pendek tidak tertutup begitu saja oleh runtun lain yang kebetulan lebih panjang pada hari yang sama. (Catatan: versi awal fitur ini hanya melaporkan runtun terpanjang per hari — diperbaiki setelah ditemukan dari kasus Ade Suherman 22 Okt 2026, di mana runtun 4-outlet pertamanya tertutup oleh runtun 6-outlet yang lebih panjang di hari yang sama.)
- Banner (sama gaya dengan indikasi GPS, warna kuning — tidak ada sinyal "terdeteksi" seperti `mock_location` untuk jarak, karena jarak antar kunjungan bukan properti perangkat) berisi ringkasan ("4 kunjungan berturutan berjarak < 5 m, dari jam 07:53 sampai 07:58") dan catatan bahwa ini indikasi dari pola data, bukan bukti.
- Kunjungan yang termasuk dalam runtun itu ditandai di daftar kunjungan drawer (mis. lencana kecil), supaya terlihat yang mana saja.
- Tombol **Tandai outlet** (spec 053) pada outlet-outlet dalam runtun itu terisi otomatis dengan kategori "Pola kunjungan tidak wajar" dan catatan berisi ringkasan, bisa diubah sebelum disimpan — pola yang sama dengan `saran` pada tanda motoris dari indikasi GPS.

### Ambang bisa diubah dari halaman konfigurasi
Dua ambang disimpan di database (bukan hanya `config/marketama.php`) dan bisa diubah lewat halaman admin baru, karena pengguna eksplisit meminta ini dapat dikonfigurasi, bukan ditanam tetap:
- **Jarak dekat (meter)** — default **5**.
- **Minimum kunjungan berturutan** — default **4** (mengikuti kasus Ade Suherman yang jadi pemicu fitur ini; dari kalibrasi, ini menandai ~4,5% motoris-hari pada data saat ini — admin bisa menaikkannya bila dirasa terlalu banyak false positive, terutama selama data masih dominan `sumber = 'lama'`).

Halaman baru **Pengaturan Deteksi Kunjungan** di bawah grup menu **Master Data & Pengaturan**, berisi form dua angka ini saja (bukan kerangka "pengaturan aplikasi" umum — lihat Out of Scope). Perubahan tercatat di riwayat admin.

## User Stories
1. Sebagai koordinator, saya ingin diberi tahu bila seorang motoris mengunjungi banyak outlet berturutan di lokasi yang nyaris sama, supaya tidak perlu membandingkan koordinat satu per satu.
2. Sebagai koordinator, saya ingin langsung menandai outlet-outlet yang terlibat lewat tombol Tandai outlet yang sudah terisi.
3. Sebagai admin, saya ingin mengubah ambang (jarak dan jumlah minimum) dari halaman pengaturan tanpa mengubah kode, karena pola di data saat ini (hasil impor lama) mungkin perlu ambang berbeda dari data HP langsung nanti.
4. Sebagai admin, saya ingin tahu siapa mengubah ambang dan kapan.
5. Sebagai motoris yang memang bekerja di pasar/mal padat outlet, saya tidak ingin satu-dua kunjungan berdekatan wajar memicu peringatan — ambang minimum mengurangi ini, dan admin bisa menaikkannya lebih lanjut bila perlu.

## Implementation Decisions
- **`pengaturan_admin` (tabel kunci → nilai yang sudah ada, pola yang sama dengan target Rekap Pencapaian SKU)**: dua kunci baru `deteksi_kunjungan.jarak_dekat_meter` dan `deteksi_kunjungan.min_kunjungan_berturutan`. Tidak ada tabel baru — menghindari membuat tabel single-row tersendiri padahal mekanisme kunci-nilai untuk pengaturan admin sederhana sudah ada.
- **Kelas baru `App\Support\Tracking\PengaturanDeteksiKunjungan`**: `ambil(): array` (baca kedua kunci, fallback ke `config('marketama.deteksi_jarak_kunjungan')` bila belum pernah disimpan) dan `simpan(int $jarak, int $min, ?User $user): void` (`updateOrInsert` per kunci).
- **Kelas baru `App\Support\Tracking\AnomaliJarakKunjungan::analisis(Collection $kunjungan): array`**: menerima kunjungan terurut jam (hasil `KunjunganHarian::untuk`, yang sudah punya `perjalanan.jarak_m`), membaca ambang dari `PengaturanDeteksiKunjungan::ambil()`, memecah kunjungan jadi runtun-runtun berurutan dengan `jarak_m < jarak_dekat_meter` (kunjungan tanpa koordinat atau tanpa `perjalanan` memutus runtun), dan mengembalikan **daftar semua runtun** yang `jumlah >= min_kunjungan_berturutan`, urut dari yang terpanjang (array kosong bila tidak ada) — tiap item `{ jumlah, kunjungan_id: [...], outlet_id: [...], dari_jam, sampai_jam, saran: { kategori, catatan } }`.
- **`TrackingSalesController@locations`** menambah `indikasi_jarak_kunjungan` (daftar di atas, bisa kosong) ke respons, dihitung dari `kunjungan` yang sudah ada di respons itu. Hanya endpoint Sales.
- **`PengaturanDeteksiKunjunganController`**: `index` (tampilkan form, hak menu baru atau `outlet_all`/`tracking_sales` — lihat Asumsi), `update` (validasi integer positif, simpan, catat riwayat admin).
- **Vue**: `RuteHarianPeta.vue` menampilkan **satu banner per runtun** (mirip `peringatan` dari `AnomaliGps`, variant warning — jadi bisa lebih dari satu banner dalam satu drawer bila harinya punya beberapa runtun), memberi lencana pada baris kunjungan yang `id`-nya termasuk di runtun mana pun, dan meneruskan `saran` runtun yang bersangkutan ke `TandaOutlet.vue` (spec 053) saat outlet dalam runtun itu ditandai. Halaman baru `Pages/Pengaturan/DeteksiKunjungan.vue` (form dua angka, submit, flash sukses).
- **Config fallback** `marketama.deteksi_jarak_kunjungan` (`jarak_dekat_meter` 5, `min_kunjungan_berturutan` 4) dipakai bila kunci `deteksi_kunjungan.*` belum pernah disimpan di `pengaturan_admin` (instalasi baru).

## Asumsi desain (bisa diubah)
1. Hak mengubah ambang memakai menu tersendiri `deteksi_kunjungan` (bukan `outlet_all`) — halaman ini perlu entri sidebar sendiri di bawah Master Data & Pengaturan > Konfigurasi, jadi memakai hak lihat/ubah normal pada menunya sendiri, konsisten dengan menu "Pengaturan" lain (`target_setting`, `holiday_dates`, `waktu_aktif_app`). Semua role yang biasanya dapat hak "lihat" (koordinator, lainnya) otomatis bisa melihat nilai aktif; hanya admin (atau role dengan hak Ubah eksplisit) yang bisa mengubahnya.
2. Banner ini tidak punya tingkat "terdeteksi" (beda dari `AnomaliGps`) karena tidak ada sinyal perangkat untuk jarak antar kunjungan — selalu "indikasi".
3. Runtun dihitung dari kunjungan **berurutan dalam satu hari yang sama** (tidak lintas tanggal), konsisten dengan `AnomaliGps`.
4. Kunjungan tanpa koordinat (`lat`/`lng` null) dianggap memutus runtun (tidak dihitung dekat maupun jauh), karena tidak bisa dinilai.

## Testing Decisions
- Unit test `AnomaliJarakKunjunganTest`: runtun di bawah ambang tidak menghasilkan peringatan; tepat di ambang (mis. 4 dari `min_kunjungan_berturutan` default) menghasilkan peringatan, 3 tidak; runtun terputus oleh kunjungan jauh atau tanpa koordinat dihitung sebagai dua runtun terpisah (ambil yang terpanjang); ambang dibaca dari tabel pengaturan, bukan ditanam tetap; fallback ke config bila tabel kosong; `saran` berisi daftar outlet dan ringkasan jam.
- Feature test: `locations` Sales memuat `indikasi_jarak_kunjungan` untuk kasus kalibrasi (mis. replikasi karyawan 718 pada data uji, termasuk kasus dua runtun terpisah dalam satu hari), array kosong untuk pola normal; endpoint Koordinator tanpa kunci ini. `PengaturanDeteksiKunjunganTest`: index menampilkan nilai aktif; update menyimpan, validasi integer positif, tercatat di riwayat admin, ditolak tanpa hak.
- Vitest: banner tampil/tidak dan satu banner per runtun bila lebih dari satu, lencana pada baris kunjungan yang termasuk runtun mana pun, `saran` diteruskan ke `TandaOutlet` sesuai runtun outlet yang bersangkutan.

## Out of Scope
- Kerangka "pengaturan aplikasi" umum (key-value generik untuk semua config) — hanya tabel dan halaman khusus dua ambang ini.
- Analisis lintas tanggal, skor per motoris/outlet, atau laporan rekap.
- Mendeteksi dari akurasi GPS atau sumber lain selain `perjalanan.jarak_m` yang sudah ada.
- Notifikasi otomatis atau memblokir transaksi.
- Peringatan di halaman Riwayat Lokasi penuh, tabel Lacak Motoris, atau aplikasi HP.
- Menyesuaikan ulang ambang `anomali_gps` (spec 050) menjadi dapat dikonfigurasi — di luar cakupan permintaan ini, bisa jadi item backlog terpisah bila dibutuhkan.
