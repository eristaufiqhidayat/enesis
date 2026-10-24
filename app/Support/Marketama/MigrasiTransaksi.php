<?php

namespace App\Support\Marketama;

use App\Support\Mobile\Lokasi;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migrasi data lama (lap_*) ke tabel motoris (trx_*) supaya riwayat aplikasi lama dan data
// aplikasi motoris ada di satu tabel. Baris lama ditandai sumber = "lama" dan sumber_id =
// source_id-nya, jadi perintah ini aman diulang (hanya baris baru yang ditambahkan).
// Satu sales satu baris per tanggal: bila tanggal itu sudah ada (dari motoris atau baris lama
// lain), baris lama dilewati — data motoris tidak pernah ditimpa.
class MigrasiTransaksi
{
    /** jenis => [tabel lama, tabel motoris, kolom ID yang perlu disambung dulu] */
    public const JENIS = [
        'absensi' => ['lap_absensi_sales', 'trx_absensi', ['karyawan_id', 'depo_id']],
        'checkin_depo' => ['lap_checkin_checkout', 'trx_checkin_depo', ['karyawan_id', 'depo_id']],
        'kunjungan' => ['lap_checkin_outlet', 'trx_kunjungan', ['karyawan_id', 'depo_id', 'outlet_id']],
        'join_visit' => ['lap_join_visit', 'trx_join_visit', ['karyawan_id', 'koordinator_id', 'depo_id', 'area_id', 'outlet_id']],
    ];

    private const POTONGAN = 1000;

    /** @var Closure(string): void */
    private Closure $log;

    /** @var array<int, string> batas jam masuk per hari (0 = Minggu) */
    private array $batasMasuk = [];

    /** @var array<int, ?int> depo karyawan */
    private array $depoKaryawan = [];

    public function __construct(?Closure $log = null)
    {
        $this->log = $log ?? fn (string $pesan) => null;
    }

    /**
     * @param  list<string>  $jenis  kosong = semua
     * @return list<array{jenis:string, sumber:int, dipindah:int, sudah:int, lewati:array<string,int>}>
     */
    public function jalankan(array $jenis = [], bool $sambung = true): array
    {
        $laporan = [];

        foreach (self::JENIS as $nama => [$lama, $trx, $kolomId]) {
            if (($jenis && ! in_array($nama, $jenis, true)) || ! Schema::hasTable($lama) || ! Schema::hasColumn($trx, 'sumber')) {
                continue;
            }

            if ($sambung) {
                ($this->log)("{$lama}: menyambungkan nama ke ID karyawan & depo…");
                (new SambungTransaksi)->jalankan([$lama], $kolomId);
            }

            $laporan[] = $this->{str($nama)->camel()->toString()}($lama, $trx);
            ($this->log)(sprintf('%s → %s selesai', $lama, $trx));
        }

        return $laporan;
    }

    private function absensi(string $lama, string $trx): array
    {
        return $this->pindahkan('absensi', $lama, $trx, 'time_in', function (object $r) {
            $masuk = $this->waktu($r->time_in);
            $tanggal = $r->date ?? $masuk?->toDateString();

            return [
                'karyawan_id' => $r->karyawan_id,
                'depo_id' => $r->depo_id ?? $this->depo($r->karyawan_id),
                'tanggal' => $tanggal ? substr((string) $tanggal, 0, 10) : null,
                'masuk_at' => $masuk?->utc(),
                'terlambat' => $masuk ? $masuk->format('H:i') > $this->batasMasuk($masuk) : false,
                'pulang_at' => $this->waktu($r->time_out)?->utc(),
                'recorded_at' => $masuk?->utc(),
            ];
        });
    }

    private function checkinDepo(string $lama, string $trx): array
    {
        return $this->pindahkan('checkin_depo', $lama, $trx, 'checkin_time', function (object $r) {
            $masuk = $this->waktu($r->checkin_time);
            $tanggal = $r->attendance_date ?? $masuk?->toDateString();
            [$lat, $lng] = $this->titik($r->location_lat, $r->location_long);
            $depo = $r->depo_id ?? $this->depo($r->karyawan_id);

            if (! $depo) {
                return 'depo tidak dikenali';
            }

            return [
                'karyawan_id' => $r->karyawan_id,
                'depo_id' => $depo,
                'tanggal' => $tanggal ? substr((string) $tanggal, 0, 10) : null,
                'checkin_at' => $masuk?->utc(),
                'lat' => $lat,
                'lng' => $lng,
                'checkout_at' => $this->waktu($r->checkout_time)?->utc(),
                'recorded_at' => $masuk?->utc(),
            ];
        });
    }

    /** Join visit lama: karyawan_id = sales yang didampingi. Boleh lebih dari satu per hari. */
    private function joinVisit(string $lama, string $trx): array
    {
        return $this->pindahkan('join_visit', $lama, $trx, 'start_time', function (object $r) {
            if (! $r->koordinator_id) {
                return 'koordinator tidak dikenali';
            }

            $mulai = $this->waktu($r->start_time);
            $selesai = $this->waktu($r->end_time);
            $tanggal = $this->waktu($r->inspection_date)?->toDateString() ?? $mulai?->toDateString();

            return [
                'koordinator_id' => $r->koordinator_id,
                'sales_id' => $r->karyawan_id,
                'depo_id' => $r->depo_id ?? $this->depo($r->karyawan_id),
                'outlet_id' => $r->outlet_id,
                'area_id' => $r->area_id,
                'tanggal' => $tanggal,
                'mulai_at' => $mulai?->utc(),
                'selesai_at' => $selesai?->utc(),
                'durasi_detik' => $mulai && $selesai && $selesai->gte($mulai) ? (int) $mulai->diffInSeconds($selesai) : null,
                'catatan' => $r->description,
                'foto_lama' => $r->photo,
                'recorded_at' => $mulai?->utc(),
            ];
        }, 'sudah ada');
    }

    private function kunjungan(string $lama, string $trx): array
    {
        return $this->pindahkan('kunjungan', $lama, $trx, 'datetime_checkin', function (object $r) {
            if (! $r->outlet_id) {
                return 'outlet tidak dikenali';
            }

            $masuk = $this->waktu($r->datetime_checkin);

            if (! $masuk) {
                return 'tanpa jam checkin';
            }

            $keluar = $this->waktu($r->datetime_checkout);
            [$lat, $lng] = $this->titik($r->sales_latitude, $r->sales_longitude);
            [$oLat, $oLng] = $this->titik($r->outlet_latitude, $r->outlet_longitude);
            $foto = trim((string) $r->photo);

            return [
                'karyawan_id' => $r->karyawan_id,
                'depo_id' => $r->depo_id ?? $this->depo($r->karyawan_id),
                'outlet_id' => $r->outlet_id,
                'tanggal' => substr((string) ($r->date_checkin ?? $masuk->toDateString()), 0, 10),
                'checkin_at' => $masuk->utc(),
                'lat' => $lat,
                'lng' => $lng,
                'jarak_m' => $lat !== null && $oLat !== null ? Lokasi::jarak($lat, $lng, $oLat, $oLng) : null,
                'checkout_at' => $keluar?->utc(),
                'durasi_detik' => $keluar && $keluar->gte($masuk) ? (int) $masuk->diffInSeconds($keluar) : null,
                'foto_url' => preg_match('#^https?://#i', $foto) ? mb_substr($foto, 0, 255) : null,
                'kategori_lama' => $r->category,
                'keterangan_lama' => $r->description !== null ? mb_substr(trim($r->description), 0, 255) : null,
                'recorded_at' => $masuk->utc(),
            ];
        }, 'sudah ada');
    }

    /**
     * @param  Closure(object): (array<string, mixed>|string)  $petakan  string = alasan baris dilewati
     */
    private function pindahkan(string $jenis, string $lama, string $trx, string $kolomWaktu, Closure $petakan, string $bentrok = 'tanggal itu sudah ada'): array
    {
        $hasil = ['jenis' => $jenis, 'sumber' => 0, 'dipindah' => 0, 'sudah' => 0, 'lewati' => []];
        $lewati = function (string $alasan) use (&$hasil) {
            $hasil['lewati'][$alasan] = ($hasil['lewati'][$alasan] ?? 0) + 1;
        };
        $sekarang = now();

        DB::table($lama)->orderBy('id')->chunkById(self::POTONGAN, function ($baris) use ($trx, $petakan, $lewati, &$hasil, $sekarang, $bentrok) {
            $hasil['sumber'] += $baris->count();
            $sudah = DB::table($trx)->whereIn('sumber_id', $baris->pluck('source_id')->filter())->pluck('sumber_id')->flip();
            $kirim = [];

            foreach ($baris as $r) {
                if ($r->source_id === null) {
                    $lewati('tanpa source_id');

                    continue;
                }

                if (isset($sudah[$r->source_id])) {
                    $hasil['sudah']++;

                    continue;
                }

                if (! $r->karyawan_id) {
                    $lewati('sales tidak dikenali');

                    continue;
                }

                $data = $petakan($r);

                if (is_string($data)) {
                    $lewati($data);

                    continue;
                }

                if (! $data['tanggal']) {
                    $lewati('tanpa tanggal');

                    continue;
                }

                $kirim[] = $data + [
                    'sumber' => 'lama',
                    'sumber_id' => $r->source_id,
                    'received_at' => $sekarang,
                ];
            }

            // Bentrok (sales + tanggal sudah ada) diabaikan oleh database; data motoris tidak ditimpa.
            // Dalam satu potongan diurut jam supaya baris ganda di satu tanggal memakai yang paling awal.
            $kirim = collect($kirim)->sortBy(fn ($d) => $d['tanggal'].' '.($d['masuk_at'] ?? $d['checkin_at'] ?? ''))->values();
            $masuk = $kirim->isEmpty() ? 0 : DB::table($trx)->insertOrIgnore($kirim->all());
            $hasil['dipindah'] += $masuk;

            if ($kirim->count() > $masuk) {
                $hasil['lewati'][$bentrok] = ($hasil['lewati'][$bentrok] ?? 0) + $kirim->count() - $masuk;
            }
        });

        return $hasil;
    }

    /** Waktu di data lama tercatat dalam jam lokal (WIB). */
    private function waktu(mixed $nilai): ?CarbonImmutable
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $nilai, Pengaturan::zona());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: ?float, 1: ?float} titik GPS, null bila kosong/0/di luar jangkauan */
    private function titik(mixed $lat, mixed $lng): array
    {
        $lat = is_numeric($lat) ? (float) $lat : null;
        $lng = is_numeric($lng) ? (float) $lng : null;

        if (! $lat || ! $lng || abs($lat) > 90 || abs($lng) > 180) {
            return [null, null];
        }

        return [$lat, $lng];
    }

    private function batasMasuk(CarbonImmutable $waktu): string
    {
        return $this->batasMasuk[$waktu->dayOfWeek] ??= Pengaturan::jamAktif($waktu, 'sales')['batas_masuk'];
    }

    private function depo(?int $karyawanId): ?int
    {
        if (! $karyawanId) {
            return null;
        }

        if (! array_key_exists($karyawanId, $this->depoKaryawan)) {
            $depo = DB::table('mk_karyawan')->where('id', $karyawanId)->value('depo_id');
            $this->depoKaryawan[$karyawanId] = $depo ? (int) $depo : null;
        }

        return $this->depoKaryawan[$karyawanId];
    }
}
