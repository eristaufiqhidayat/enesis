<?php

namespace App\Support\Mobile;

use App\Support\Marketama\HalamanParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nilai aturan untuk aplikasi mobile: data depo (koordinat, minimal transaksi, target),
// jam aktif hari ini (waktu_aktif_app), dan tanggal libur (holiday_dates). Semua punya
// cadangan di config/mobile.php bila tabel referensi kosong.
class Pengaturan
{
    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public static function zona(): string
    {
        return config('mobile.zona_waktu');
    }

    public static function sekarang(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona());
    }

    /**
     * @return array{id: int, nama: string, lat: ?float, lng: ?float, minimal_transaksi: int, target: array, interval_gps_detik: int}|null
     */
    public static function depo(?int $depoId): ?array
    {
        if (! $depoId) {
            return null;
        }

        $depo = DB::table('mk_depo')->where('id', $depoId)->first();

        if (! $depo) {
            return null;
        }

        $data = json_decode((string) $depo->data, true) ?: [];

        // Cadangan: tabel master_depo lama (hasil scraping) dengan nama yang sama.
        if (Schema::hasTable('master_depo') && (! isset($data['latitude']) || ! isset($data['minimal_value_transaksi']))) {
            $lama = DB::table('master_depo')->whereRaw('LOWER(TRIM(nama)) = ?', [HalamanParser::kunciNama($depo->nama)])->first();
            $data += $lama ? (array) $lama : [];
        }

        $nilai = fn (string $k) => isset($data[$k]) && $data[$k] !== '' ? $data[$k] : null;
        $lat = $nilai('latitude');
        $lng = $nilai('longitude');
        $sah = Lokasi::sah(is_string($lat) ? str_replace(',', '.', $lat) : $lat, is_string($lng) ? str_replace(',', '.', $lng) : $lng);
        $bulat = function ($v): ?int {
            $n = $v === null ? null : HalamanParser::angka((string) $v);

            return $n ? (int) round($n) : null;
        };
        $target = config('mobile.target_default');

        return [
            'id' => (int) $depo->id,
            'nama' => $depo->nama,
            'lat' => $sah ? (float) str_replace(',', '.', (string) $lat) : null,
            'lng' => $sah ? (float) str_replace(',', '.', (string) $lng) : null,
            'minimal_transaksi' => $bulat($nilai('minimal_value_transaksi')) ?? config('mobile.minimal_transaksi_default'),
            'target' => [
                'call' => $bulat($nilai('target_call')) ?? $target['call'],
                'ec' => $bulat($nilai('target_ec')) ?? $target['ec'],
                'ipt' => $target['ipt'],
                'omset' => $target['omset'],
            ],
            'interval_gps_detik' => $bulat($nilai('durasi_tracking_gps')) ?? 180,
        ];
    }

    /**
     * Jam aktif hari ini untuk sales/koordinator.
     *
     * @return array{hari_kerja: bool, mulai: string, selesai: string, batas_masuk: string}
     */
    public static function jamAktif(CarbonImmutable $waktu, string $peran = 'sales'): array
    {
        $hasil = [
            'hari_kerja' => true,
            'mulai' => config('mobile.jam_aktif_mulai'),
            'selesai' => config('mobile.jam_aktif_selesai'),
            'batas_masuk' => config('mobile.batas_masuk'),
        ];

        if (! Schema::hasTable('waktu_aktif_app')) {
            return $hasil;
        }

        $baris = DB::table('waktu_aktif_app')->get()
            ->first(fn ($b) => strtolower(trim((string) $b->nama_hari)) === strtolower(self::HARI[$waktu->dayOfWeek]));

        if (! $baris) {
            return $hasil;
        }

        $kolom = $peran === 'koordinator' ? 'koordinator' : 'sales';
        $hasil['hari_kerja'] = HalamanParser::aktif($baris->{$kolom} ?? null) !== false;

        foreach (['mulai' => 'waktu_mulai', 'selesai' => 'waktu_berakhir'] as $k => $kol) {
            if (preg_match('/^(\d{1,2})[:.](\d{2})/', (string) $baris->{$kol}, $m)) {
                $hasil[$k] = sprintf('%02d:%s', $m[1], $m[2]);
            }
        }

        return $hasil;
    }

    /**
     * Daftar libur (tanggal Y-m-d => nama) antara dua tanggal.
     *
     * @return array<string, string>
     */
    public static function libur(string $dari, string $sampai): array
    {
        if (! Schema::hasTable('holiday_dates')) {
            return [];
        }

        $hasil = [];

        foreach (DB::table('holiday_dates')->get(['nama_libur', 'tanggal']) as $b) {
            $tgl = self::tanggal((string) $b->tanggal);

            if ($tgl && $tgl >= $dari && $tgl <= $sampai) {
                $hasil[$tgl] = trim((string) $b->nama_libur) ?: 'Libur';
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /** Tanggal teks dari layar lama (2026-12-25, 25-12-2026, 25/12/2026, 25 Desember 2026) ke Y-m-d. */
    public static function tanggal(string $teks): ?string
    {
        $teks = trim($teks);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $teks, $m)) {
            return "$m[1]-$m[2]-$m[3]";
        }

        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})#', $teks, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $bulan = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})/i', $teks, $m) && ($b = array_search(strtolower($m[2]), $bulan, true)) !== false) {
            return sprintf('%04d-%02d-%02d', $m[3], $b + 1, $m[1]);
        }

        return null;
    }

    /** Poin produk fokus dan batas poin per invoice dari data produk. */
    public static function poinProduk(object $produk): array
    {
        $data = json_decode((string) ($produk->data ?? ''), true) ?: [];
        $fokus = HalamanParser::aktif($data['produk_fokus'] ?? null) === true;
        $maks = HalamanParser::angka(isset($data['maksimal_poin_per_invoice']) ? (string) $data['maksimal_poin_per_invoice'] : null);

        return ['fokus' => $fokus, 'maks_poin' => $maks ? (int) $maks : null];
    }
}
