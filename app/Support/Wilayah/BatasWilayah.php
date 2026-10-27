<?php

namespace App\Support\Wilayah;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

// Batas wilayah resmi per kode Kemendagri. Sumber utama (config batas_wilayah.sumber): layanan peta BIG
// (BatasBig) atau dataset cahyadsn/wilayah_boundaries (satu berkas SQL = semua kelurahan/desa satu kota,
// kel/31/wilayah_boundaries_kel_31.71.sql). Kelurahan yang tidak ada di sumber utama diisi dari sumber cadangan.
// Hasil disimpan ke batas_wilayah, jadi tiap kota cukup diambil sekali.
class BatasWilayah
{
    public const SUMBER = 'kemendagri-2025';

    public function __construct(private BatasBig $big) {}

    private const TINGKAT = [2 => 'kota', 3 => 'kecamatan', 4 => 'kelurahan'];

    /**
     * Pastikan batas semua kelurahan/desa satu kota sudah ada di batas_wilayah; bila belum, berkas
     * dataset kota itu diambil sekali (±0,1–3 MB). Mengembalikan true bila berkas baru diambil.
     * Batas tingkat kota/kecamatan di dataset jauh lebih kasar, jadi tidak dipakai di sini.
     *
     * @param  string  $kodeKota  4 digit, mis. "3171"
     */
    public function pastikanKota(string $kodeKota, bool $paksa = false): bool
    {
        $kodeKota = preg_replace('/\D/', '', $kodeKota);
        if (strlen($kodeKota) !== 4) {
            throw new RuntimeException('Kode kota/kabupaten tidak valid: '.$kodeKota);
        }

        return Cache::lock('batas-wilayah:'.$kodeKota, 300)->block(240, function () use ($kodeKota, $paksa) {
            if (! $paksa && DB::table('batas_wilayah')->where('tingkat', 'kelurahan')->where('kode', 'like', $kodeKota.'%')->exists()) {
                return false;
            }

            $utama = (string) config('batas_wilayah.sumber', self::SUMBER);
            $cadangan = (string) config('batas_wilayah.cadangan');
            $cadangan = $cadangan !== '' && $cadangan !== $utama ? $cadangan : null;

            try {
                $baris = $this->dariSumber($utama, $kodeKota);
            } catch (RuntimeException $e) {
                if (! $cadangan) {
                    throw $e;
                }
                report($e);
                $baris = [];
            }
            $tersimpan = $baris ? $this->simpan($baris, $this->labelSumber($utama)) : 0;

            // Kelurahan master yang tidak ada di sumber utama (mis. desa pemekaran baru) diisi dari cadangan.
            if ($cadangan) {
                $ada = array_flip(array_column($baris, 'kode'));
                $kurang = DB::table('kelurahan')->where('kode', 'like', $kodeKota.'%')->pluck('kode')
                    ->map(fn ($k) => preg_replace('/\D/', '', (string) $k))
                    ->filter(fn ($k) => strlen($k) === 10 && ! isset($ada[$k]))->flip();
                if (! $baris || $kurang->isNotEmpty()) {
                    try {
                        $lain = array_filter($this->dariSumber($cadangan, $kodeKota), fn ($b) => ! $baris || isset($kurang[$b['kode']]));
                        $tersimpan += $lain ? $this->simpan(array_values($lain), $this->labelSumber($cadangan)) : 0;
                    } catch (RuntimeException $e) {
                        if (! $tersimpan) {
                            throw $e;
                        }
                    }
                }
            }

            if (! $tersimpan) {
                throw new RuntimeException("Batas kelurahan kota {$kodeKota} tidak ditemukan di sumber resmi.");
            }

            return true;
        });
    }

    /** Batas kelurahan satu kota dari satu sumber. */
    public function dariSumber(string $sumber, string $kodeKota): array
    {
        if ($sumber === 'big') {
            return $this->big->kelurahanKota($kodeKota);
        }
        if ($sumber !== self::SUMBER) {
            throw new RuntimeException("Sumber batas wilayah tidak dikenal: {$sumber}");
        }

        return array_values(array_filter(
            $this->parse($this->ambil(self::berkasKelurahan($kodeKota))),
            fn ($b) => $b['tingkat'] === 'kelurahan' && str_starts_with($b['kode'], $kodeKota),
        ));
    }

    private function labelSumber(string $sumber): string
    {
        return $sumber === 'big' ? BatasBig::SUMBER : $sumber;
    }

    /**
     * Parse INSERT dataset: ('31.71.01.1001','Gambir',-6.17,106.82,'[[[lat,lng],...]]').
     *
     * @return list<array{kode: string, tingkat: string, nama: string, lat: ?float, lng: ?float, multi: array}>
     */
    public function parse(string $sql): array
    {
        $pola = "/\(\s*'([\d.]+)'\s*,\s*'((?:[^'\\\\]|\\\\.|'')*+)'\s*,\s*(-?[\d.eE+-]+|NULL)\s*,\s*(-?[\d.eE+-]+|NULL)\s*,\s*'(\[[^']*+)'\s*\)/";
        if (preg_match_all($pola, $sql, $cocok, PREG_SET_ORDER) === false) {
            throw new RuntimeException('Berkas batas wilayah tidak bisa dibaca.');
        }

        $hasil = [];
        foreach ($cocok as [, $kode, $nama, $lat, $lng, $path]) {
            $tingkat = self::TINGKAT[substr_count($kode, '.') + 1] ?? null;
            $koordinat = json_decode($path, true);
            if (! $tingkat || ! is_array($koordinat)) {
                continue;
            }
            $multi = Geometri::dariPathLatLng($koordinat);
            if (! $multi) {
                continue;
            }
            $hasil[] = [
                'kode' => str_replace('.', '', $kode),
                'tingkat' => $tingkat,
                'nama' => str_replace(["''", "\\'"], "'", $nama),
                'lat' => is_numeric($lat) ? (float) $lat : null,
                'lng' => is_numeric($lng) ? (float) $lng : null,
                'multi' => $multi,
            ];
        }

        return $hasil;
    }

    public function simpan(array $baris, string $sumber = self::SUMBER): int
    {
        $sekarang = now();
        $data = array_map(function ($b) use ($sumber, $sekarang) {
            $pusat = $b['lat'] !== null && $b['lng'] !== null ? [$b['lat'], $b['lng']] : Geometri::pusatKotak($b['multi']);

            return [
                'kode' => $b['kode'],
                'tingkat' => $b['tingkat'],
                'nama' => $b['nama'],
                'lat' => $pusat[0] ?? null,
                'lng' => $pusat[1] ?? null,
                'geojson' => json_encode(['type' => 'MultiPolygon', 'coordinates' => $b['multi']]),
                'jumlah_titik' => Geometri::jumlahTitik($b['multi']),
                'luas_km2' => round(Geometri::luasKm2($b['multi']), 4),
                'sumber' => $sumber,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }, $baris);

        DB::transaction(function () use ($data) {
            foreach (array_chunk($data, 100) as $potongan) {
                DB::table('batas_wilayah')->upsert(
                    $potongan,
                    ['kode'],
                    ['tingkat', 'nama', 'lat', 'lng', 'geojson', 'jumlah_titik', 'luas_km2', 'sumber', 'updated_at'],
                );
            }
        });

        return count($data);
    }

    /** Isi berkas dataset: dari folder lokal bila dikonfigurasi, selain itu diunduh. */
    public function ambil(string $berkas): string
    {
        $dir = config('batas_wilayah.direktori');
        if ($dir) {
            $path = rtrim($dir, '/').'/'.$berkas;
            if (! is_file($path)) {
                throw new RuntimeException("Berkas batas wilayah tidak ada: {$path}");
            }

            return file_get_contents($path);
        }

        $url = rtrim(config('batas_wilayah.url_dasar'), '/').'/'.$berkas;
        try {
            $res = Http::timeout(config('batas_wilayah.timeout', 60))->retry(2, 1000, throw: false)->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException('Tidak bisa menghubungi sumber batas wilayah: '.$e->getMessage(), 0, $e);
        }

        if ($res->status() === 404) {
            throw new RuntimeException("Batas wilayah belum tersedia di dataset ({$berkas}).");
        }
        if (! $res->successful()) {
            throw new RuntimeException("Gagal mengambil batas wilayah ({$berkas}): HTTP {$res->status()}.");
        }

        return $res->body();
    }

    public static function berkasKelurahan(string $kodeKota): string
    {
        $prov = substr($kodeKota, 0, 2);

        return "kel/{$prov}/wilayah_boundaries_kel_{$prov}.".substr($kodeKota, 2, 2).'.sql';
    }
}
