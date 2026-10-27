<?php

namespace App\Support\Area;

use App\Support\Wilayah\Geometri;
use RuntimeException;

// Auto Grid: membagi satu kota/kecamatan menjadi kotak berukuran tetap, dipotong batas kelurahan resmi.
// Tiga mode:
// - "kelurahan": kotak dipotong garis batas kelurahan, jadi 1 area = bagian 1 kelurahan dalam 1 kotak.
// - "kotak": kotak utuh (hanya dipotong batas wilayah terpilih); kelurahan utama = bagian terluas.
// - "segienam": segi enam utuh (seperti area lama Marketama "Generate Polygon V2"), dipotong batas wilayah.
//   Ukuran = jarak sisi ke sisi; segi enam "flat-top", kolom ganjil turun setengah baris.
// Pecahan kecil (< ambang × luas kotak) digabung ke kotak tetangga (atas/bawah/kiri/kanan) sehingga
// gabungannya tetap persegi panjang 1×2 dan bisa dipotong ulang dengan rapi.
// Hasil deterministik untuk input yang sama, jadi simpan cukup mengirim kunci unit yang dipilih.
class GridArea
{
    public const MAKS_SEL = 8000;

    public const MODE = ['kelurahan', 'kotak', 'segienam'];

    private float $x0;

    private float $y0;

    private float $dx;

    private float $dy;

    /** @var array<int, array{id: int, kode: string, nama: string, kecamatan: string, multi: array}> */
    private array $kel;

    /**
     * @param  list<array{id: int, kode: string, nama: string, kecamatan: string, multi: array}>  $kelurahan
     * @return array{baris: int, kolom: int, unit: list<array>}
     */
    public function buat(array $kelurahan, float $ukuranKm, string $mode, float $ambang = 0.25): array
    {
        if (! in_array($mode, self::MODE, true)) {
            throw new RuntimeException('Mode grid tidak dikenal: '.$mode);
        }
        $this->kel = array_values($kelurahan);

        $batas = null;
        foreach ($this->kel as $k) {
            $b = Geometri::kotak($k['multi']);
            if ($b) {
                $batas = $batas ? [min($batas[0], $b[0]), min($batas[1], $b[1]), max($batas[2], $b[2]), max($batas[3], $b[3])] : $b;
            }
        }
        if (! $batas) {
            return ['baris' => 0, 'kolom' => 0, 'unit' => []];
        }
        if ($mode === 'segienam') {
            return $this->segiEnam($batas, $ukuranKm, $ambang);
        }

        // Grid berawal dari pojok kiri atas (barat laut): baris A di utara, kolom 1 di barat.
        $this->dy = $ukuranKm * 1000 / 111320;
        $this->dx = $ukuranKm * 1000 / (111320 * cos(deg2rad(($batas[1] + $batas[3]) / 2)));
        $this->x0 = $batas[0];
        $this->y0 = $batas[3];
        $baris = max(1, (int) ceil(($batas[3] - $batas[1]) / $this->dy));
        $kolom = max(1, (int) ceil(($batas[2] - $batas[0]) / $this->dx));
        if ($baris * $kolom > self::MAKS_SEL) {
            throw new RuntimeException(sprintf(
                'Grid %s km terlalu rapat untuk wilayah ini (%s kotak). Perbesar ukuran atau pilih satu kecamatan.',
                $ukuranKm, number_format($baris * $kolom, 0, ',', '.'),
            ));
        }

        // 1. Potong tiap kelurahan dengan kotak-kotak yang dilewatinya.
        $potong = []; // [r][c][indeks kelurahan] = [multi, luas]
        foreach ($this->kel as $i => $k) {
            $b = Geometri::kotak($k['multi']);
            if (! $b) {
                continue;
            }
            [$c1, $c2] = [max(0, (int) floor(($b[0] - $this->x0) / $this->dx)), min($kolom - 1, (int) floor(($b[2] - $this->x0) / $this->dx))];
            [$r1, $r2] = [max(0, (int) floor(($this->y0 - $b[3]) / $this->dy)), min($baris - 1, (int) floor(($this->y0 - $b[1]) / $this->dy))];
            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    $bagian = $this->potongKelurahan($i, $r, $c, $r, $c);
                    if ($bagian) {
                        $potong[$r][$c][$i] = $bagian;
                    }
                }
            }
        }

        // 2. Satuan area sesuai mode.
        $unit = [];
        foreach ($potong as $r => $perKolom) {
            foreach ($perKolom as $c => $perKel) {
                if ($mode === 'kotak') {
                    $unit["$r.$c"] = ['r' => $r, 'c' => $c, 'r2' => $r, 'c2' => $c, 'kel' => array_keys($perKel), 'bagian' => $perKel];
                } else {
                    foreach ($perKel as $i => $bagian) {
                        $unit["$r.$c@$i"] = ['r' => $r, 'c' => $c, 'r2' => $r, 'c2' => $c, 'kel' => [$i], 'bagian' => [$i => $bagian]];
                    }
                }
            }
        }

        // 3. Gabungkan pecahan kecil ke tetangga yang masih satu kotak dan bukan pecahan kecil.
        // Mode kelurahan: ukuran pembanding = yang lebih kecil dari luas kotak dan luas kelurahannya, supaya
        // kelurahan yang lebih kecil dari kotak tidak dianggap pecahan (yang digabung hanya serpihannya).
        $luasKotak = $ukuranKm * $ukuranKm;
        $luasKel = array_map(fn ($k) => Geometri::luasKm2($k['multi']), $this->kel);
        $luas = fn ($u) => array_sum(array_column($u['bagian'], 1));
        $kecil = array_filter($unit, fn ($u) => $luas($u) < $ambang * ($mode === 'kelurahan' ? min($luasKotak, $luasKel[$u['kel'][0]]) : $luasKotak));
        uasort($kecil, fn ($a, $b) => $luas($a) <=> $luas($b));

        foreach (array_keys($kecil) as $kunci) {
            if (! isset($unit[$kunci])) {
                continue;
            }
            $u = $unit[$kunci];
            $akhiran = $mode === 'kelurahan' ? '@'.$u['kel'][0] : '';
            $terbaik = null;
            foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
                $kt = ($u['r'] + $dr).'.'.($u['c'] + $dc).$akhiran;
                $t = $unit[$kt] ?? null;
                if (! $t || $t['r'] !== $t['r2'] || $t['c'] !== $t['c2'] || isset($kecil[$kt]) || ! empty($t['gabungan'])) {
                    continue;
                }
                if (! $terbaik || $luas($t) > $luas($unit[$terbaik])) {
                    $terbaik = $kt;
                }
            }
            if (! $terbaik) {
                continue;
            }

            $t = $unit[$terbaik];
            $baru = [
                'r' => min($u['r'], $t['r']), 'c' => min($u['c'], $t['c']),
                'r2' => max($u['r'], $t['r']), 'c2' => max($u['c'], $t['c']),
                'kel' => array_values(array_unique([...$t['kel'], ...$u['kel']])),
                'gabungan' => true,
            ];
            $baru['bagian'] = [];
            foreach ($baru['kel'] as $i) {
                $bagian = $this->potongKelurahan($i, $baru['r'], $baru['c'], $baru['r2'], $baru['c2']);
                if ($bagian) {
                    $baru['bagian'][$i] = $bagian;
                }
            }
            unset($unit[$kunci], $unit[$terbaik]);
            $unit["{$baru['r']}.{$baru['c']}~{$baru['r2']}.{$baru['c2']}$akhiran"] = $baru;
        }

        // 4. Bentuk keluaran.
        $hasil = [];
        foreach ($unit as $kunci => $u) {
            uasort($u['bagian'], fn ($a, $b) => $b[1] <=> $a[1]);
            $multi = array_merge(...array_column($u['bagian'], 0));
            $label = self::labelKotak($u['r'], $u['c']).($u['r2'] !== $u['r'] || $u['c2'] !== $u['c'] ? '+'.self::labelKotak($u['r2'], $u['c2']) : '');
            $daftar = [];
            foreach ($u['bagian'] as $i => [, $l]) {
                $k = $this->kel[$i];
                $daftar[] = ['id' => $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'kecamatan' => $k['kecamatan'], 'luas_km2' => round($l, 3)];
            }
            $hasil[] = [
                'kunci' => $mode === 'kelurahan' ? preg_replace('/@(\d+)$/', '@'.$daftar[0]['id'], $kunci) : $kunci,
                'label' => $label,
                'baris' => $u['r'],
                'kolom' => $u['c'],
                'nama' => $daftar[0]['nama'].' ('.$label.')',
                'kelurahan' => $daftar,
                'geometri' => $multi,
                'luas_km2' => round($luas($u), 3),
                'titik' => Geometri::titikDalam($multi),
            ];
        }
        usort($hasil, fn ($a, $b) => [$a['baris'], $a['kolom'], $a['kunci']] <=> [$b['baris'], $b['kolom'], $b['kunci']]);

        return ['baris' => $baris, 'kolom' => $kolom, 'unit' => $hasil];
    }

    /**
     * Grid segi enam. Tiap kelurahan dipotong segi enam yang dilewatinya; satu unit = satu segi enam (semua bagian
     * kelurahan di dalamnya). Pecahan < ambang × luas segi enam digabung ke tetangga terluas (6 arah).
     */
    private function segiEnam(array $batas, float $ukuranKm, float $ambang): array
    {
        $jariKm = $ukuranKm / sqrt(3); // jari-jari (pusat ke sudut) = panjang sisi
        $derajatX = 1000 / (111320 * cos(deg2rad(($batas[1] + $batas[3]) / 2)));
        $derajatY = 1000 / 111320;
        $rx = $jariKm * $derajatX;
        $ry = $jariKm * $derajatY;
        $langkahX = 1.5 * $rx;           // jarak antar kolom
        $langkahY = $ukuranKm * $derajatY; // jarak antar baris (sisi ke sisi)
        $x0 = $batas[0];
        $y0 = $batas[3];

        $kolom = max(1, (int) ceil(($batas[2] - $batas[0]) / $langkahX) + 1);
        $baris = max(1, (int) ceil(($batas[3] - $batas[1]) / $langkahY) + 1);
        if ($baris * $kolom > self::MAKS_SEL) {
            throw new RuntimeException(sprintf(
                'Grid %s km terlalu rapat untuk wilayah ini (%s segi enam). Perbesar ukuran atau pilih satu kecamatan.',
                $ukuranKm, number_format($baris * $kolom, 0, ',', '.'),
            ));
        }

        $pusat = fn (int $r, int $c) => [$x0 + $c * $langkahX, $y0 - ($r + ($c % 2) / 2) * $langkahY];
        $segiEnam = function (int $r, int $c) use ($pusat, $rx, $ry) {
            [$cx, $cy] = $pusat($r, $c);
            $titik = [];
            for ($k = 0; $k < 6; $k++) {
                $sudut = deg2rad(60 * $k);
                $titik[] = [$cx + $rx * cos($sudut), $cy + $ry * sin($sudut)];
            }

            return $titik;
        };

        // 1. Potong tiap kelurahan dengan segi enam yang kotak batasnya beririsan.
        $unit = [];
        foreach ($this->kel as $i => $k) {
            $b = Geometri::kotak($k['multi']);
            if (! $b) {
                continue;
            }
            $c1 = max(0, (int) floor(($b[0] - $x0) / $langkahX) - 1);
            $c2 = min($kolom - 1, (int) ceil(($b[2] - $x0) / $langkahX) + 1);
            $r1 = max(0, (int) floor(($y0 - $b[3]) / $langkahY) - 1);
            $r2 = min($baris - 1, (int) ceil(($y0 - $b[1]) / $langkahY) + 1);
            for ($c = $c1; $c <= $c2; $c++) {
                for ($r = $r1; $r <= $r2; $r++) {
                    $multi = Geometri::potongCembung($k['multi'], $segiEnam($r, $c));
                    $luas = $multi ? Geometri::luasKm2($multi) : 0.0;
                    if ($luas >= 0.0001) {
                        $unit["$r.$c"] ??= ['r' => $r, 'c' => $c, 'sel' => [[$r, $c]], 'bagian' => []];
                        $unit["$r.$c"]['bagian'][$i][] = [$multi, $luas];
                    }
                }
            }
        }

        // 2. Gabungkan pecahan kecil ke tetangga terluas yang bukan pecahan kecil dan belum digabung.
        $luasSel = 1.5 * sqrt(3) * $jariKm * $jariKm;
        $luas = fn ($u) => array_sum(array_map(fn ($daftar) => array_sum(array_column($daftar, 1)), $u['bagian']));
        $kecil = array_filter($unit, fn ($u) => $luas($u) < $ambang * $luasSel);
        uasort($kecil, fn ($a, $b) => $luas($a) <=> $luas($b));
        $tetangga = fn (int $r, int $c) => $c % 2
            ? [[$r - 1, $c], [$r + 1, $c], [$r, $c - 1], [$r, $c + 1], [$r + 1, $c - 1], [$r + 1, $c + 1]]
            : [[$r - 1, $c], [$r + 1, $c], [$r - 1, $c - 1], [$r - 1, $c + 1], [$r, $c - 1], [$r, $c + 1]];

        foreach (array_keys($kecil) as $kunci) {
            if (! isset($unit[$kunci])) {
                continue;
            }
            $u = $unit[$kunci];
            $terbaik = null;
            foreach ($tetangga($u['r'], $u['c']) as [$tr, $tc]) {
                $kt = "$tr.$tc";
                $t = $unit[$kt] ?? null;
                if (! $t || isset($kecil[$kt]) || count($t['sel']) > 1) {
                    continue;
                }
                if (! $terbaik || $luas($t) > $luas($unit[$terbaik])) {
                    $terbaik = $kt;
                }
            }
            if (! $terbaik) {
                continue;
            }
            $t = $unit[$terbaik];
            $sel = [...$t['sel'], ...$u['sel']];
            usort($sel, fn ($a, $b) => $a <=> $b);
            $baru = ['r' => $sel[0][0], 'c' => $sel[0][1], 'sel' => $sel, 'bagian' => $t['bagian']];
            foreach ($u['bagian'] as $i => $daftar) {
                $baru['bagian'][$i] = [...($baru['bagian'][$i] ?? []), ...$daftar];
            }
            unset($unit[$kunci], $unit[$terbaik]);
            $unit[implode('+', array_map(fn ($s) => $s[0].'.'.$s[1], $sel))] = $baru;
        }

        // 3. Bentuk keluaran (sama dengan mode kotak).
        $hasil = [];
        foreach ($unit as $kunci => $u) {
            $perKel = array_map(fn ($daftar) => [array_merge(...array_column($daftar, 0)), array_sum(array_column($daftar, 1))], $u['bagian']);
            uasort($perKel, fn ($a, $b) => $b[1] <=> $a[1]);
            $multi = array_merge(...array_column($perKel, 0));
            $label = implode('+', array_map(fn ($s) => self::labelKotak($s[0], $s[1]), $u['sel']));
            $daftar = [];
            foreach ($perKel as $i => [, $l]) {
                $k = $this->kel[$i];
                $daftar[] = ['id' => $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'kecamatan' => $k['kecamatan'], 'luas_km2' => round($l, 3)];
            }
            $hasil[] = [
                'kunci' => 'h'.$kunci,
                'label' => $label,
                'baris' => $u['r'],
                'kolom' => $u['c'],
                'nama' => $daftar[0]['nama'].' ('.$label.')',
                'kelurahan' => $daftar,
                'geometri' => $multi,
                'luas_km2' => round($luas($u), 3),
                'titik' => Geometri::titikDalam($multi),
            ];
        }
        usort($hasil, fn ($a, $b) => [$a['baris'], $a['kolom'], $a['kunci']] <=> [$b['baris'], $b['kolom'], $b['kunci']]);

        return ['baris' => $baris, 'kolom' => $kolom, 'unit' => $hasil];
    }

    /** A..Z, AA, AB, ... (seperti kolom spreadsheet) + nomor kolom mulai 1, mis. "A25", "AB3". */
    public static function labelKotak(int $baris, int $kolom): string
    {
        $huruf = '';
        for ($n = $baris + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $huruf = chr(65 + ($n - 1) % 26).$huruf;
        }

        return $huruf.($kolom + 1);
    }

    /** @return array{0: array, 1: float}|null bagian kelurahan $i di dalam kotak r..r2, c..c2 beserta luasnya */
    private function potongKelurahan(int $i, int $r, int $c, int $r2, int $c2): ?array
    {
        $multi = Geometri::potongPersegi(
            $this->kel[$i]['multi'],
            $this->x0 + $c * $this->dx, $this->y0 - ($r2 + 1) * $this->dy,
            $this->x0 + ($c2 + 1) * $this->dx, $this->y0 - $r * $this->dy,
        );
        $luas = $multi ? Geometri::luasKm2($multi) : 0.0;

        // Kurang dari 100 m² dianggap sisa garis batas, bukan wilayah.
        return $luas >= 0.0001 ? [$multi, $luas] : null;
    }
}
