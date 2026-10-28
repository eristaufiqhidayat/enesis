# Diagram relasi tabel (ERD)

Dibuat dari skema hasil semua migrasi (`php artisan migrate`, 150 tabel) per 28 Okt 2026. Database **tidak memakai foreign key formal**; relasi di bawah adalah relasi lewat kolom `*_id` (dan beberapa lewat nama atau kode) yang dipakai kode aplikasi.

Versi satu file (semua diagram digabung jadi satu ERD, 55 tabel / 80 relasi): [`erwin.mmd`](erwin.mmd) — bisa dibuka di [mermaid.live](https://mermaid.live) atau editor yang mendukung Mermaid.

**Cara membaca**
- Garis **tegas** = relasi lewat kolom ID (`depo_id` → `mk_depo.id`).
- Garis **putus-putus** = relasi lewat nama, kode, atau penyalinan (mis. wilayah dihubungkan lewat nama induk; `area_polygon` disalin ke `mk_area` oleh `AreaMk`).
- `||` tepat satu · `|o` nol atau satu · `|{` satu atau lebih · `o{` nol atau lebih.
- Hanya kolom kunci dan kolom penting yang ditampilkan.

Tabel yang **tidak** digambar: tabel salinan menu lama Marketama tanpa relasi (`karyawan_*`, `user_*`, `absensi_*`, `rekap_*`, `motoris_*`, dll.), data referensi datar (kategori, rumus insentif, target, hari kerja, libur), dan tabel sistem Laravel (`cache`, `jobs`, `migrations`, …).

## 1. Master & organisasi

Hasil tarikan Marketama (`mk_*`) yang dipakai aplikasi HP dan laporan motoris, ditambah isian Enesis (kuota motoris, hierarki sales, area polygon).

```mermaid
erDiagram
    mk_depo ||--o{ mk_wilayah_depo : "punya"
    mk_depo ||--o{ mk_area : "punya"
    mk_depo ||--o{ mk_distributor : "punya"
    mk_depo ||--o{ mk_karyawan : "menaungi"
    mk_depo ||--o{ master_kuota_motoris : "kuota per bulan"
    mk_karyawan |o--o{ mk_karyawan : "koordinator_id (atasan)"
    mk_karyawan ||--o{ mk_karyawan_area : "rute sales"
    mk_area ||--o{ mk_karyawan_area : "dikunjungi"
    mk_area |o..o{ mk_karyawan : "area_aktif_id"
    mk_area ||--o{ mk_outlet : "berisi"
    mk_karyawan ||--o| hierarki_sales : "atasan versi Enesis"
    mk_karyawan |o..o{ mk_user : "akun Marketama"
    area_polygon ||..|| mk_area : "disalin AreaMk"
    mk_depo |o..o{ area_polygon : "warehouse_id"
    mk_depo |o..o| area_depo : "warehouse_id"
    kategori_produk |o..o{ mk_produk : "kategori_id"
    merk_produk |o..o{ mk_produk : "merk_id"
    satuan_produk |o..o{ mk_produk : "satuan_id"

    mk_depo {
        int id PK "warehouse_id Marketama"
        string kode
        string nama
        bool aktif
        json data "kota_besar, kuota, target, koordinat"
    }
    mk_wilayah_depo {
        int id PK
        string nama
        int depo_id FK
    }
    mk_area {
        int id PK "source_id atau 1e9 + area_polygon.id"
        string kode
        string nama
        int depo_id FK
    }
    mk_distributor {
        int id PK
        string nama
        int depo_id FK
    }
    mk_karyawan {
        int id PK "employee id Marketama"
        string kategori "sales, koordinator, supervisor, lainnya"
        string nama
        int depo_id FK
        int koordinator_id FK
        int area_aktif_id FK
        bool aktif
    }
    mk_karyawan_area {
        int karyawan_id PK,FK
        int area_id PK,FK
        bool aktif
    }
    hierarki_sales {
        int karyawan_id PK,FK
        int koordinator_id FK
        int diubah_oleh FK "users.id"
    }
    mk_user {
        string jenis PK
        int id PK
        string username
        int karyawan_id FK
    }
    mk_outlet {
        int id PK "Marketama, atau >= 100.000.000 dari NOO"
        string kode
        string nama
        int area_id FK
        string kelurahan "nama wilayah (teks)"
        decimal latitude
        decimal longitude
    }
    mk_produk {
        int id PK
        string kode
        string nama
        int merk_id FK
        int kategori_id FK
        int satuan_id FK
        decimal harga_jual
    }
    master_kuota_motoris {
        int id PK
        int depo_id FK
        string berlaku_mulai "Y-m"
        int kuota
    }
    area_polygon {
        int id PK
        int source_id "id area Marketama"
        string code UK
        string name
        int warehouse_id FK
        string kode_kelurahan FK
        text geometri "MultiPolygon"
    }
    area_depo {
        int id PK
        int warehouse_id UK
        string name
    }
    kategori_produk {
        int id PK
        string nama
    }
    merk_produk {
        int id PK
        string nama
    }
    satuan_produk {
        int id PK
        string nama
    }
```

## 2. Transaksi aplikasi motoris

Alur harian sales: absen → checkin depo → terima canvassing → kunjungan (stok, order) → kembalikan sisa. Koordinator: join visit. Semua tabel `trx_*` juga memuat riwayat aplikasi lama hasil `enesis:migrasi-transaksi` (kolom `sumber` = `motoris` / `lama`, `sumber_id` = `source_id` lama).

```mermaid
erDiagram
    mk_karyawan ||--o{ trx_absensi : "absen harian"
    mk_karyawan ||--o{ trx_checkin_depo : "checkin depo"
    mk_karyawan ||--o{ trx_canvassing : "membawa muatan"
    trx_canvassing ||--|{ trx_canvassing_item : "berisi"
    mk_produk ||--o{ trx_canvassing_item : "produk"
    mk_karyawan ||--o{ trx_kunjungan : "kunjungan outlet"
    mk_outlet |o--o{ trx_kunjungan : "outlet_id"
    trx_outlet_baru |o..o{ trx_kunjungan : "outlet_baru_id"
    trx_kunjungan ||--o{ trx_stok_outlet : "catat stok"
    mk_produk |o--o{ trx_stok_outlet : "null = kompetitor"
    trx_kunjungan ||--o{ trx_penjualan : "order"
    trx_canvassing |o--o{ trx_penjualan : "barang dari"
    mk_outlet |o--o{ trx_penjualan : "outlet_id"
    trx_penjualan ||--|{ trx_penjualan_item : "berisi"
    mk_produk ||--o{ trx_penjualan_item : "produk"
    mk_karyawan ||--o{ trx_outlet_baru : "daftarkan NOO"
    trx_outlet_baru |o..o| mk_outlet : "disetujui jadi"
    mk_area |o--o{ trx_outlet_baru : "area_id"
    mk_karyawan ||--o{ trx_join_visit : "koordinator_id"
    mk_karyawan ||--o{ trx_join_visit : "sales_id"
    mk_outlet |o--o{ trx_join_visit : "outlet_id"
    mk_karyawan ||--o{ trx_tracking : "titik GPS"
    mk_depo |o--o{ trx_absensi : "depo_id"
    mk_depo |o--o{ trx_canvassing : "depo_id"
    mobile_uploads |o..o{ trx_kunjungan : "foto_id"

    mk_karyawan {
        int id PK
        string kategori
        int depo_id FK
    }
    mk_depo {
        int id PK
        string nama
    }
    mk_outlet {
        int id PK
        string kode
        int area_id FK
    }
    mk_area {
        int id PK
        int depo_id FK
    }
    mk_produk {
        int id PK
        string nama
        decimal harga_jual
    }
    mobile_uploads {
        uuid id PK
        int user_id FK
        string jenis "selfie, kunjungan, toko, rak"
    }
    trx_absensi {
        int id PK
        int karyawan_id FK "UK bersama tanggal"
        int depo_id FK
        date tanggal
        datetime masuk_at
        datetime pulang_at
        bool terlambat
        string sumber "motoris atau lama"
    }
    trx_checkin_depo {
        int id PK
        int karyawan_id FK
        int depo_id FK
        date tanggal
        datetime checkin_at
        datetime checkout_at
    }
    trx_canvassing {
        int id PK
        string nomor UK
        int karyawan_id FK
        int depo_id FK
        date tanggal
        string status "disiapkan, diterima, dikembalikan"
    }
    trx_canvassing_item {
        int id PK
        int canvassing_id FK
        int produk_id FK
        decimal qty_bawa
        decimal qty_diterima
        decimal qty_kembali
    }
    trx_kunjungan {
        int id PK
        int karyawan_id FK
        int depo_id FK
        int outlet_id FK
        int outlet_baru_id FK
        date tanggal
        datetime checkin_at
        datetime checkout_at
        string tidak_beli_kategori
        uuid foto_id FK
    }
    trx_stok_outlet {
        int id PK
        int kunjungan_id FK
        int produk_id FK
        string produk_kompetitor
        decimal qty
    }
    trx_penjualan {
        int id PK
        string kode UK
        int kunjungan_id FK
        int karyawan_id FK
        int depo_id FK
        int outlet_id FK
        int canvassing_id FK
        date tanggal
        int total
        int poin
        bool is_effective_call
        datetime dibatalkan_at
    }
    trx_penjualan_item {
        int id PK
        int penjualan_id FK
        int produk_id FK
        decimal qty
        int harga
        int total
    }
    trx_outlet_baru {
        int id PK
        int karyawan_id FK
        int depo_id FK
        int area_id FK
        string nama
        string status "menunggu, disetujui, ditolak"
        int outlet_id FK
        int diverifikasi_oleh FK "users.id"
    }
    trx_join_visit {
        int id PK
        int koordinator_id FK
        int sales_id FK
        int depo_id FK
        int outlet_id FK
        int area_id FK
        date tanggal
        uuid foto_id FK
    }
    trx_tracking {
        int id PK
        int karyawan_id FK "UK bersama recorded_at"
        datetime recorded_at
        decimal lat
        decimal lng
        int baterai
    }
```

## 3. Akun, role & aplikasi HP

```mermaid
erDiagram
    roles ||--o{ role_hak : "hak per menu"
    roles ||--o{ users : "role_id"
    mk_karyawan |o--o| users : "karyawan_id"
    mk_user |o..o| users : "sumber_jenis + sumber_id"
    users ||--o{ mobile_tokens : "sesi HP"
    users ||--o{ mobile_uploads : "foto unggahan"
    users ||--o{ mobile_idempotency : "kunci kirim ulang"
    users ||--o{ sessions : "sesi web"
    users |o--o{ riwayat_admin : "aktor_id"
    users |o..o{ backup_data : "dibuat_oleh"
    users |o..o{ pengaturan_admin : "diubah_oleh"
    users |o..o{ master_kuota_motoris : "diubah_oleh"

    roles {
        int id PK
        string kode UK
        string nama UK
        string cakupan "semua, depo, downline, diri"
        bool login_web
        bool login_mobile
    }
    role_hak {
        int id PK
        int role_id FK "UK bersama menu"
        string menu "slug menu"
        bool lihat
        bool tambah
        bool ubah
        bool hapus
        bool ekspor
    }
    users {
        int id PK
        string username UK
        string kategori
        int karyawan_id FK
        int role_id FK
        bool aktif
        bool wajib_ganti_password
        string sumber_jenis
        int sumber_id
    }
    mk_karyawan {
        int id PK
        string nama
    }
    mk_user {
        string jenis PK
        int id PK
        int karyawan_id FK
    }
    mobile_tokens {
        int id PK
        int user_id FK
        string token_hash UK
        string device_id
        datetime last_used_at
        datetime revoked_at
    }
    mobile_uploads {
        uuid id PK
        int user_id FK
        string jenis
        string path
    }
    mobile_idempotency {
        int id PK
        int user_id FK "UK bersama kunci"
        string kunci
        string endpoint
    }
    sessions {
        string id PK
        int user_id FK
    }
    riwayat_admin {
        int id PK
        int aktor_id FK
        string objek "user, role, kuota_motoris, backup, ..."
        int objek_id
        string aksi
    }
    backup_data {
        int id PK
        string format "csv atau sql"
        string status
        string nama_file
        int dibuat_oleh FK
    }
    pengaturan_admin {
        string kunci PK "target_nasional.call, ..."
        string nilai
        int diubah_oleh FK
    }
    master_kuota_motoris {
        int id PK
        int depo_id FK
        int diubah_oleh FK
    }
```

## 4. Wilayah & area

Master wilayah berjenjang lewat **nama** induk (`kode` = kode Kemendagri tanpa titik). Batas resmi di `batas_wilayah` dicocokkan lewat `kode`; area hasil Generate Polygon Kelurahan menyimpan `kode_kelurahan`.

```mermaid
erDiagram
    provinsi ||..o{ kota_kabupaten : "nama provinsi"
    kota_kabupaten ||..o{ kecamatan : "nama kota_kabupaten"
    kecamatan ||..o{ kelurahan : "nama kecamatan"
    kelurahan ||..o| batas_wilayah : "kode Kemendagri"
    kecamatan ||..o| batas_wilayah : "kode"
    kota_kabupaten ||..o| batas_wilayah : "kode"
    kelurahan ||..o{ area_polygon : "kode_kelurahan"
    area_polygon ||..|| mk_area : "disalin AreaMk"
    mk_area ||--o{ mk_outlet : "area_id"
    kelurahan ||..o{ mk_outlet : "nama wilayah (teks)"

    provinsi {
        int id PK
        int kode "Kemendagri"
        string nama_provinsi
        string kode_bps
        string sumber
    }
    kota_kabupaten {
        int id PK
        int kode
        string provinsi "nama induk"
        string nama_kota_kabupaten
        string kode_bps
    }
    kecamatan {
        int id PK
        string kode
        string provinsi
        string kota_kabupaten "nama induk"
        string nama_kecamatan
    }
    kelurahan {
        int id PK
        string kode
        string kecamatan "nama induk"
        string nama_kelurahan
        string kode_bps
    }
    batas_wilayah {
        int id PK
        string kode UK "kode Kemendagri tanpa titik"
        string tingkat
        string nama
        text geojson
        decimal luas_km2
        string sumber "BIG atau dataset cadangan"
    }
    area_polygon {
        int id PK
        string code UK
        string name
        int warehouse_id FK
        string kode_kelurahan FK
        string sumber_polygon
        text geometri
    }
    mk_area {
        int id PK
        string nama
        int depo_id FK
    }
    mk_outlet {
        int id PK
        int area_id FK
        string provinsi
        string kota_kabupaten
        string kecamatan
        string kelurahan
    }
```

## 5. Data aplikasi lama (`lap_*`) dan migrasinya

Kolom `*_id` di tabel `lap_*` diisi oleh `enesis:sambung-transaksi` lewat `mk_peta_nama` (teks nama/kode → ID master). Isinya lalu dipindah ke tabel motoris oleh `enesis:migrasi-transaksi`.

```mermaid
erDiagram
    mk_peta_nama }o..|| mk_karyawan : "nama sales/koordinator ke ID"
    mk_karyawan |o..o{ lap_penjualan_barang : "karyawan_id, koordinator_id"
    lap_penjualan_barang ||..|{ lap_penjualan_barang_detail : "trans_code"
    lap_penjualan_barang ||..o| trx_penjualan : "dimigrasi (sumber_id)"
    lap_checkin_outlet ||..o| trx_kunjungan : "dimigrasi"
    lap_absensi_sales ||..o| trx_absensi : "dimigrasi"
    lap_absensi_koordinator ||..o| trx_absensi : "dimigrasi"
    lap_checkin_checkout ||..o| trx_checkin_depo : "dimigrasi"
    lap_join_visit ||..o| trx_join_visit : "dimigrasi"
    lap_stok_outlet ||..o| trx_stok_outlet : "dimigrasi (sumber lama)"
    lap_stok_kompetitor ||..o| trx_stok_outlet : "dimigrasi (sumber lama_komp)"
    lap_barang_canvassing ||..o| trx_canvassing_item : "dimigrasi"
    lap_pengembalian_barang }o..|| lap_barang_canvassing : "canvassing_no"
    mk_outlet |o..o{ lap_checkin_outlet : "outlet_id dari kode"
    mk_produk |o..o{ lap_penjualan_barang_detail : "produk_id dari SKU"
    mk_distributor |o..o{ lap_loading_barang : "distributor_id"

    mk_peta_nama {
        string jenis "sales, koordinator, depo, area, produk, distributor"
        string nilai "teks asli"
        string depo
        int ref_id "ID master"
    }
    mk_karyawan {
        int id PK
    }
    mk_outlet {
        int id PK
    }
    mk_produk {
        int id PK
    }
    mk_distributor {
        int id PK
    }
    lap_penjualan_barang {
        int source_id
        string trans_code
        string salesperson_name
        int karyawan_id FK
        int koordinator_id FK
        int depo_id FK
        int area_id FK
        int outlet_id FK
    }
    lap_penjualan_barang_detail {
        int source_id
        string trans_code
        string product_sku
        int produk_id FK
    }
    lap_checkin_outlet {
        int source_id
        int karyawan_id FK
        int outlet_id FK
    }
    lap_absensi_sales {
        int source_id
        int karyawan_id FK
        int depo_id FK
    }
    lap_absensi_koordinator {
        int source_id
        int karyawan_id FK
    }
    lap_checkin_checkout {
        int source_id
        int karyawan_id FK
    }
    lap_join_visit {
        int source_id
        int koordinator_id FK
        int karyawan_id FK
    }
    lap_stok_outlet {
        int source_id
        int produk_id FK
    }
    lap_stok_kompetitor {
        int source_id
        string product_name
    }
    lap_barang_canvassing {
        int source_id
        string canvassing_no
        int produk_id FK
    }
    lap_pengembalian_barang {
        int source_id
        string canvassing_no
    }
    lap_loading_barang {
        int source_id
        int distributor_id FK
    }
    trx_penjualan {
        int id PK
        int sumber_id UK
    }
    trx_kunjungan {
        int id PK
        int sumber_id UK
    }
    trx_absensi {
        int id PK
        int sumber_id UK
    }
    trx_checkin_depo {
        int id PK
        int sumber_id UK
    }
    trx_join_visit {
        int id PK
        int sumber_id UK
    }
    trx_stok_outlet {
        int id PK
        int sumber_id
    }
    trx_canvassing_item {
        int id PK
        int sumber_id UK
    }
```

Tabel `lap_*` lain yang punya kolom ID master: `lap_barang_habis_terjual`, `lap_effective_call`, `lap_effective_call_barang` (karyawan, koordinator, depo, area, outlet/produk).
