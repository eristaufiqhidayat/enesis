<?php

namespace App\Support\Marketama;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Sambungkan tabel transaksi hasil tarikan awal (lap_*, berisi teks nama) ke ID master mk_*.
// Teks asli tidak diubah; hanya kolom *_id yang diisi. Nama sales yang kembar diselesaikan
// dengan depo transaksi. Aman dijalankan ulang (mis. setelah marketama:import).
class SambungTransaksi
{
    /**
     * Tabel => kolom ID => [jenis, kolom sumber]. Jenis "sales"/"koordinator" memakai warehouse_name
     * sebagai pembeda nama kembar.
     *
     * @var array<string, array<string, array{0:string, 1:string}>>
     */
    public const PETA = [
        'lap_penjualan_barang' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
        ],
        'lap_penjualan_barang_detail' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
            'produk_id' => ['produk', 'product_sku'],
        ],
        'lap_effective_call' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
        ],
        'lap_effective_call_barang' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
            'produk_id' => ['produk', 'product_sku'],
        ],
        'lap_checkin_outlet' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
        ],
        'lap_stok_outlet' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
            'produk_id' => ['produk', 'product_code'],
        ],
        'lap_join_visit' => [
            'karyawan_id' => ['sales', 'sales_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'outlet_id' => ['outlet', 'outlet_code'],
        ],
        'lap_absensi_sales' => [
            'karyawan_id' => ['sales', 'employee_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'],
        ],
        'lap_barang_canvassing' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'produk_id' => ['produk', 'product_sku'],
        ],
        'lap_barang_habis_terjual' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'koordinator_id' => ['koordinator', 'koordinator_name'],
            'depo_id' => ['depo', 'warehouse_name'], 'area_id' => ['area', 'area_name'], 'produk_id' => ['produk', 'product_sku'],
        ],
        'lap_pengembalian_barang' => [
            'karyawan_id' => ['sales', 'salesperson_name'], 'depo_id' => ['depo', 'warehouse_name'],
            'area_id' => ['area', 'area_name'], 'produk_id' => ['produk', 'product_sku'],
        ],
        'lap_loading_barang' => [
            'koordinator_id' => ['koordinator', 'koordinator_name'], 'depo_id' => ['depo', 'warehouse_name'],
            'distributor_id' => ['distributor', 'distributor_name'],
        ],
    ];

    private const POTONGAN = 50000;

    /** @var Closure(string): void */
    private Closure $log;

    public function __construct(?Closure $log = null)
    {
        $this->log = $log ?? fn (string $pesan) => null;
    }

    /**
     * @param  list<string>  $hanya  batasi ke tabel tertentu
     * @return list<array{tabel:string, kolom:string, terisi:int, sumber:int}>
     */
    public function jalankan(array $hanya = []): array
    {
        $tabel = array_filter(
            self::PETA,
            fn ($t) => (! $hanya || in_array($t, $hanya, true)) && Schema::hasTable($t) && Schema::hasColumn($t, 'id'),
            ARRAY_FILTER_USE_KEY,
        );

        $this->bangunPeta($tabel);
        $laporan = [];

        foreach ($tabel as $t => $kolomId) {
            $maks = (int) DB::table($t)->max('id');

            foreach ($kolomId as $kolom => [$jenis, $sumber]) {
                if (! Schema::hasColumn($t, $kolom) || ! Schema::hasColumn($t, $sumber)) {
                    continue;
                }

                for ($dari = 0; $dari <= $maks; $dari += self::POTONGAN) {
                    DB::table($t)->whereBetween('id', [$dari, $dari + self::POTONGAN - 1])
                        ->update([$kolom => DB::raw($this->subquery($t, $jenis, $sumber))]);
                }

                $sumberIsi = DB::table($t)->whereNotNull($sumber)->whereRaw($this->teks($sumber)." NOT IN ('', '-')")->count();
                $terisi = DB::table($t)->whereNotNull($kolom)->count();
                $laporan[] = ['tabel' => $t, 'kolom' => $kolom, 'terisi' => $terisi, 'sumber' => $sumberIsi];
                ($this->log)(sprintf('%s.%s: %s / %s', $t, $kolom, number_format($terisi, 0, ',', '.'), number_format($sumberIsi, 0, ',', '.')));
            }
        }

        return $laporan;
    }

    /** Ekspresi SQL pengisi satu kolom ID (subquery berkorelasi ke tabel peta / mk_outlet). */
    private function subquery(string $tabel, string $jenis, string $sumber): string
    {
        $nilai = $this->teks("{$tabel}.{$sumber}");

        if ($jenis === 'outlet') {
            return "(SELECT MIN(o.id) FROM mk_outlet o WHERE o.kode = {$nilai})";
        }

        $syaratDepo = in_array($jenis, ['sales', 'koordinator'], true)
            ? " AND (p.depo = '' OR p.depo = LOWER(TRIM({$tabel}.warehouse_name)))"
            : '';

        return "(SELECT MIN(p.ref_id) FROM mk_peta_nama p WHERE p.jenis = '{$jenis}' AND p.nilai = {$nilai}{$syaratDepo})";
    }

    private function teks(string $kolom): string
    {
        return DB::connection()->getDriverName() === 'mysql' ? "CAST({$kolom} AS CHAR)" : "CAST({$kolom} AS TEXT)";
    }

    /**
     * Isi mk_peta_nama: setiap nilai teks (apa adanya) di tabel transaksi => ID master.
     * Nama sales/koordinator kembar: satu baris per depo yang membuatnya unik.
     */
    private function bangunPeta(array $tabel): void
    {
        $master = [
            'depo' => $this->unik(DB::table('mk_depo')->get(['id', 'nama'])),
            'area' => $this->unik(DB::table('mk_area')->get(['id', 'nama'])),
            'distributor' => $this->unik(DB::table('mk_distributor')->get(['id', 'nama'])),
            'produk' => DB::table('mk_produk')->whereNotNull('kode')->pluck('id', 'kode')->mapWithKeys(fn ($id, $k) => [mb_strtolower(trim($k)) => $id])->all(),
        ];

        // Orang (sales/koordinator): nama => daftar [id, depo]. Nama kembar diselesaikan dengan depo.
        $depoNama = DB::table('mk_depo')->pluck('nama', 'id')->map(fn ($n) => mb_strtolower(trim($n)));
        $orang = ['sales' => [], 'koordinator' => [], 'semua' => []];

        foreach (DB::table('mk_karyawan')->get(['id', 'nama', 'kategori', 'depo_id', 'aktif']) as $k) {
            $kunci = mb_strtolower(trim((string) $k->nama));
            $calon = ['id' => (int) $k->id, 'depo' => $depoNama[$k->depo_id] ?? null, 'aktif' => (bool) $k->aktif];
            $orang['semua'][$kunci][] = $calon;

            if (isset($orang[$k->kategori])) {
                $orang[$k->kategori][$kunci][] = $calon;
            }
        }

        $baris = [];

        foreach ($tabel as $t => $kolomId) {
            foreach ($kolomId as [$jenis, $sumber]) {
                if ($jenis === 'outlet' || ! Schema::hasColumn($t, $sumber)) {
                    continue;
                }

                $nilaiUnik = DB::table($t)->select(DB::raw($this->teks($sumber).' AS v'))->whereNotNull($sumber)->distinct()->pluck('v');

                foreach ($nilaiUnik as $v) {
                    $kunci = mb_strtolower(trim((string) $v));

                    if ($kunci === '' || $kunci === '-') {
                        continue;
                    }

                    if (! isset($orang[$jenis])) {
                        if (isset($master[$jenis][$kunci])) {
                            $baris["{$jenis}|{$v}|"] = ['jenis' => $jenis, 'nilai' => (string) $v, 'depo' => '', 'ref_id' => $master[$jenis][$kunci]];
                        }

                        continue;
                    }

                    // Cari di kategorinya; bila tidak ada (mis. sales yang kini koordinator), di semua karyawan.
                    $calon = $orang[$jenis][$kunci] ?? $orang['semua'][$kunci] ?? [];

                    foreach ($this->petaOrang($calon) as $depo => $id) {
                        $baris["{$jenis}|{$v}|{$depo}"] = ['jenis' => $jenis, 'nilai' => (string) $v, 'depo' => $depo, 'ref_id' => $id];
                    }
                }
            }
        }

        DB::table('mk_peta_nama')->delete();

        foreach (array_chunk(array_values($baris), 1000) as $potongan) {
            DB::table('mk_peta_nama')->insert($potongan);
        }

        ($this->log)(sprintf('peta nama: %d nilai', count($baris)));
    }

    /**
     * Calon orang bernama sama => [depo => id]. Satu calon: depo '' (berlaku di semua depo).
     * Beberapa calon: per depo, pakai orang satu-satunya di depo itu; bila di satu depo ada
     * beberapa (mis. karyawan yang didaftarkan ulang), pakai yang aktif bila hanya satu.
     *
     * @param  list<array{id:int, depo:?string, aktif:bool}>  $calon
     * @return array<string, int>
     */
    private function petaOrang(array $calon): array
    {
        if (count($calon) === 1) {
            return ['' => $calon[0]['id']];
        }

        $perDepo = [];

        foreach ($calon as $c) {
            if ($c['depo'] !== null) {
                $perDepo[$c['depo']][] = $c;
            }
        }

        $hasil = [];

        foreach ($perDepo as $depo => $orang) {
            $aktif = array_values(array_filter($orang, fn ($c) => $c['aktif']));
            $pilih = count($orang) === 1 ? $orang[0] : (count($aktif) === 1 ? $aktif[0] : null);

            if ($pilih) {
                $hasil[$depo] = $pilih['id'];
            }
        }

        return $hasil;
    }

    /** nama (huruf kecil) => id, hanya untuk nama yang tidak kembar. */
    private function unik($rows): array
    {
        $peta = [];
        $kembar = [];

        foreach ($rows as $r) {
            $k = mb_strtolower(trim((string) $r->nama));
            isset($peta[$k]) ? $kembar[$k] = true : $peta[$k] = (int) $r->id;
        }

        return array_diff_key($peta, $kembar);
    }
}
