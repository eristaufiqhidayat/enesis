<?php

namespace App\Support\Area;

use App\Support\Wilayah\Geometri;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Penghubung area_polygon (peta area: Design Area, Generate Polygon Kelurahan, Auto Grid) dengan mk_area
// (master area yang dibaca aplikasi HP, outlet, dan penugasan area sales lewat mk_karyawan_area).
//
// ID mk_area:
// - area tarikan Marketama: ID asli Marketama = area_polygon.source_id (sama dengan Penarik::areaDariPolygon);
// - area buatan Enesis (source_id kosong): ID_AWAL + area_polygon.id, supaya tidak pernah bertabrakan dengan
//   ID Marketama (saat ini < 30.000) dan tetap bisa dihitung dari area_polygon tanpa kolom tambahan.
class AreaMk
{
    public const ID_AWAL = 1_000_000_000;

    // Tabel yang merujuk ID mk_area; area yang masih dirujuk tidak boleh dihapus.
    public const RUJUKAN = [
        'mk_outlet' => ['area_id', 'outlet'],
        'mk_karyawan_area' => ['area_id', 'penugasan sales'],
        'mk_karyawan' => ['area_aktif_id', 'sales dengan area aktif'],
        'trx_outlet_baru' => ['area_id', 'outlet baru dari HP'],
        'trx_join_visit' => ['area_id', 'join visit'],
    ];

    private const KOLOM = ['id', 'source_id', 'code', 'name', 'warehouse', 'warehouse_id', 'branch_area', 'branch_area_id', 'province', 'province_id',
        'city', 'city_id', 'district', 'district_id', 'village', 'village_id', 'kode_kelurahan', 'outlet_count', 'salesperson', 'sumber_polygon'];

    public static function id(object $area): int
    {
        return $area->source_id !== null ? (int) $area->source_id : self::ID_AWAL + (int) $area->id;
    }

    /** Ekspresi SQL ID mk_area untuk alias tabel area_polygon, mis. dipakai join di menu Master Area. */
    public static function idSql(string $alias = 'ap'): string
    {
        return "COALESCE({$alias}.source_id, {$alias}.id + ".self::ID_AWAL.')';
    }

    /**
     * Salin area_polygon ke mk_area (tambah atau perbarui kode, nama, depo, data).
     *
     * @param  list<int>|null  $ids  ID area_polygon; null = semua
     * @return int jumlah baris yang disalin
     */
    public static function sinkron(?array $ids = null): int
    {
        if (! Schema::hasTable('mk_area')) {
            return 0;
        }

        $jumlah = 0;
        DB::table('area_polygon')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))
            ->select(self::KOLOM)->orderBy('id')
            ->chunk(1000, function ($rows) use (&$jumlah) {
                DB::table('mk_area')->upsert($rows->map(fn ($r) => [
                    'id' => self::id($r),
                    'kode' => $r->code,
                    'nama' => (string) $r->name,
                    'depo_id' => $r->warehouse_id ? (int) $r->warehouse_id : null,
                    'data' => json_encode(array_diff_key((array) $r, ['source_id' => 1, 'id' => 1]) + [
                        'sumber' => $r->source_id !== null ? 'area_polygon' : 'enesis',
                        'area_polygon_id' => (int) $r->id,
                    ]),
                ])->all(), ['id'], ['kode', 'nama', 'depo_id', 'data']);
                self::perbaruiKotak($rows->pluck('id')->all());
                $jumlah += $rows->count();
            });

        return $jumlah;
    }

    /**
     * Isi kotak batas (min/max lng/lat) area dari geometri atau polygon-nya, untuk pencarian area per layar peta.
     *
     * @param  list<int>  $ids  ID area_polygon
     */
    public static function perbaruiKotak(array $ids): void
    {
        if (! $ids || ! Schema::hasColumn('area_polygon', 'min_lng')) {
            return;
        }
        DB::table('area_polygon')->whereIn('id', $ids)->select(['id', 'polygon', 'geometri'])->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $r) {
                    $multi = $r->geometri ? (json_decode($r->geometri, true)['coordinates'] ?? []) : [[json_decode((string) $r->polygon, true) ?: []]];
                    $k = Geometri::kotak($multi);
                    DB::table('area_polygon')->where('id', $r->id)->update($k
                        ? ['min_lng' => $k[0], 'min_lat' => $k[1], 'max_lng' => $k[2], 'max_lat' => $k[3]]
                        : ['min_lng' => null, 'min_lat' => null, 'max_lng' => null, 'max_lat' => null]);
                }
            });
    }

    /** Sinkron area berdasarkan kode (untuk insert massal Generate/Auto Grid yang tidak mengembalikan ID). */
    public static function sinkronKode(array $kode): int
    {
        $ids = [];
        foreach (array_chunk($kode, 1000) as $potongan) {
            array_push($ids, ...DB::table('area_polygon')->whereIn('code', $potongan)->pluck('id')->all());
        }

        return $ids ? self::sinkron($ids) : 0;
    }

    /**
     * Rujukan yang masih memakai area ini, mis. ["12 outlet", "2 penugasan sales"]; kosong = aman dihapus.
     *
     * @return list<string>
     */
    public static function dirujuk(object $area): array
    {
        $id = self::id($area);
        $hasil = [];
        foreach (self::RUJUKAN as $tabel => [$kolom, $label]) {
            if (Schema::hasTable($tabel) && ($n = DB::table($tabel)->where($kolom, $id)->count())) {
                $hasil[] = number_format($n, 0, ',', '.').' '.$label;
            }
        }

        return $hasil;
    }

    /** Hapus baris mk_area area ini (dipanggil setelah dipastikan tidak dirujuk). */
    public static function hapus(object $area): void
    {
        if (Schema::hasTable('mk_area')) {
            DB::table('mk_area')->where('id', self::id($area))->delete();
        }
    }
}
