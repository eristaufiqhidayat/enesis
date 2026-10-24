# Gabung Menu Rekap Pencapaian SKU per Motoris & per Depo jadi Satu Halaman Bertab

## Problem Statement

Dua menu di grup pertama sidebar (`config/marketama.php:21-30`) — "Rekap Pencapaian SKU per Motoris" (`rekap_pencapaian_sku_motoris`) dan "Rekap Pencapaian SKU per Depo" (`rekap_pencapaian_depo_sku`) — adalah dua menu terpisah yang isinya sama persis secara struktural: masing-masing menumpuk dua tabel (`...tableachieved` dan `...tablenotachieved`, lihat `config/marketama.php:771-786` & `2926-2971`) dengan kolom yang hampir identik (`no`, `depo`, `motoris`/`-`, `total_qty`, `hari_kerja`, `avg_day`/`avg_day_motoris`, `kategori`). Kedua menu ini adalah dua sudut pandang (per motoris vs. per depo) dari rekap pencapaian SKU yang sama, kemungkinan besar selalu dicek berurutan oleh user yang sama, tapi sekarang harus klik dua item sidebar berbeda dan kehilangan konteks pencarian setiap pindah menu.

Pola penggabungan menu bertab sudah ada sejak tiket [011](../011-gabung-menu-monitoring-harian-tab/spec.md) ("Monitoring Harian Sales"), tapi mekanisme tab yang dibangun di sana (`page.tabs === true`, `MenuController@show`, `Tabs.vue`) berasumsi **satu tab = satu tabel**. Di kasus ini, satu tab ("Per Motoris" atau "Per Depo") perlu menampilkan **dua tabel sekaligus** (achieved + not achieved), sehingga mekanisme tab yang ada perlu digeneralisasi dulu sebelum bisa dipakai di sini.

## Solution

Gabungkan kedua menu ini menjadi satu halaman dengan satu slug sidebar, dirender dengan tab di bagian atas (satu tab = "Per Motoris", satu tab = "Per Depo"), masing-masing tab menumpuk dua tabel (achieved + not achieved) seperti tampilan tabel di masing-masing menu sekarang. Untuk mendukung ini, generalisasi mekanisme tab (`page.tabs`, `MenuController@show`, `Tabs.vue`) dari "satu tab = satu tabel" menjadi "satu tab = satu grup tabel (1..n tabel)", supaya pola ini reusable untuk menu multi-tabel-per-tab lain di masa depan (bukan cuma dua menu ini).

1. Sidebar punya satu item menu menggantikan dua item sekarang.
2. Pindah antar tab tidak reload query pencarian dari awal.
3. Tab aktif tercermin di URL (query string `?tab=`) supaya bisa dibagikan/bookmark dan tombol back/forward browser berfungsi wajar.

## User Stories

1. Sebagai admin yang mengecek pencapaian SKU, saya ingin melihat rekap per Motoris dan per Depo dari satu halaman, supaya saya tidak perlu bolak-balik ke sidebar untuk dua menu yang isinya saling berkaitan.
2. Sebagai admin yang sedang berada di tab "Per Depo", saya ingin pindah ke tab "Per Motoris" tanpa harus mengatur ulang pencarian saya, supaya alur kerja saya tidak terganggu.
3. Sebagai admin yang menyalin URL halaman ini (termasuk tab yang sedang aktif) dan membagikannya ke rekan kerja, saya ingin rekan saya membuka tab yang sama saat link dibuka, supaya saya tidak perlu menjelaskan ulang klik mana yang harus dilakukan.
4. Sebagai admin yang berada di tab "Per Motoris", saya ingin tetap melihat dua tabelnya (tercapai & tidak tercapai) sekaligus seperti tampilan menu "per Motoris" sekarang, supaya tidak ada informasi yang hilang dibanding sebelum digabung.
5. Sebagai developer yang menambah menu rekap serupa di masa depan (tab yang isinya lebih dari satu tabel), saya ingin mekanisme tab ini reusable untuk grup tabel (bukan cuma satu tabel per tab seperti tiket 011), supaya tidak perlu menulis ulang logika tab setiap kali ada kasus seperti ini.

## Implementation Decisions

- **Config (`config/marketama.php`):**
  - Tambah satu slug baru (usul: `rekap_pencapaian_sku`) di `menu` (grup pertama) menggantikan dua entri `rekap_pencapaian_sku_motoris` dan `rekap_pencapaian_depo_sku` yang ada sekarang (baris 21-30).
  - Di `pages`, entri baru ini set `'tabs' => true` dan elemen `tables` jadi grup per tab, bukan satu tabel per elemen seperti tiket 011. Struktur usul: setiap elemen `tables` adalah array asosiatif `{'tab_key' => ..., 'tab_label' => ..., 'tables' => [...nama tabel...]}` — field `tables` di sini berisi 1..n nama tabel yang ditumpuk di tab tersebut (untuk kasus ini: 2 tabel per tab, achieved + not achieved).
  - Dua entri `pages.rekap_pencapaian_sku_motoris` dan `pages.rekap_pencapaian_depo_sku` yang lama dihapus setelah dipindah isinya ke entri gabungan (lihat Testing Decisions soal redirect).
- **`MenuController@show` (generalisasi dari tiket 011):**
  - Loop utama berubah dari "per tabel = per tab" menjadi dua level: loop grup tab, lalu di dalamnya loop tabel-tabel milik grup itu (reuse logic query/search/paginate yang sudah ada per tabel, cuma dikelompokkan).
  - `activeTab` sekarang divalidasi/dibandingkan terhadap `tab_key` grup (bukan nama tabel individual seperti di tiket 011), karena satu tab kini bisa punya lebih dari satu tabel.
  - Prop `tables` yang dikirim ke Inertia tetap flat list semua tabel (seperti sekarang) tapi tiap elemen tabel dapat tambahan field `tab_key` supaya Vue tahu tabel itu milik tab mana saat memfilter `visibleTables`.
  - `date_filter_column`/`kpi` per tabel (hasil generalisasi tiket 011) tidak dipakai di menu ini (kedua tabel di tiap tab tidak punya filter tanggal/KPI khusus) — tapi struktur yang sudah ada tidak perlu diubah, cukup diisi `null` seperti tabel-tabel non-checkin sekarang.
- **Vue (`resources/js/Pages/Menu/Show.vue` & `Tabs.vue`):**
  - `visibleTables` difilter berdasarkan `table.tab_key === props.activeTab`, bukan `table.name === props.activeTab`, supaya tab aktif bisa menampilkan lebih dari satu tabel.
  - `tabItems` (daftar tab yang dirender `Tabs.vue`) dibangun dari grup unik `tab_key`/`tab_label`, bukan dari setiap tabel satu-satu seperti tiket 011 — perlu util kecil di `Show.vue` untuk dedupe tabel jadi daftar tab.
  - Tidak ada perubahan pada komponen `Tabs.vue` sendiri (sudah generic: terima `tabs: [{key, label}]`), hanya cara `tabItems` dihitung dari props `tables` yang berubah.
- **Blade stack:** menu ini dirender lewat Inertia (`Menu/Show.vue`), bukan Blade custom, jadi tidak ada perubahan di sisi `resources/views/` untuk dua menu ini — hanya `resources/views/components/ui/organisms/sidebar.blade.php` dan Vue `Sidebar.vue` yang perlu update karena dua item menu lama diganti satu item baru.
- **Redirect slug lama:** tambah route redirect dari `/rekap_pencapaian_sku_motoris` dan `/rekap_pencapaian_depo_sku` ke `/rekap_pencapaian_sku?tab=<tab-terkait>` supaya bookmark/link lama tidak 404.

## Testing Decisions

- Feature test di `tests/Feature/MenuTest.php` (pola sudah ada dari tiket 011) yang:
  - Assert GET `/rekap_pencapaian_sku` mengembalikan Inertia response dengan prop `tables` berisi empat entri (dua tabel per tab), masing-masing `tab_key` sesuai grup motoris/depo.
  - Assert default `activeTab` adalah `tab_key` tab pertama kalau query `tab` kosong/tidak valid.
  - Assert query `tab=motoris` hanya menampilkan dua tabel milik grup motoris (bukan empat tabel sekaligus) — ini juga sekaligus memvalidasi generalisasi grouping tab di `MenuController@show`.
  - Assert request ke slug lama (`/rekap_pencapaian_sku_motoris`, `/rekap_pencapaian_depo_sku`) redirect (302) ke `/rekap_pencapaian_sku` dengan query `tab` yang sesuai.
- Verifikasi manual didokumentasikan di PR (belum ada test runner JS di proyek ini, konsisten dengan tiket 009/010/011): klik antar tab, cek kedua tabel (achieved/not achieved) tetap tampil sekaligus di tab yang sama, cek URL berubah mengikuti tab aktif, cek sidebar hanya punya satu item menu menggantikan dua item lama.

## Out of Scope

- Mengubah skema tabel (`rekap_pencapaian_sku_motoris_tableachieved`, dst.) atau cara datanya diimpor — tiket ini murni perubahan tampilan/routing, bukan data.
- Menggabungkan menu rekap pencapaian lain yang belum disebut user (mis. "Summary All Depo", "Motoris Performance") ke dalam tab yang sama — hanya dua menu yang disebutkan.
- Menambah filter tanggal/KPI baru untuk tab ini — menu sumbernya memang tidak punya filter tanggal/KPI, tidak ada yang perlu dipertahankan atau ditambahkan.
- Animasi transisi saat ganti tab — cukup perubahan state instan seperti pola tab di tiket 011.

## Further Notes

- Tabel `rekap_pencapaian_sku_motoris_tableachieved`, `rekap_pencapaian_sku_motoris_tablenotachieved`, `rekap_pencapaian_depo_sku_tableachieved`, `rekap_pencapaian_depo_sku_tablenotachieved` bukan tabel data pribadi/transaksi besar, jadi tidak ada pertimbangan privasi tambahan untuk seeding/testing.
- Generalisasi mekanisme tab (satu tab = satu grup tabel) di tiket ini akan membuat pola tiket 011 (satu tab = satu tabel) jadi kasus khusus (grup berisi satu tabel) — idealnya tiket 011 tidak perlu diubah perilakunya, cuma struktur datanya disesuaikan ke bentuk grup yang baru.
- Penamaan slug gabungan (`rekap_pencapaian_sku`) dan `tab_key` (`motoris`/`depo`) adalah usulan — bisa disesuaikan saat implementasi kalau ada nama yang lebih konsisten dengan penamaan menu lain di `config/marketama.php`.
