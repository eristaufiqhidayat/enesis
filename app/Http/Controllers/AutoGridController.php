<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Area\AreaTerpasang;
use App\Support\Area\GridArea;
use App\Support\Area\KodeArea;
use App\Support\Area\WilayahKota;
use App\Support\Wilayah\Geometri;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

// Auto Generate Polygon Area (Auto Grid): wilayah satu kota/kecamatan dibagi kotak 1–15 km yang dipotong batas
// kelurahan resmi di server. Berbeda dengan Marketama (batas dari Nominatim, kelurahan ditebak dengan reverse
// geocode per kotak dari browser), kelurahan/kecamatan tiap kotak sudah pasti dari batas_wilayah.
class AutoGridController extends Controller
{
    public const UKURAN = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

    public function index(Request $request)
    {
        return Inertia::render('Area/AutoGrid', [
            'slug' => 'master_area',
            'title' => 'Auto Grid Polygon Area',
            'provinsi' => DB::table('provinsi')->orderBy('nama_provinsi')->get(['id', 'kode', 'nama_provinsi as nama']),
            'depo' => DB::table('area_depo')->orderBy('name')->get(['warehouse_id', 'name', 'province', 'lat', 'lng']),
            'wilayahDepo' => Schema::hasTable('mk_wilayah_depo')
                ? DB::table('mk_wilayah_depo')->orderBy('nama')->get(['id', 'nama', 'depo_id'])
                : [],
            'ukuran' => self::UKURAN,
            'bisaTambah' => Akses::bisa($request->user(), 'master_area', 'tambah'),
            'bisaUbah' => Akses::bisa($request->user(), 'master_area', 'ubah'),
            'bisaHapus' => Akses::bisa($request->user(), 'master_area', 'hapus'),
            'kembaliUrl' => route('design-area.index'),
            'designUrl' => route('design-area.index'),
            'hapusUrl' => url('master_area/design_area/areas'),
            'kelurahanUrl' => route('kelurahan-polygon.index'),
            'opsiUrl' => route('kelurahan-polygon.opsi'),
            'pratinjauUrl' => route('auto-grid.pratinjau'),
            'simpanUrl' => route('auto-grid.simpan'),
        ]);
    }

    public function pratinjau(Request $request, WilayahKota $wilayah)
    {
        $data = $this->validasiGrid($request);
        [$kota, $kecamatan] = $this->wilayah($data);

        $peringatan = $wilayah->siapkanBatas($kota);
        try {
            [$grid, $kelurahan, $tanpaBatas] = $this->bangun($wilayah, $kota, $kecamatan, $data);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['ukuran' => $e->getMessage()]);
        }
        if ($tanpaBatas) {
            $peringatan = trim(($peringatan ? $peringatan.' ' : '').$tanpaBatas.' kelurahan tanpa batas resmi tidak ikut di grid (gambar manual di Design Area).');
        }

        $terpasang = $grid['unit'] ? new AreaTerpasang($this->kotakUnit($grid['unit'])) : null;
        $unit = array_map(fn ($u) => [
            'kunci' => $u['kunci'],
            'label' => $u['label'],
            'nama' => $u['nama'],
            'kelurahan' => $u['kelurahan'],
            'geometri' => $u['geometri'],
            'luas_km2' => $u['luas_km2'],
            'titik' => $u['titik'],
            'area' => $terpasang?->di($u['titik']),
        ], $grid['unit']);

        return response()->json([
            'kota' => $kota->nama_kota_kabupaten,
            'kecamatan' => $kecamatan?->nama_kecamatan,
            'baris' => $grid['baris'],
            'kolom' => $grid['kolom'],
            'jumlah_kelurahan' => $kelurahan->count(),
            'unit' => $unit,
            'peringatan' => $peringatan,
        ]);
    }

    /** Grid dibangun ulang di server dengan parameter yang sama, lalu hanya kunci terpilih yang disimpan. */
    public function simpan(Request $request, WilayahKota $wilayah)
    {
        $data = $this->validasiGrid($request) + $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:area_depo,warehouse_id'],
            'wilayah_depo_id' => ['nullable', 'integer'],
            'kunci' => ['required', 'array', 'min:1', 'max:'.GridArea::MAKS_SEL],
            'kunci.*' => ['string', 'distinct'],
        ], [], ['warehouse_id' => 'depo', 'kunci' => 'kotak']);
        [$kota, $kecamatan] = $this->wilayah($data);

        $depo = DB::table('area_depo')->where('warehouse_id', $data['warehouse_id'])->first();
        $wilayahDepo = ! empty($data['wilayah_depo_id']) && Schema::hasTable('mk_wilayah_depo')
            ? DB::table('mk_wilayah_depo')->where('id', $data['wilayah_depo_id'])->first()
            : null;

        try {
            [$grid, $kelurahan] = $this->bangun($wilayah, $kota, $kecamatan, $data);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['ukuran' => $e->getMessage()]);
        }
        $perKunci = collect($grid['unit'])->keyBy('kunci');
        $hilang = array_values(array_diff($data['kunci'], $perKunci->keys()->all()));
        if ($hilang) {
            throw ValidationException::withMessages(['kunci' => 'Grid sudah berubah (batas wilayah diperbarui). Klik Generate lagi lalu simpan.']);
        }

        $kelPerId = $kelurahan->keyBy('id');
        $idWilayah = $wilayah->idWilayah($kelurahan);

        $hasil = DB::transaction(function () use ($data, $perKunci, $kelPerId, $idWilayah, $depo, $wilayahDepo) {
            $unit = collect($data['kunci'])->map(fn ($k) => $perKunci[$k]);
            $terpasang = new AreaTerpasang($this->kotakUnit($unit->all()));

            $dilewati = [];
            $baru = [];
            foreach ($unit as $u) {
                if ($ada = $terpasang->di($u['titik'])) {
                    $dilewati[] = ['kunci' => $u['kunci'], 'nama' => $u['nama'], 'alasan' => 'Tumpang tindih dengan area '.$ada['code'].($ada['warehouse'] ? ' ('.$ada['warehouse'].')' : '')];
                } else {
                    $baru[] = $u;
                }
            }
            if (! $baru) {
                return ['dibuat' => 0, 'kode' => [], 'dilewati' => $dilewati];
            }

            $kode = KodeArea::berikutnya(count($baru));
            $baris = [];
            foreach ($baru as $i => $u) {
                $utama = $kelPerId[$u['kelurahan'][0]['id']];
                $baris[] = [
                    'code' => $kode[$i],
                    'name' => mb_substr($u['nama'], 0, 255),
                    'polygon' => json_encode(Geometri::cincinTerbesar($u['geometri'])),
                    'geometri' => json_encode(['type' => 'MultiPolygon', 'coordinates' => $u['geometri']]),
                    'warehouse' => $depo->name,
                    'warehouse_id' => $depo->warehouse_id,
                    'branch_area' => $wilayahDepo?->nama,
                    'branch_area_id' => $wilayahDepo?->id,
                    ...WilayahKota::kolomWilayah($utama, $idWilayah),
                    'sumber_polygon' => 'grid-'.$data['ukuran'].'km-'.$data['mode'],
                    'info' => $this->info($u, $data),
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

    private function validasiGrid(Request $request): array
    {
        return $request->validate([
            'kota_id' => ['required', 'integer'],
            'kecamatan_id' => ['nullable', 'integer'],
            'ukuran' => ['required', 'integer', 'in:'.implode(',', self::UKURAN)],
            'mode' => ['required', 'in:'.implode(',', GridArea::MODE)],
        ]);
    }

    private function wilayah(array $data): array
    {
        $kota = DB::table('kota_kabupaten')->find($data['kota_id']);
        abort_unless($kota, 404);
        $kecamatan = ! empty($data['kecamatan_id']) ? DB::table('kecamatan')->find($data['kecamatan_id']) : null;

        return [$kota, $kecamatan];
    }

    /** @return array{0: array, 1: Collection, 2: int} grid, kelurahan berbatas, jumlah kelurahan tanpa batas */
    private function bangun(WilayahKota $wilayah, object $kota, ?object $kecamatan, array $data): array
    {
        $semua = $wilayah->kelurahan($kota, $kecamatan);
        $batas = $wilayah->batasPerKode($semua);
        $kelurahan = $semua->filter(fn ($k) => $k->kode && $batas->has($k->kode))->values();

        $grid = (new GridArea)->buat($kelurahan->map(fn ($k) => [
            'id' => $k->id,
            'kode' => $k->kode,
            'nama' => $k->nama_kelurahan,
            'kecamatan' => $k->kecamatan,
            'multi' => json_decode($batas[$k->kode]->geojson, true)['coordinates'],
        ])->all(), (float) $data['ukuran'], $data['mode']);

        return [$grid, $kelurahan, $semua->count() - $kelurahan->count()];
    }

    private function kotakUnit(array $unit): array
    {
        $b = [INF, INF, -INF, -INF];
        foreach ($unit as $u) {
            $k = Geometri::kotak($u['geometri']);
            if ($k) {
                $b = [min($b[0], $k[0]), min($b[1], $k[1]), max($b[2], $k[2]), max($b[3], $k[3])];
            }
        }

        return $b;
    }

    /** Keterangan: ukuran grid + kelurahan yang dicakup (mode kotak: tiga terluas beserta persentasenya). */
    private function info(array $u, array $data): string
    {
        $info = ($data['mode'] === 'segienam' ? 'Segi enam ' : 'Grid ').$data['ukuran'].' km '.$u['label'];
        if ($data['mode'] === 'kelurahan' || count($u['kelurahan']) === 1) {
            $k = $u['kelurahan'][0];

            return $info.' · Kel. '.$k['nama'].', Kec. '.$k['kecamatan'];
        }

        $total = max(0.0001, $u['luas_km2']);
        $daftar = array_map(
            fn ($k) => 'Kel. '.$k['nama'].' '.round($k['luas_km2'] / $total * 100).'%',
            array_slice($u['kelurahan'], 0, 3),
        );
        $sisa = count($u['kelurahan']) - 3;

        return $info.' · '.implode(', ', $daftar).($sisa > 0 ? " +{$sisa} lainnya" : '');
    }
}
