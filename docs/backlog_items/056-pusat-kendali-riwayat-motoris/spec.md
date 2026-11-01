# Pusat kendali di modal Riwayat Lacak Motoris

## Latar belakang

Modal Riwayat (tombol Lihat riwayat di Lacak Motoris) kini hampir sepenuh layar. Koordinator dan Supervisor memakainya untuk menilai kinerja motoris sekaligus mencari potensi kecurangan, tetapi informasinya tercecer: ringkasan beli/tidak beli hanya satu baris kecil, peringatan GPS tidak wajar tidak tampil sama sekali (hanya mengisi form Tandai dan menggambar garis di peta), peringatan jarak dekat berupa tombol terpisah, dan tidak ada cara cepat berpindah hari atau menyaring daftar kunjungan.

## Tujuan

Satu tampilan yang menjawab berurutan: (1) siapa dan tanggal berapa, (2) bagaimana kinerja hari itu, (3) apa yang mencurigakan, (4) di mana buktinya di peta dan daftar.

## Cakupan

- **Navigasi tanggal**: tombol hari sebelumnya/berikutnya di samping pemilih tanggal; hari berikutnya nonaktif di hari ini; pemilih tanggal tidak bisa memilih tanggal mendatang.
- **Ringkasan KPI** (hanya bila endpoint mengirim `kunjungan`, yaitu Sales): jumlah kunjungan, beli (persen), tidak beli, total penjualan, jam kerja (kunjungan pertama sampai terakhir beserta durasinya).
- **Panel Temuan terpadu**: satu tombol berlencana jumlah dan warna tingkat tertinggi (hijau "Tidak ada temuan" bila bersih). Panel melayang berisi daftar temuan dari: lokasi palsu terdeteksi, lompatan posisi mustahil, titik GPS menyimpang, tiap runtun kunjungan berjarak dekat, kunjungan di luar rute, outlet ditandai, kunjungan tanpa koordinat. Klik temuan memperbesar peta ke titiknya dan menyorot kunjungan terkait di daftar. Selalu ada catatan: indikasi dari pola data, bukan bukti kecurangan.
- **Filter daftar kunjungan**: Semua, Beli, Tidak beli, Bermasalah (dengan hitungan). Nomor tetap sama dengan penanda di peta.
- **Legenda peta**: arti warna penanda, garis rute, lompatan, dan titik menyimpang.
- Tanpa perubahan backend; semua data sudah ada di endpoint `locations`.

## Di luar cakupan

Perubahan aturan deteksi, ambang, atau form Tandai motoris/outlet.

## Asumsi desain

- Tingkat temuan: `bahaya` (lokasi palsu terdeteksi, outlet ditandai, lompatan saat HP menandai mock), `waspada` (lompatan/menyimpang/jarak dekat/di luar rute), `info` (tanpa koordinat).
- "Bermasalah" = kunjungan di luar rute, lokasi palsu, outlet ditandai, tanpa koordinat, atau bagian dari runtun jarak dekat.
