<?php

namespace App\Support\Wilayah;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

// Batas kelurahan/desa dari layanan peta resmi Badan Informasi Geospasial (BIG):
// geoservices.big.go.id → BATASWILAYAH/Administrasi_AR_KelDesa_10K, layer 0 "Batas Wilayah Kelurahan/Desa".
// Kolom yang dipakai: KDEPUM (kode Kemendagri desa), WADMKD (nama desa), KDPKAB (kode kabupaten/kota).
// Satu desa bisa terdiri dari beberapa fitur (pulau terpisah) → digabung jadi satu MultiPolygon per kode.
class BatasBig
{
    public const SUMBER = 'big-keldesa-10k';

    private const MAKS_HALAMAN = 200;

    /**
     * @param  string  $kodeKota  4 digit, mis. "3201"
     * @return list<array{kode: string, tingkat: string, nama: string, lat: null, lng: null, multi: array}>
     */
    public function kelurahanKota(string $kodeKota): array
    {
        $kodeKota = preg_replace('/\D/', '', $kodeKota);
        $titik = substr($kodeKota, 0, 2).'.'.substr($kodeKota, 2, 2);
        $per = max(1, (int) config('batas_wilayah.big.halaman', 100));

        $fitur = [];
        for ($hal = 0; $hal < self::MAKS_HALAMAN; $hal++) {
            $json = $this->minta([
                // Kode BIG ditulis bertitik (32.01); bentuk tanpa titik ikut dicari untuk berjaga-jaga.
                'where' => "KDPKAB='{$titik}' OR KDPKAB='{$kodeKota}'",
                'outFields' => 'KDEPUM,WADMKD',
                'returnGeometry' => 'true',
                'outSR' => '4326',
                'geometryPrecision' => '6',
                'orderByFields' => 'FID',
                'resultOffset' => $hal * $per,
                'resultRecordCount' => $per,
                'maxAllowableOffset' => config('batas_wilayah.big.sederhanakan'),
                'f' => 'geojson',
            ]);

            $baru = $json['features'] ?? [];
            array_push($fitur, ...$baru);
            $lebih = ($json['exceededTransferLimit'] ?? $json['properties']['exceededTransferLimit'] ?? false) === true;
            if (! $baru || (! $lebih && count($baru) < $per)) {
                break;
            }
        }

        return $this->gabung($fitur, $kodeKota);
    }

    /** Fitur GeoJSON → satu baris per kode desa (MultiPolygon gabungan semua fiturnya). */
    public function gabung(array $fitur, string $kodeKota): array
    {
        $hasil = [];
        foreach ($fitur as $f) {
            $prop = array_change_key_case($f['properties'] ?? [], CASE_UPPER);
            $kode = preg_replace('/\D/', '', (string) ($prop['KDEPUM'] ?? ''));
            if (strlen($kode) !== 10 || ! str_starts_with($kode, $kodeKota)) {
                continue;
            }
            $multi = $this->bulatkan(Geometri::multi($f['geometry'] ?? []));
            if (! $multi) {
                continue;
            }
            $hasil[$kode] ??= [
                'kode' => $kode,
                'tingkat' => 'kelurahan',
                'nama' => NamaWilayah::rapikan((string) ($prop['WADMKD'] ?? $kode)),
                'lat' => null,
                'lng' => null,
                'multi' => [],
            ];
            array_push($hasil[$kode]['multi'], ...$multi);
        }
        ksort($hasil, SORT_STRING);

        return array_values($hasil);
    }

    /** Koordinat [lng, lat] 6 desimal, cincin ditutup, cincin < 4 titik dibuang. */
    private function bulatkan(array $multi): array
    {
        $hasil = [];
        foreach ($multi as $polygon) {
            $cincin = [];
            foreach ((array) $polygon as $ring) {
                $titik = array_values(array_map(fn ($p) => [round((float) $p[0], 6), round((float) $p[1], 6)], array_filter((array) $ring, fn ($p) => is_array($p) && count($p) >= 2)));
                if ($titik && $titik[0] !== end($titik)) {
                    $titik[] = $titik[0];
                }
                if (count($titik) >= 4) {
                    $cincin[] = $titik;
                }
            }
            if ($cincin) {
                $hasil[] = $cincin;
            }
        }

        return $hasil;
    }

    private function minta(array $parameter): array
    {
        $parameter = array_filter($parameter, fn ($v) => $v !== null && $v !== '');
        try {
            $res = Http::timeout(config('batas_wilayah.timeout', 60))->acceptJson()
                ->retry(3, 2000, throw: false)->asForm()->post(config('batas_wilayah.big.url'), $parameter);
        } catch (Throwable $e) {
            throw new RuntimeException('Tidak bisa menghubungi layanan batas wilayah BIG: '.$e->getMessage(), 0, $e);
        }
        if (! $res->successful()) {
            throw new RuntimeException("Layanan batas wilayah BIG menjawab HTTP {$res->status()}.");
        }
        $json = $res->json();
        if (! is_array($json)) {
            throw new RuntimeException('Jawaban layanan batas wilayah BIG bukan JSON.');
        }
        // ArcGIS melaporkan galat sebagai HTTP 200 + {"error": {...}}.
        if (isset($json['error'])) {
            throw new RuntimeException('Layanan batas wilayah BIG: '.($json['error']['message'] ?? 'galat tidak dikenal'));
        }

        return $json;
    }
}
