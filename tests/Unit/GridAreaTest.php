<?php

namespace Tests\Unit;

use App\Support\Area\GridArea;
use App\Support\Wilayah\Geometri;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GridAreaTest extends TestCase
{
    private static function kotak(float $x1, float $y1, float $x2, float $y2): array
    {
        return [[[[$x1, $y1], [$x2, $y1], [$x2, $y2], [$x1, $y2], [$x1, $y1]]]];
    }

    public function test_clipping_a_concave_polygon_with_a_hole_keeps_exact_area(): void
    {
        // Huruf U (lebar 3, tinggi 3, celah tengah 1×2) dengan lubang 0,2×0,2 di kaki kiri.
        $u = [[
            [[0, 0], [3, 0], [3, 3], [2, 3], [2, 1], [1, 1], [1, 3], [0, 3], [0, 0]],
            [[0.4, 1.4], [0.6, 1.4], [0.6, 1.6], [0.4, 1.6], [0.4, 1.4]],
        ]];
        // Persegi memotong kedua kaki di atas lengkungan: hasil sebenarnya dua bagian terpisah.
        $potong = Geometri::potongPersegi($u, -1, 2, 4, 4);
        $luasPenuh = Geometri::luasKm2(self::kotak(0, 2, 1, 3)) * 2;
        $this->assertEqualsWithDelta($luasPenuh, Geometri::luasKm2($potong), $luasPenuh * 1e-3);

        // Lubang ikut terpotong: luas = U − lubang.
        $semua = Geometri::potongPersegi($u, -1, -1, 4, 4);
        $this->assertSame($u, $semua);
        $tengah = Geometri::potongPersegi($u, 0, 1, 1, 2);
        $this->assertEqualsWithDelta(
            Geometri::luasKm2(self::kotak(0, 1, 1, 2)) - Geometri::luasKm2(self::kotak(0.4, 1.4, 0.6, 1.6)),
            Geometri::luasKm2($tengah), 20,
        );

        // Titik label selalu di dalam, juga untuk bagian yang tersambung garis nol-lebar.
        [$x, $y] = Geometri::titikDalam($potong);
        $this->assertTrue(Geometri::dalam($x, $y, $u));
        $this->assertFalse(Geometri::dalam(0.5, 1.5, $u)); // di dalam lubang
        $this->assertSame([], Geometri::potongPersegi($u, 5, 5, 6, 6));
    }

    public function test_grid_modes_conserve_area_and_track_villages(): void
    {
        // Dua kelurahan bersebelahan ±3,3 km × 3,3 km di sekitar Jakarta + satu pulau kecil milik kelurahan B.
        $kel = [
            ['id' => 1, 'kode' => '3171011001', 'nama' => 'Barat', 'kecamatan' => 'Gambir', 'multi' => self::kotak(106.80, -6.20, 106.83, -6.17)],
            ['id' => 2, 'kode' => '3171011002', 'nama' => 'Timur', 'kecamatan' => 'Gambir', 'multi' => [
                ...self::kotak(106.83, -6.20, 106.86, -6.17),
                ...self::kotak(106.861, -6.171, 106.8615, -6.1705),
            ]],
        ];
        $luasTotal = Geometri::luasKm2($kel[0]['multi']) + Geometri::luasKm2($kel[1]['multi']);

        $perKel = (new GridArea)->buat($kel, 2, 'kelurahan');
        $kotak = (new GridArea)->buat($kel, 2, 'kotak');

        foreach ([$perKel, $kotak] as $g) {
            $this->assertEqualsWithDelta($luasTotal, array_sum(array_column($g['unit'], 'luas_km2')), 0.01);
            $this->assertSame(count($g['unit']), count(array_unique(array_column($g['unit'], 'kunci'))));
            foreach ($g['unit'] as $u) {
                $this->assertTrue(Geometri::dalam($u['titik'][0], $u['titik'][1], $u['geometri']), $u['nama']);
            }
        }

        // Mode kelurahan: setiap area tepat satu kelurahan. Mode kotak: kotak di garis batas mencakup dua.
        $this->assertSame([1], array_unique(array_map(fn ($u) => count($u['kelurahan']), $perKel['unit'])));
        $this->assertContains(2, array_map(fn ($u) => count($u['kelurahan']), $kotak['unit']));
        $this->assertGreaterThan(count($kotak['unit']), count($perKel['unit']));

        // Pulau kecil (< 25% kotak) digabung ke kotak tetangga: label "A4+B4" dsb, bukan area serpihan sendiri.
        $this->assertNotEmpty(array_filter($kotak['unit'], fn ($u) => str_contains($u['label'], '+')));
        $this->assertSame('A1', $kotak['unit'][0]['label']);
        $this->assertStringStartsWith('Barat (A1', $kotak['unit'][0]['nama']);
    }

    public function test_hexagon_mode_tiles_without_gaps_and_cells_are_hexagons(): void
    {
        $kel = [
            ['id' => 1, 'kode' => '3171011001', 'nama' => 'Barat', 'kecamatan' => 'Gambir', 'multi' => self::kotak(106.80, -6.20, 106.83, -6.17)],
            ['id' => 2, 'kode' => '3171011002', 'nama' => 'Timur', 'kecamatan' => 'Gambir', 'multi' => self::kotak(106.83, -6.20, 106.86, -6.17)],
        ];
        $luasTotal = Geometri::luasKm2($kel[0]['multi']) + Geometri::luasKm2($kel[1]['multi']);

        $g = (new GridArea)->buat($kel, 1, 'segienam');

        // Tanpa celah dan tumpang tindih: jumlah luas = luas wilayah.
        $this->assertEqualsWithDelta($luasTotal, array_sum(array_column($g['unit'], 'luas_km2')), 0.01);
        $this->assertSame(count($g['unit']), count(array_unique(array_column($g['unit'], 'kunci'))));
        foreach ($g['unit'] as $u) {
            $this->assertStringStartsWith('h', $u['kunci']);
            $this->assertTrue(Geometri::dalam($u['titik'][0], $u['titik'][1], $u['geometri']), $u['nama']);
        }

        // Segi enam di tengah wilayah utuh: 6 sudut, luas = 3√3/2 · r² dengan r = 1/√3 km.
        $utuh = array_filter($g['unit'], fn ($u) => count($u['kelurahan']) === 1 && count($u['geometri']) === 1
            && count($u['geometri'][0][0]) === 7 && abs($u['luas_km2'] - sqrt(3) / 2) < 0.01);
        $this->assertNotEmpty($utuh);

        // Segi enam di garis batas dua kelurahan mencakup keduanya; kelurahan utama = bagian terluas.
        $dua = array_values(array_filter($g['unit'], fn ($u) => count($u['kelurahan']) === 2));
        $this->assertNotEmpty($dua);
        $this->assertGreaterThanOrEqual($dua[0]['kelurahan'][1]['luas_km2'], $dua[0]['kelurahan'][0]['luas_km2']);

        // Deterministik: kunci sama untuk input sama.
        $this->assertSame(array_column($g['unit'], 'kunci'), array_column((new GridArea)->buat($kel, 1, 'segienam')['unit'], 'kunci'));
    }

    public function test_hexagon_fragment_of_other_village_merges_into_neighbour(): void
    {
        // Kelurahan kecil yang menempel di tepi: serpihannya digabung ke segi enam tetangga yang belum memuatnya.
        $kel = [
            ['id' => 1, 'kode' => '1', 'nama' => 'Besar', 'kecamatan' => 'X', 'multi' => self::kotak(106.80, -6.20, 106.83, -6.17)],
            ['id' => 2, 'kode' => '2', 'nama' => 'Kecil', 'kecamatan' => 'X', 'multi' => self::kotak(106.83, -6.1705, 106.8303, -6.17)],
        ];
        $g = (new GridArea)->buat($kel, 1, 'segienam');

        $luasTotal = Geometri::luasKm2($kel[0]['multi']) + Geometri::luasKm2($kel[1]['multi']);
        $this->assertEqualsWithDelta($luasTotal, array_sum(array_column($g['unit'], 'luas_km2')), 0.01);
        $kecil = array_values(array_filter($g['unit'], fn ($u) => in_array('Kecil', array_column($u['kelurahan'], 'nama'), true)));
        $this->assertCount(1, $kecil);
        $this->assertSame('Besar', $kecil[0]['kelurahan'][0]['nama']);
    }

    public function test_convex_clip_keeps_inside_part_only(): void
    {
        $persegi = self::kotak(0, 0, 2, 2);
        // Segitiga berlawanan jarum jam menutup separuh persegi (di bawah diagonal).
        $potong = Geometri::potongCembung($persegi, [[-1, -1], [3, -1], [3, 3]]);
        $this->assertEqualsWithDelta(Geometri::luasKm2($persegi) / 2, Geometri::luasKm2($potong), Geometri::luasKm2($persegi) * 1e-3);
        $this->assertSame([], Geometri::potongCembung($persegi, [[5, 5], [6, 5], [6, 6]]));
    }

    public function test_labels_continue_past_z_and_dense_grid_is_rejected(): void
    {
        $this->assertSame('A1', GridArea::labelKotak(0, 0));
        $this->assertSame('Z3', GridArea::labelKotak(25, 2));
        $this->assertSame('AA1', GridArea::labelKotak(26, 0));
        $this->assertSame('AB10', GridArea::labelKotak(27, 9));

        $this->expectException(RuntimeException::class);
        (new GridArea)->buat([['id' => 1, 'kode' => '1', 'nama' => 'Luas', 'kecamatan' => 'X', 'multi' => self::kotak(100, -3, 102, -1)]], 1, 'kotak');
    }
}
