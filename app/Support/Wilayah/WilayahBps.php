<?php

namespace App\Support\Wilayah;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

// Klien API "Kode Relasi BPS–Kemendagri" (sig.bps.go.id/rest-bridging/getwilayah).
// Satu permintaan = semua anak satu wilayah BPS: ?level=kabupaten|kecamatan|desa&parent=<kode_bps>.
// Tiap baris memuat kode_bps, nama_bps, kode_dagri, nama_dagri; yang dipakai Enesis adalah kode & nama Kemendagri.
class WilayahBps
{
    public const LEVEL = ['provinsi', 'kabupaten', 'kecamatan', 'desa'];

    /** Panjang kode Kemendagri tanpa titik per level. */
    public const PANJANG_KODE = ['provinsi' => 2, 'kabupaten' => 4, 'kecamatan' => 6, 'desa' => 10];

    public bool $segarkan = false;

    /**
     * Anak dari banyak induk sekaligus (permintaan paralel, jawaban disimpan di cache berkas).
     *
     * @param  list<string|null>  $induk  kode BPS induk; null untuk level provinsi
     * @return array<string, list<array{kode: string, nama: string, kode_bps: string, nama_bps: string}>|null>
     *                                                                                                         kunci = kode BPS induk ('' untuk provinsi); null bila gagal diambil
     */
    public function anak(string $level, array $induk, ?callable $kemajuan = null): array
    {
        if (! in_array($level, self::LEVEL, true)) {
            throw new RuntimeException("Level wilayah tidak dikenal: {$level}");
        }

        $hasil = [];
        $perlu = [];
        foreach ($induk as $kode) {
            $kode = (string) $kode;
            $cache = $this->bacaCache($level, $kode);
            if ($cache !== null) {
                $hasil[$kode] = $this->baris($level, $cache);
                $kemajuan && $kemajuan();
            } else {
                $perlu[] = $kode;
            }
        }

        foreach (array_chunk($perlu, max(1, (int) config('wilayah_resmi.bps.paralel', 4))) as $potongan) {
            try {
                $jawaban = Http::pool(fn (Pool $pool) => array_map(
                    fn ($kode) => $pool->as($kode)->timeout(config('wilayah_resmi.bps.timeout', 60))
                        ->acceptJson()->retry(3, 2000, throw: false)->get(config('wilayah_resmi.bps.url'), $this->parameter($level, $kode)),
                    $potongan,
                ));
            } catch (Throwable) {
                $jawaban = [];
            }

            foreach ($potongan as $kode) {
                $res = $jawaban[$kode] ?? null;
                $data = $res instanceof Response && $res->successful() ? $this->isi($res) : null;
                if ($data !== null) {
                    $this->tulisCache($level, $kode, $data);
                    $hasil[$kode] = $this->baris($level, $data);
                } else {
                    $hasil[$kode] = null;
                }
                $kemajuan && $kemajuan();
            }
        }

        return $hasil;
    }

    public function parameter(string $level, string $induk): array
    {
        return array_filter([
            'level' => $level,
            'parent' => $level === 'provinsi' ? null : $induk,
            'periode_merge' => config('wilayah_resmi.bps.periode'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Isi JSON jawaban: array baris, atau {"data": [...]}. Null bila bukan JSON yang dikenal. */
    private function isi(Response $res): ?array
    {
        $json = $res->json();
        if (is_array($json) && array_key_exists('data', $json) && is_array($json['data'])) {
            $json = $json['data'];
        }

        return is_array($json) && array_is_list($json) ? $json : null;
    }

    /**
     * Baris jawaban → kode/nama Kemendagri. Baris tanpa kode_dagri (wilayah BPS tanpa padanan) dilewati,
     * kode_dagri kembar (beberapa wilayah BPS → satu wilayah Kemendagri) diambil sekali.
     */
    public function baris(string $level, array $data): array
    {
        $panjang = self::PANJANG_KODE[$level];
        $hasil = [];
        foreach ($data as $r) {
            if (! is_array($r)) {
                continue;
            }
            $kode = preg_replace('/\D/', '', (string) ($r['kode_dagri'] ?? ''));
            $nama = trim(html_entity_decode((string) ($r['nama_dagri'] ?? ''), ENT_QUOTES | ENT_HTML5));
            if (strlen($kode) !== $panjang || $nama === '' || isset($hasil[$kode])) {
                continue;
            }
            $hasil[$kode] = [
                'kode' => $kode,
                'nama' => preg_replace('/\s+/', ' ', $nama),
                'kode_bps' => preg_replace('/\D/', '', (string) ($r['kode_bps'] ?? '')),
                'nama_bps' => trim((string) ($r['nama_bps'] ?? '')),
            ];
        }

        return array_values($hasil);
    }

    private function berkasCache(string $level, string $induk): string
    {
        $periode = preg_replace('/[^\w.-]/', '_', (string) (config('wilayah_resmi.bps.periode') ?: 'terbaru'));

        return rtrim(config('wilayah_resmi.bps.cache_dir'), '/')."/{$periode}/{$level}-".($induk === '' ? 'semua' : $induk).'.json';
    }

    private function bacaCache(string $level, string $induk): ?array
    {
        if ($this->segarkan) {
            return null;
        }
        $berkas = $this->berkasCache($level, $induk);
        if (! is_file($berkas)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($berkas), true);

        return is_array($data) ? $data : null;
    }

    private function tulisCache(string $level, string $induk, array $data): void
    {
        $berkas = $this->berkasCache($level, $induk);
        if (! is_dir(dirname($berkas))) {
            mkdir(dirname($berkas), 0775, true);
        }
        file_put_contents($berkas, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
