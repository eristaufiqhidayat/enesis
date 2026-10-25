<?php

namespace App\Support\Marketama;

use App\Support\Mobile\Lokasi;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        'absensi_koordinator' => ['lap_absensi_koordinator', 'trx_absensi', ['karyawan_id', 'depo_id']],
        'penjualan' => ['lap_penjualan_barang', 'trx_penjualan', ['karyawan_id', 'depo_id', 'outlet_id']],
        'stok_outlet' => ['lap_stok_outlet', 'trx_stok_outlet', ['karyawan_id', 'depo_id', 'outlet_id', 'produk_id']],
        'stok_kompetitor' => ['lap_stok_kompetitor', 'trx_stok_outlet', ['karyawan_id', 'outlet_id']],
        'canvassing' => ['lap_barang_canvassing', 'trx_canvassing_item', ['karyawan_id', 'depo_id', 'produk_id']],
    ];

    /** Tabel lama pendamping yang ID-nya juga perlu disambung: jenis => [tabel => kolom]. */
    private const SAMBUNG_TAMBAHAN = [
        'penjualan' => ['lap_penjualan_barang_detail' => ['produk_id']],
        'canvassing' => ['lap_pengembalian_barang' => ['karyawan_id', 'produk_id']],
    ];

    private const POTONGAN = 1000;

    /** @var Closure(string): void */
    private Closure $log;

    /** @var array<int, string> batas jam masuk per hari (0 = Minggu) */
    private array $batasMasuk = [];

    /** @var array<string, int> jumlah baris tabel lama (untuk laporan kemajuan) */
    private array $totalBaris = [];

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
            $siap = Schema::hasColumn($trx, 'sumber') || Schema::hasColumn($trx, 'sumber_id');

            if (($jenis && ! in_array($nama, $jenis, true)) || ! Schema::hasTable($lama) || ! $siap) {
                continue;
            }

            if ($sambung) {
                ($this->log)("{$lama}: menyambungkan nama ke ID karyawan & depo…");
                (new SambungTransaksi)->jalankan([$lama], $kolomId);

                foreach (self::SAMBUNG_TAMBAHAN[$nama] ?? [] as $tabel => $kolom) {
                    if (Schema::hasTable($tabel)) {
                        (new SambungTransaksi)->jalankan([$tabel], $kolom);
                    }
                }
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

    /** Absensi koordinator lama (kolom sama dengan absensi sales), batas masuk memakai jam koordinator. */
    private function absensiKoordinator(string $lama, string $trx): array
    {
        return $this->pindahkan('absensi_koordinator', $lama, $trx, 'time_in', function (object $r) {
            $masuk = $this->waktu($r->time_in);
            $tanggal = $r->date ?? $masuk?->toDateString();

            return [
                'karyawan_id' => $r->karyawan_id,
                'depo_id' => $r->depo_id ?? $this->depo($r->karyawan_id),
                'tanggal' => $tanggal ? substr((string) $tanggal, 0, 10) : null,
                'masuk_at' => $masuk?->utc(),
                'terlambat' => $masuk ? $masuk->format('H:i') > Pengaturan::jamAktif($masuk, 'koordinator')['batas_masuk'] : false,
                'pulang_at' => $this->waktu($r->time_out)?->utc(),
                'recorded_at' => $masuk?->utc(),
            ];
        });
    }

    /**
     * Penjualan lama (header lap_penjualan_barang + item lap_penjualan_barang_detail per trans_code) → trx_penjualan
     * + trx_penjualan_item. Tiap penjualan disambung ke kunjungan lama (sales + outlet + jam checkin); bila kunjungan
     * itu belum ada di trx_kunjungan, dibuat dari data penjualan. Poin tidak tercatat di data lama (0).
     */
    private function penjualan(string $lama, string $trx): array
    {
        $hasil = ['jenis' => 'penjualan', 'sumber' => 0, 'dipindah' => 0, 'sudah' => 0, 'lewati' => []];
        $lewati = function (string $alasan, int $n = 1) use (&$hasil) {
            $hasil['lewati'][$alasan] = ($hasil['lewati'][$alasan] ?? 0) + $n;
        };

        DB::table($lama)->orderBy('id')->chunkById(self::POTONGAN, function ($baris) use (&$hasil, $lewati, $lama) {
            $hasil['sumber'] += $baris->count();
            $this->kemajuan($lama, $hasil['sumber']);
            $sudah = DB::table('trx_penjualan')->whereIn('sumber_id', $baris->pluck('source_id')->filter())->pluck('sumber_id')->flip();
            $kode = $baris->pluck('trans_code')->filter()->values();
            $kodeAda = DB::table('trx_penjualan')->whereIn('kode', $kode)->pluck('kode')->flip();
            $detail = Schema::hasTable('lap_penjualan_barang_detail')
                ? DB::table('lap_penjualan_barang_detail')->whereIn('trans_code', $kode)->orderBy('id')->get()->groupBy('trans_code')
                : collect();
            $calon = [];

            foreach ($baris as $r) {
                $alasan = match (true) {
                    $r->source_id === null => 'tanpa source_id',
                    isset($sudah[$r->source_id]) => null,
                    ! $r->karyawan_id => 'sales tidak dikenali',
                    ! $r->outlet_id => 'outlet tidak dikenali',
                    trim((string) $r->trans_code) === '' => 'tanpa kode transaksi',
                    isset($kodeAda[$r->trans_code]) => 'kode transaksi sudah ada',
                    default => false,
                };

                if ($alasan === null) {
                    $hasil['sudah']++;

                    continue;
                }

                if ($alasan !== false) {
                    $lewati($alasan);

                    continue;
                }

                $jam = $this->waktu($r->datetime_trans) ?? $this->waktu($r->datetime_checkin);
                $masuk = $this->waktu($r->datetime_checkin) ?? $jam;
                $tanggal = $r->date_trans ? substr((string) $r->date_trans, 0, 10) : $jam?->toDateString();

                if (! $tanggal || ! $masuk) {
                    $lewati('tanpa tanggal');

                    continue;
                }

                $item = [];

                foreach ($detail[$r->trans_code] ?? [] as $d) {
                    if (! $d->produk_id) {
                        $lewati('item: produk tidak dikenali');

                        continue;
                    }

                    $item[] = [
                        'produk_id' => (int) $d->produk_id,
                        'qty' => $this->angka($d->quantity),
                        'harga' => (int) round($this->angka($d->price)),
                        'diskon' => (int) round($this->angka($d->total_discount ?? $d->discount)),
                        'total' => (int) round($this->angka($d->total)),
                        'poin' => 0,
                    ];
                }

                [$lat, $lng] = $this->titik($r->sales_latitude, $r->sales_longitude);
                $calon[] = [
                    'kunjungan' => $this->kunciKunjungan((int) $r->karyawan_id, (int) $r->outlet_id, $r->depo_id, $masuk, $this->waktu($r->datetime_checkout), $lat, $lng, $tanggal),
                    'baris' => [
                        'kode' => mb_substr(trim($r->trans_code), 0, 50),
                        'karyawan_id' => (int) $r->karyawan_id,
                        'depo_id' => $r->depo_id ?? $this->depo($r->karyawan_id),
                        'outlet_id' => (int) $r->outlet_id,
                        'tanggal' => $tanggal,
                        'jumlah_sku' => (int) ($this->angka($r->total_item) ?: count($item)),
                        'total_qty' => $this->angka($r->total_qty) ?: array_sum(array_column($item, 'qty')),
                        'total' => (int) round($this->angka($r->grand_total)),
                        'total_diskon' => array_sum(array_column($item, 'diskon')),
                        'poin' => 0,
                        'is_effective_call' => HalamanParser::aktif($r->effective_call_status === null ? null : (string) $r->effective_call_status) === true,
                        'recorded_at' => $jam?->utc(),
                        'received_at' => now(),
                        'sumber' => 'lama',
                        'sumber_id' => $r->source_id,
                    ],
                    'item' => $item,
                ];
            }

            if (! $calon) {
                return;
            }

            $kunjungan = $this->kunjunganUntuk(array_column($calon, 'kunjungan'));
            $kirim = array_map(fn ($c) => $c['baris'] + ['kunjungan_id' => $kunjungan[$c['kunjungan']['kunci']]], $calon);
            $hasil['dipindah'] += DB::table('trx_penjualan')->insertOrIgnore($kirim);

            $id = DB::table('trx_penjualan')->whereIn('sumber_id', array_column($kirim, 'sumber_id'))->pluck('id', 'sumber_id');
            $adaItem = DB::table('trx_penjualan_item')->whereIn('penjualan_id', $id->values())->distinct()->pluck('penjualan_id')->flip();
            $item = [];

            foreach ($calon as $c) {
                $pid = $id[$c['baris']['sumber_id']] ?? null;

                if ($pid && ! isset($adaItem[$pid])) {
                    foreach ($c['item'] as $i) {
                        $item[] = $i + ['penjualan_id' => $pid];
                    }
                }
            }

            foreach (array_chunk($item, 500) as $potong) {
                DB::table('trx_penjualan_item')->insert($potong);
            }
        });

        return $hasil;
    }

    /** Stok produk sendiri yang dicatat saat kunjungan lama → trx_stok_outlet (sumber "lama"). */
    private function stokOutlet(string $lama, string $trx): array
    {
        return $this->pindahkanStok('stok_outlet', $lama, 'lama', function (object $r) {
            return $r->produk_id ? ['produk_id' => (int) $r->produk_id, 'produk_kompetitor' => null] : 'produk tidak dikenali';
        });
    }

    /** Stok produk kompetitor saat kunjungan lama → trx_stok_outlet (sumber "lama_komp"). */
    private function stokKompetitor(string $lama, string $trx): array
    {
        return $this->pindahkanStok('stok_kompetitor', $lama, 'lama_komp', function (object $r) {
            $nama = trim((string) $r->product_name);

            return $nama !== '' ? ['produk_id' => null, 'produk_kompetitor' => mb_substr($nama, 0, 255)] : 'tanpa nama produk';
        });
    }

    /**
     * @param  Closure(object): (array{produk_id: ?int, produk_kompetitor: ?string}|string)  $produk
     */
    private function pindahkanStok(string $jenis, string $lama, string $sumber, Closure $produk): array
    {
        $hasil = ['jenis' => $jenis, 'sumber' => 0, 'dipindah' => 0, 'sudah' => 0, 'lewati' => []];
        $lewati = function (string $alasan) use (&$hasil) {
            $hasil['lewati'][$alasan] = ($hasil['lewati'][$alasan] ?? 0) + 1;
        };
        $adaDepo = Schema::hasColumn($lama, 'depo_id');

        DB::table($lama)->orderBy('id')->chunkById(self::POTONGAN, function ($baris) use (&$hasil, $lewati, $sumber, $produk, $adaDepo, $lama) {
            $hasil['sumber'] += $baris->count();
            $this->kemajuan($lama, $hasil['sumber']);
            $sudah = DB::table('trx_stok_outlet')->where('sumber', $sumber)->whereIn('sumber_id', $baris->pluck('source_id')->filter())
                ->pluck('sumber_id')->flip();
            $calon = [];

            foreach ($baris as $r) {
                if ($r->source_id === null) {
                    $lewati('tanpa source_id');

                    continue;
                }

                if (isset($sudah[$r->source_id])) {
                    $hasil['sudah']++;

                    continue;
                }

                $p = ! $r->karyawan_id ? 'sales tidak dikenali' : (! $r->outlet_id ? 'outlet tidak dikenali' : $produk($r));
                $masuk = $this->waktu($r->datetime_checkin);

                if (is_string($p) || ! $masuk) {
                    $lewati(is_string($p) ? $p : 'tanpa jam checkin');

                    continue;
                }

                $tanggal = $r->date_checkin ? substr((string) $r->date_checkin, 0, 10) : $masuk->toDateString();
                $calon[] = [
                    'kunjungan' => $this->kunciKunjungan((int) $r->karyawan_id, (int) $r->outlet_id, $adaDepo ? $r->depo_id : null, $masuk, $this->waktu($r->datetime_checkout), null, null, $tanggal),
                    'baris' => $p + [
                        'qty' => $this->angka($r->quantity),
                        'created_at' => ($this->waktu($r->last_update) ?? $masuk)->utc(),
                        'sumber' => $sumber,
                        'sumber_id' => $r->source_id,
                    ],
                ];
            }

            if ($calon) {
                $kunjungan = $this->kunjunganUntuk(array_column($calon, 'kunjungan'));
                $kirim = array_map(fn ($c) => $c['baris'] + ['kunjungan_id' => $kunjungan[$c['kunjungan']['kunci']]], $calon);
                $hasil['dipindah'] += DB::table('trx_stok_outlet')->insertOrIgnore($kirim);
            }
        });

        return $hasil;
    }

    /**
     * Barang canvassing lama (per item, lap_barang_canvassing) → trx_canvassing per canvassing_no + trx_canvassing_item;
     * jam & jumlah kembali dari lap_pengembalian_barang. Penjualan lama sales itu pada tanggal yang sama disambung ke
     * canvassing-nya supaya jumlah terjual bisa dihitung.
     */
    private function canvassing(string $lama, string $trx): array
    {
        $hasil = ['jenis' => 'canvassing', 'sumber' => 0, 'dipindah' => 0, 'sudah' => 0, 'lewati' => []];
        $lewati = function (string $alasan) use (&$hasil) {
            $hasil['lewati'][$alasan] = ($hasil['lewati'][$alasan] ?? 0) + 1;
        };
        $kembali = Schema::hasTable('lap_pengembalian_barang');

        DB::table($lama)->orderBy('id')->chunkById(self::POTONGAN, function ($baris) use (&$hasil, $lewati, $kembali, $lama) {
            $hasil['sumber'] += $baris->count();
            $this->kemajuan($lama, $hasil['sumber']);
            $sudah = DB::table('trx_canvassing_item')->whereIn('sumber_id', $baris->pluck('source_id')->filter())->pluck('sumber_id')->flip();
            $nomor = $baris->pluck('canvassing_no')->filter()->unique()->values();
            $header = DB::table('trx_canvassing')->whereIn('nomor', $nomor)->get(['id', 'nomor', 'sumber'])->keyBy('nomor');
            $retur = $kembali
                ? DB::table('lap_pengembalian_barang')->whereIn('canvassing_no', $nomor)->get()->groupBy('canvassing_no')
                : collect();
            $item = [];

            foreach ($baris as $r) {
                $no = trim((string) $r->canvassing_no);
                $alasan = match (true) {
                    $r->source_id === null => 'tanpa source_id',
                    isset($sudah[$r->source_id]) => null,
                    $no === '' => 'tanpa nomor canvassing',
                    ! $r->karyawan_id => 'sales tidak dikenali',
                    ! $r->produk_id => 'produk tidak dikenali',
                    isset($header[$no]) && $header[$no]->sumber !== 'lama' => 'nomor sudah dipakai aplikasi HP',
                    default => false,
                };

                if ($alasan === null) {
                    $hasil['sudah']++;

                    continue;
                }

                if ($alasan !== false) {
                    $lewati($alasan);

                    continue;
                }

                $terima = $this->waktu($r->receive_datetime) ?? $this->waktu($r->receive_date);
                $depo = $r->depo_id ?? $this->depo($r->karyawan_id);

                if (! $terima || ! $depo) {
                    $lewati(! $terima ? 'tanpa tanggal terima' : 'depo tidak dikenali');

                    continue;
                }

                if (! isset($header[$no])) {
                    $kembaliPertama = collect($retur[$no] ?? [])->map(fn ($x) => $this->waktu($x->return_datetime) ?? $this->waktu($x->return_date))->filter()->min();
                    $id = DB::table('trx_canvassing')->insertGetId([
                        'nomor' => mb_substr($no, 0, 50),
                        'karyawan_id' => (int) $r->karyawan_id,
                        'depo_id' => (int) $depo,
                        'tanggal' => $r->receive_date ? substr((string) $r->receive_date, 0, 10) : $terima->toDateString(),
                        'status' => $kembaliPertama || HalamanParser::aktif($r->returned === null ? null : (string) $r->returned) ? 'dikembalikan' : 'diterima',
                        'disiapkan_at' => $terima->utc(),
                        'diterima_at' => $terima->utc(),
                        'dikembalikan_at' => $kembaliPertama?->utc(),
                        'sumber' => 'lama',
                        'received_at' => now(),
                    ]);
                    $header[$no] = (object) ['id' => $id, 'nomor' => $no, 'sumber' => 'lama'];
                }

                $qtyKembali = collect($retur[$no] ?? [])->where('produk_id', $r->produk_id)->sum(fn ($x) => $this->angka($x->return_qty));
                $dikembalikan = isset($retur[$no]) || HalamanParser::aktif($r->returned === null ? null : (string) $r->returned);
                $item[] = [
                    'canvassing_id' => $header[$no]->id,
                    'produk_id' => (int) $r->produk_id,
                    'qty_bawa' => $this->angka($r->total_qty),
                    'qty_diterima' => $this->angka($r->total_qty),
                    'qty_kembali' => $dikembalikan ? ($qtyKembali ?: $this->angka($r->return_qty)) : null,
                    'sumber_id' => $r->source_id,
                ];
            }

            // Item ganda (nomor + produk sama) diabaikan database; yang pertama dipakai.
            $hasil['dipindah'] += $item ? DB::table('trx_canvassing_item')->insertOrIgnore($item) : 0;
        });

        // Penjualan lama tanpa canvassing: sambungkan ke canvassing lama sales itu di tanggal yang sama.
        DB::table('trx_canvassing')->where('sumber', 'lama')->orderBy('id')
            ->each(fn ($c) => DB::table('trx_penjualan')->where('sumber', 'lama')->whereNull('canvassing_id')
                ->where('karyawan_id', $c->karyawan_id)->where('tanggal', $c->tanggal)->update(['canvassing_id' => $c->id]));

        return $hasil;
    }

    /**
     * Kunci kunjungan lama: sales + outlet + jam checkin (UTC). Dipakai untuk mencari / membuat trx_kunjungan.
     *
     * @return array<string, mixed>
     */
    private function kunciKunjungan(int $karyawan, int $outlet, mixed $depo, CarbonImmutable $masuk, ?CarbonImmutable $keluar, ?float $lat, ?float $lng, string $tanggal): array
    {
        $jam = $masuk->utc()->format('Y-m-d H:i:s');

        return [
            'kunci' => "{$karyawan}|{$outlet}|{$jam}",
            'karyawan_id' => $karyawan,
            'outlet_id' => $outlet,
            'depo_id' => $depo ? (int) $depo : $this->depo($karyawan),
            'tanggal' => $tanggal,
            'checkin_at' => $jam,
            'checkout_at' => $keluar?->utc()->format('Y-m-d H:i:s'),
            'durasi_detik' => $keluar && $keluar->gte($masuk) ? (int) $masuk->diffInSeconds($keluar) : null,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * ID trx_kunjungan untuk tiap kunci; kunjungan yang belum ada dibuat (sumber "lama", tanpa sumber_id).
     *
     * @param  list<array<string, mixed>>  $daftar
     * @return array<string, int>
     */
    private function kunjunganUntuk(array $daftar): array
    {
        $unik = collect($daftar)->keyBy('kunci');
        $cari = fn () => DB::table('trx_kunjungan')
            ->whereIn('karyawan_id', $unik->pluck('karyawan_id')->unique()->values())
            ->whereIn('checkin_at', $unik->pluck('checkin_at')->unique()->values())
            ->get(['id', 'karyawan_id', 'outlet_id', 'checkin_at'])
            ->mapWithKeys(fn ($k) => [$k->karyawan_id.'|'.$k->outlet_id.'|'.substr((string) $k->checkin_at, 0, 19) => (int) $k->id])
            ->all();
        $ada = $cari();
        $baru = $unik->reject(fn ($k, $kunci) => isset($ada[$kunci]))->map(fn ($k) => Arr::except($k, ['kunci']) + [
            'sumber' => 'lama', 'client_ref' => (string) Str::uuid(), 'recorded_at' => $k['checkin_at'], 'received_at' => now(),
        ])->values();

        foreach ($baru->chunk(500) as $potong) {
            DB::table('trx_kunjungan')->insert($potong->all());
        }

        return $baru->isEmpty() ? $ada : $cari();
    }

    /** Tulis kemajuan tiap 20.000 baris supaya proses panjang di hosting terlihat berjalan. */
    private function kemajuan(string $lama, int $sudah): void
    {
        if ($sudah % (self::POTONGAN * 20) !== 0) {
            return;
        }

        $total = $this->totalBaris[$lama] ??= DB::table($lama)->count();
        ($this->log)(sprintf('  %s: %s / %s baris (%d%%)', $lama, number_format($sudah, 0, ',', '.'),
            number_format($total, 0, ',', '.'), $total ? (int) floor($sudah / $total * 100) : 100));
    }

    /** Angka dari kolom lama (numerik atau teks berformat Indonesia). */
    private function angka(mixed $nilai): float
    {
        if ($nilai === null || $nilai === '') {
            return 0.0;
        }

        return is_numeric($nilai) ? (float) $nilai : (float) (HalamanParser::angka((string) $nilai) ?? 0);
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

        DB::table($lama)->orderBy('id')->chunkById(self::POTONGAN, function ($baris) use ($trx, $petakan, $lewati, &$hasil, $sekarang, $bentrok, $lama) {
            $hasil['sumber'] += $baris->count();
            $this->kemajuan($lama, $hasil['sumber']);
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
