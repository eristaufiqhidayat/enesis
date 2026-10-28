<?php

namespace App\Support\Master;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Definisi 5 menu CRUD master produk (slug menu => tabel mk_*, kolom daftar, field form, rujukan).
 * Dipakai MasterProdukController dan halaman Vue MasterProduk/Index + MasterProduk/Form.
 *
 * Jenis field: text, textarea, angka, rupiah, pilihan (opsi: satuan|kategori|merk|ya_tidak|aktif), foto.
 */
class MasterProduk
{
    public const DEFINISI = [
        'produk' => [
            'tabel' => 'mk_produk',
            'judul' => 'Produk',
            'nama' => 'produk',
            'keterangan' => 'Dipakai aplikasi HP untuk order, stok outlet, dan canvassing.',
            'kolom' => [
                'foto' => 'Foto', 'kode' => 'Kode', 'nama' => 'Nama Produk', 'merk' => 'Merk', 'kategori' => 'Kategori', 'satuan' => 'Satuan',
                'harga_beli' => 'Harga Beli', 'harga_jual' => 'Harga Jual', 'produk_fokus' => 'Produk Fokus', 'drop_size' => 'Drop Size',
                'maks_poin_per_invoice' => 'Maks Poin/Invoice', 'diubah' => 'Diubah',
            ],
            'cari' => ['p.kode', 'p.nama', 'm.nama', 'k.nama'],
            'field' => [
                ['kode', 'Kode produk', 'text', true, 50, 'Unik, dipakai sebagai SKU di laporan.'],
                ['nama', 'Nama produk', 'text', true, 150, null],
                ['packsize', 'Packsize', 'text', false, 50, null],
                ['merk_id', 'Merk', 'pilihan:merk', true, null, 'Merk tidak aktif tidak bisa dipilih.'],
                ['kategori_id', 'Kategori', 'pilihan:kategori', true, null, null],
                ['satuan_id', 'Satuan', 'pilihan:satuan', true, null, null],
                ['produk_fokus', 'Produk fokus', 'pilihan:ya_tidak', false, null, 'Produk fokus mendapat poin di aplikasi HP.'],
                ['drop_size', 'Drop size', 'angka', false, null, null],
                ['maks_poin_per_invoice', 'Maksimal poin per invoice', 'angka', false, null, null],
                ['keterangan', 'Keterangan', 'textarea', false, 1000, null],
                ['harga_beli', 'Harga beli', 'rupiah', true, null, null],
                ['harga_jual', 'Harga jual', 'rupiah', true, null, 'Harga order di aplikasi HP.'],
                ['harga_spreading', 'Harga spreading', 'rupiah', false, null, null],
                ['het', 'Harga eceran tertinggi (HET)', 'rupiah', false, null, null],
                ['harga_pokok', 'Harga pokok', 'rupiah', false, null, null],
                ['foto', 'Foto', 'foto', false, null, '.jpg, .jpeg, .png, maksimal 2 MB.'],
            ],
            'unik' => ['kode'],
        ],
        'produk_kompetitor' => [
            'tabel' => 'mk_produk_kompetitor',
            'judul' => 'Produk Kompetitor',
            'nama' => 'produk kompetitor',
            'keterangan' => 'Pilihan produk kompetitor saat sales mencatat stok outlet di aplikasi HP.',
            'kolom' => ['foto' => 'Foto', 'nama' => 'Nama Produk', 'satuan' => 'Satuan', 'diubah' => 'Diubah'],
            'cari' => ['p.nama', 's.nama'],
            'field' => [
                ['nama', 'Nama produk', 'text', true, 150, null],
                ['satuan_id', 'Satuan', 'pilihan:satuan', false, null, null],
                ['foto', 'Foto', 'foto', false, null, '.jpg, .jpeg, .png, maksimal 2 MB.'],
            ],
            'unik' => ['nama'],
        ],
        'kategori_produk' => [
            'tabel' => 'mk_kategori_produk',
            'judul' => 'Kategori Produk',
            'nama' => 'kategori',
            'keterangan' => 'Kode kategori wajib unik. Kategori yang masih dipakai produk tidak bisa dihapus.',
            'kolom' => ['kode' => 'Kode', 'nama' => 'Nama', 'total_produk' => 'Total Produk', 'diubah' => 'Diubah'],
            'cari' => ['p.kode', 'p.nama'],
            'field' => [
                ['kode', 'Kode', 'text', true, 30, null],
                ['nama', 'Nama', 'text', true, 100, null],
            ],
            'unik' => ['kode'],
        ],
        'merk_produk' => [
            'tabel' => 'mk_merk_produk',
            'judul' => 'Brand',
            'nama' => 'brand',
            'keterangan' => 'Brand tidak aktif tidak muncul di pilihan produk baru; produk lama tetap memakainya.',
            'kolom' => ['kode' => 'Kode', 'nama' => 'Nama', 'total_produk' => 'Total Produk', 'aktif' => 'Status', 'diubah' => 'Diubah'],
            'cari' => ['p.kode', 'p.nama'],
            'field' => [
                ['kode', 'Kode', 'text', true, 30, null],
                ['nama', 'Nama', 'text', true, 100, null],
                ['aktif', 'Status', 'pilihan:aktif', true, null, null],
            ],
            'unik' => ['kode'],
        ],
        'satuan_produk' => [
            'tabel' => 'mk_satuan_produk',
            'judul' => 'Satuan Produk',
            'nama' => 'satuan',
            'keterangan' => 'Nama satuan wajib unik. Satuan yang masih dipakai produk tidak bisa dihapus.',
            'kolom' => ['nama' => 'Nama', 'total_produk' => 'Total Produk', 'diubah' => 'Diubah'],
            'cari' => ['p.nama'],
            'field' => [
                ['nama', 'Nama', 'text', true, 50, null],
            ],
            'unik' => ['nama'],
        ],
    ];

    /** Kolom rujukan ke master di mk_produk / mk_produk_kompetitor. */
    private const RUJUKAN_MASTER = [
        'satuan_produk' => [['mk_produk', 'satuan_id', 'produk'], ['mk_produk_kompetitor', 'satuan_id', 'produk kompetitor']],
        'kategori_produk' => [['mk_produk', 'kategori_id', 'produk']],
        'merk_produk' => [['mk_produk', 'merk_id', 'produk']],
    ];

    /** Tabel transaksi yang menyimpan produk_id (riwayat lama lap_* dan aplikasi HP trx_*). */
    private const RUJUKAN_PRODUK = [
        'trx_penjualan_item' => 'item penjualan', 'trx_canvassing_item' => 'item canvassing', 'trx_stok_outlet' => 'stok outlet',
        'lap_penjualan_barang_detail' => 'riwayat penjualan', 'lap_barang_canvassing' => 'riwayat canvassing',
        'lap_pengembalian_barang' => 'riwayat pengembalian', 'lap_stok_outlet' => 'riwayat stok outlet',
    ];

    public static function ada(string $slug): bool
    {
        return isset(self::DEFINISI[$slug]);
    }

    public static function def(string $slug): array
    {
        return self::DEFINISI[$slug] ?? abort(404);
    }

    /** Query daftar dengan nama relasi & total produk, alias tabel utama "p". */
    public static function query(string $slug): Builder
    {
        $def = self::def($slug);
        $q = DB::table($def['tabel'].' as p');

        return match ($slug) {
            'produk' => $q->leftJoin('mk_merk_produk as m', 'm.id', '=', 'p.merk_id')
                ->leftJoin('mk_kategori_produk as k', 'k.id', '=', 'p.kategori_id')
                ->leftJoin('mk_satuan_produk as s', 's.id', '=', 'p.satuan_id')
                ->select(['p.*', 'm.nama as merk', 'm.aktif as merk_aktif', 'k.nama as kategori', 's.nama as satuan']),
            'produk_kompetitor' => $q->leftJoin('mk_satuan_produk as s', 's.id', '=', 'p.satuan_id')->select(['p.*', 's.nama as satuan']),
            'kategori_produk', 'merk_produk', 'satuan_produk' => $q->select('p.*')->selectSub(
                DB::table('mk_produk')->selectRaw('COUNT(*)')->whereColumn(self::RUJUKAN_MASTER[$slug][0][1], 'p.id'),
                'total_produk'
            ),
        };
    }

    /** Urutan bawaan daftar. */
    public static function urutkan(string $slug, Builder $q): Builder
    {
        return match ($slug) {
            'produk', 'kategori_produk', 'merk_produk' => $q->orderBy('p.kode'),
            default => $q->orderBy('p.nama'),
        };
    }

    /**
     * Pilihan untuk field "pilihan:<sumber>". Merk tidak aktif hanya ikut bila sedang dipakai data ini.
     *
     * @return list<array{value:string,label:string}>
     */
    public static function opsi(string $sumber, mixed $nilaiSekarang = null): array
    {
        $baris = match ($sumber) {
            'satuan' => DB::table('mk_satuan_produk')->orderBy('nama')->pluck('nama', 'id'),
            'kategori' => DB::table('mk_kategori_produk')->orderBy('nama')->pluck('nama', 'id'),
            'merk' => DB::table('mk_merk_produk')
                ->where(fn ($w) => $w->where('aktif', true)->when($nilaiSekarang !== null && $nilaiSekarang !== '', fn ($x) => $x->orWhere('id', $nilaiSekarang)))
                ->orderBy('nama')->get(['id', 'nama', 'aktif'])
                ->mapWithKeys(fn ($m) => [$m->id => $m->nama.($m->aktif ? '' : ' (tidak aktif)')]),
            'ya_tidak' => collect(['1' => 'Ya', '0' => 'Tidak']),
            'aktif' => collect(['1' => 'Aktif', '0' => 'Tidak aktif']),
        };

        return $baris->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all();
    }

    /** ID yang dibolehkan untuk field pilihan (validasi). */
    public static function idOpsi(string $sumber, mixed $nilaiSekarang = null): array
    {
        return array_column(self::opsi($sumber, $nilaiSekarang), 'value');
    }

    /**
     * Rujukan yang masih memakai data ini; kosong = boleh dihapus.
     *
     * @return list<string> mis. ["3 produk", "120 item penjualan"]
     */
    public static function dipakai(string $slug, int $id): array
    {
        $hasil = [];
        $daftar = $slug === 'produk'
            ? array_map(fn ($t, $label) => [$t, 'produk_id', $label], array_keys(self::RUJUKAN_PRODUK), self::RUJUKAN_PRODUK)
            : (self::RUJUKAN_MASTER[$slug] ?? []);

        foreach ($daftar as [$tabel, $kolom, $label]) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }

            $jumlah = DB::table($tabel)->where($kolom, $id)->count();

            if ($jumlah > 0) {
                $hasil[] = number_format($jumlah, 0, ',', '.').' '.$label;
            }
        }

        return $hasil;
    }

    /** ID untuk data baru: melanjutkan ID terbesar (ID asli Marketama dipertahankan). */
    public static function idBaru(string $slug): int
    {
        return (int) DB::table(self::def($slug)['tabel'])->max('id') + 1;
    }
}
