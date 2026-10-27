<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Area\KodeArea;
use App\Support\Area\WilayahKota;
use App\Support\Wilayah\Geometri;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

// Generate Polygon Kelurahan: buat area (area_polygon) 1 area = 1 kelurahan/desa dari batas resmi Kemendagri
// di tabel batas_wilayah. Berbeda dengan Marketama (mencari tiap kelurahan ke OpenStreetMap dari browser),
// batas diambil sekali per kota di server, dicocokkan lewat kode kelurahan, dan disimpan utuh (semua pulau).
class GenerateKelurahanController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Area/GenerateKelurahan', [
            'slug' => 'master_area',
            'title' => 'Generate Polygon Kelurahan',
            'provinsi' => DB::table('provinsi')->orderBy('nama_provinsi')->get(['id', 'kode', 'nama_provinsi as nama']),
            'depo' => DB::table('area_depo')->orderBy('name')->get(['warehouse_id', 'name', 'province', 'lat', 'lng']),
            'bisaTambah' => Akses::bisa($request->user(), 'master_area', 'tambah'),
            'bisaUbah' => Akses::bisa($request->user(), 'master_area', 'ubah'),
            'bisaHapus' => Akses::bisa($request->user(), 'master_area', 'hapus'),
            'kembaliUrl' => route('design-area.index'),
            'designUrl' => route('design-area.index'),
            'hapusUrl' => url('master_area/design_area/areas'),
            'autoGridUrl' => route('auto-grid.index'),
            'opsiUrl' => route('kelurahan-polygon.opsi'),
            'pratinjauUrl' => route('kelurahan-polygon.pratinjau'),
            'simpanUrl' => route('kelurahan-polygon.simpan'),
        ]);
    }

    /** Dropdown bertingkat: kota per provinsi, kecamatan per kota. */
    public function opsi(Request $request)
    {
        $data = $request->validate([
            'tingkat' => ['required', 'in:kota,kecamatan'],
            'provinsi_id' => ['required_if:tingkat,kota', 'integer'],
            'kota_id' => ['required_if:tingkat,kecamatan', 'integer'],
        ]);

        if ($data['tingkat'] === 'kota') {
            $provinsi = DB::table('provinsi')->find($data['provinsi_id']);
            abort_unless($provinsi, 404);

            return DB::table('kota_kabupaten')->where('provinsi', $provinsi->nama_provinsi)
                ->orderBy('nama_kota_kabupaten')->get(['id', 'kode', 'nama_kota_kabupaten as nama']);
        }

        $kota = DB::table('kota_kabupaten')->find($data['kota_id']);
        abort_unless($kota, 404);

        return DB::table('kecamatan')->where('provinsi', $kota->provinsi)->where('kota_kabupaten', $kota->nama_kota_kabupaten)
            ->orderBy('nama_kecamatan')->get(['id', 'kode', 'nama_kecamatan as nama']);
    }

    /** Daftar kelurahan satu kota (atau satu kecamatan) beserta batasnya dan area yang sudah ada. */
    public function pratinjau(Request $request, WilayahKota $wilayah)
    {
        $data = $request->validate([
            'kota_id' => ['required', 'integer'],
            'kecamatan_id' => ['nullable', 'integer'],
        ]);

        $kota = DB::table('kota_kabupaten')->find($data['kota_id']);
        abort_unless($kota, 404);
        $kecamatan = ! empty($data['kecamatan_id']) ? DB::table('kecamatan')->find($data['kecamatan_id']) : null;

        $peringatan = $wilayah->siapkanBatas($kota);
        $kelurahan = $wilayah->kelurahan($kota, $kecamatan);
        $batasPerKode = $wilayah->batasPerKode($kelurahan);
        $areaAda = $this->areaYangAda($kota, $kelurahan);

        $hasil = $kelurahan->map(function ($k) use ($batasPerKode, $areaAda) {
            $b = $k->kode ? $batasPerKode->get($k->kode) : null;

            return [
                'id' => $k->id,
                'kode' => $k->kode,
                'nama' => $k->nama_kelurahan,
                'kecamatan' => $k->kecamatan,
                'geometri' => $b ? json_decode($b->geojson)->coordinates : null,
                'lat' => $b?->lat !== null ? (float) $b->lat : null,
                'lng' => $b?->lng !== null ? (float) $b->lng : null,
                'luas_km2' => $b?->luas_km2 !== null ? (float) $b->luas_km2 : null,
                'jumlah_titik' => $b?->jumlah_titik,
                'area' => $areaAda($k),
            ];
        })->values();

        return response()->json([
            'kota' => $kota->nama_kota_kabupaten,
            'kecamatan' => $kecamatan?->nama_kecamatan,
            'kelurahan' => $hasil,
            'peringatan' => $peringatan,
        ]);
    }

    /** Simpan kelurahan terpilih sebagai area baru milik satu depo. Batas diambil ulang dari server, bukan dari browser. */
    public function simpan(Request $request, WilayahKota $wilayah)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:area_depo,warehouse_id'],
            'kelurahan_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'kelurahan_ids.*' => ['integer', 'distinct'],
        ], [], ['warehouse_id' => 'depo', 'kelurahan_ids' => 'kelurahan']);

        $depo = DB::table('area_depo')->where('warehouse_id', $data['warehouse_id'])->first();
        $kelurahan = DB::table('kelurahan')->whereIn('id', $data['kelurahan_ids'])->get()->keyBy('id');

        $hasil = DB::transaction(function () use ($data, $depo, $kelurahan, $wilayah) {
            $batas = $wilayah->batasPerKode($kelurahan);

            $cariArea = $this->areaYangAda(null, $kelurahan);
            $dilewati = [];
            $baru = [];
            foreach ($data['kelurahan_ids'] as $id) {
                $k = $kelurahan->get($id);
                $ada = $k ? $cariArea($k) : [];
                $alasan = match (true) {
                    ! $k => 'Kelurahan tidak ditemukan',
                    $ada !== [] => 'Sudah ada area '.$ada[0]['code'].($ada[0]['warehouse'] ? ' ('.$ada[0]['warehouse'].')' : ''),
                    ! $k->kode || ! $batas->has($k->kode) => 'Tidak ada batas resmi',
                    default => null,
                };

                if ($alasan) {
                    $dilewati[] = ['id' => $id, 'nama' => $k?->nama_kelurahan, 'alasan' => $alasan];
                } else {
                    $baru[] = $k;
                }
            }

            if (! $baru) {
                return ['dibuat' => 0, 'kode' => [], 'dilewati' => $dilewati];
            }

            $idWilayah = $wilayah->idWilayah(collect($baru));
            $kode = KodeArea::berikutnya(count($baru));
            $baris = [];
            foreach ($baru as $i => $k) {
                $multi = json_decode($batas[$k->kode]->geojson, true)['coordinates'];
                $baris[] = [
                    'code' => $kode[$i],
                    'name' => $k->nama_kelurahan,
                    'polygon' => json_encode(Geometri::cincinTerbesar($multi)),
                    'geometri' => json_encode(['type' => 'MultiPolygon', 'coordinates' => $multi]),
                    'warehouse' => $depo->name,
                    'warehouse_id' => $depo->warehouse_id,
                    ...WilayahKota::kolomWilayah($k, $idWilayah),
                    'sumber_polygon' => $batas[$k->kode]->sumber,
                    'info' => 'Kel. '.$k->nama_kelurahan.', Kec. '.$k->kecamatan,
                    'outlet_count' => 0,
                ];
            }
            foreach (array_chunk($baris, 100) as $potongan) {
                DB::table('area_polygon')->insert($potongan);
            }

            return ['dibuat' => count($baris), 'kode' => [$kode[0], end($kode)], 'dilewati' => $dilewati];
        });

        return response()->json($hasil, $hasil['dibuat'] ? 201 : 200);
    }

    /**
     * Pencari area yang sudah menutup sebuah kelurahan: lewat village_id / kode_kelurahan (area hasil generate),
     * atau lewat nama kota+kecamatan+kelurahan (area lama hasil tarikan Marketama yang belum punya ID).
     *
     * @return callable(object): list<array{id: int, code: string, name: string, warehouse: ?string}>
     */
    private function areaYangAda(?object $kota, Collection $kelurahan): callable
    {
        $ids = $kelurahan->pluck('id')->all();
        $kode = $kelurahan->pluck('kode')->filter()->all();
        $kotaNama = $kota?->nama_kota_kabupaten ?? $kelurahan->pluck('kota_kabupaten')->unique()->all();

        $area = DB::table('area_polygon')
            ->where(function ($q) use ($ids, $kode, $kotaNama) {
                $q->whereIn('village_id', $ids)
                    ->orWhereIn('kode_kelurahan', $kode ?: ['-'])
                    // Nama kelurahan dicocokkan di PHP (tanpa beda huruf besar/kecil), cukup saring per kota di SQL.
                    ->orWhere(fn ($q) => $q->whereIn('city', (array) $kotaNama)->whereNull('village_id')->whereNull('kode_kelurahan'));
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'warehouse', 'village_id', 'kode_kelurahan', 'city', 'district', 'village']);

        $kunciNama = fn ($kota, $kec, $kel) => mb_strtolower(trim($kota).'|'.trim($kec).'|'.trim($kel));

        return function (object $k) use ($area, $kunciNama) {
            return $area->filter(fn ($a) => $a->village_id == $k->id
                || ($k->kode && $a->kode_kelurahan === $k->kode)
                || ($a->village_id === null && $a->kode_kelurahan === null
                    && $kunciNama($a->city, $a->district, $a->village) === $kunciNama($k->kota_kabupaten, $k->kecamatan, $k->nama_kelurahan)))
                ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'warehouse' => $a->warehouse])
                ->values()->all();
        };
    }
}
