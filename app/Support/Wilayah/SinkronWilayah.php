<?php

namespace App\Support\Wilayah;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Bandingkan master wilayah Enesis (provinsi, kota_kabupaten, kecamatan, kelurahan — salinan Marketama, berelasi
// lewat nama) dengan data resmi Kemendagri dari API relasi BPS, lalu (opsional) perbarui.
//
// Pencocokan lewat kode Kemendagri. Status per baris:
//   cocok        kode ada di data resmi, nama & induk sama
//   beda_nama    kode ada, nama berbeda (selain gaya penulisan)
//   beda_induk   kode ada, tapi tercatat di bawah induk lain (mis. kecamatan Kutai Barat di Mahakam Ulu)
//   baru         ada di data resmi, belum ada di Enesis
//   tidak_resmi  kode Enesis tidak ada di data resmi (mis. "Kabupaten Poso Pesisir")
//   tambahan     baris tanpa kode (dibuat Marketama dari alamat outlet) — tidak disentuh
//   tidak_dicek  induknya gagal diambil dari API, jadi belum bisa dinilai
//
// ID baris lama tidak pernah berubah dan tidak ada baris yang dihapus, karena outlet/area/depo merujuk ke master ini.
class SinkronWilayah
{
    public const TINGKAT = [
        'provinsi' => ['tabel' => 'provinsi', 'nama' => 'nama_provinsi', 'level' => 'provinsi', 'induk' => []],
        'kota' => ['tabel' => 'kota_kabupaten', 'nama' => 'nama_kota_kabupaten', 'level' => 'kabupaten', 'induk' => ['provinsi']],
        'kecamatan' => ['tabel' => 'kecamatan', 'nama' => 'nama_kecamatan', 'level' => 'kecamatan', 'induk' => ['provinsi', 'kota_kabupaten']],
        'kelurahan' => ['tabel' => 'kelurahan', 'nama' => 'nama_kelurahan', 'level' => 'desa', 'induk' => ['provinsi', 'kota_kabupaten', 'kecamatan']],
    ];

    private const PANJANG = ['provinsi' => 2, 'kota' => 4, 'kecamatan' => 6, 'kelurahan' => 10];

    /** @var array<string, array<string, array>> tingkat → kode → baris resmi */
    private array $resmi = [];

    /** @var array<string, array<string, true>> tingkat → kode induk (Kemendagri) yang anaknya gagal diambil */
    private array $gagal = [];

    /** @var list<array> baris laporan */
    public array $laporan = [];

    /** @var array<string, array|null> nama induk per kode induk, dikosongkan tiap tingkat */
    private array $memoInduk = [];

    public function __construct(private WilayahBps $bps) {}

    /**
     * @param  list<string>  $provinsi  kode provinsi 2 digit; kosong = semua
     * @param  callable(string $pesan, ?int $total): void|null  $log  dipanggil per langkah (total = jumlah permintaan)
     * @param  callable(): void|null  $maju  dipanggil per permintaan API
     * @return array<string, array<string, int>> tingkat → status → jumlah (+ diubah/ditambah)
     */
    public function jalankan(array $provinsi = [], string $sampai = 'kelurahan', bool $simpan = false, bool $perbaiki = false, ?callable $log = null, ?callable $maju = null): array
    {
        $tingkat = array_keys(self::TINGKAT);
        $tingkat = array_slice($tingkat, 0, array_search($sampai, $tingkat, true) + 1);

        $this->ambilResmi($tingkat, $provinsi, $log, $maju);

        $ringkasan = [];
        foreach ($tingkat as $t) {
            $ringkasan[$t] = $this->bandingkan($t, $provinsi, $simpan, $perbaiki);
        }

        return $ringkasan;
    }

    /** Ambil data resmi dari API, level demi level. */
    private function ambilResmi(array $tingkat, array $provinsi, ?callable $log, ?callable $maju): void
    {
        $log && $log('Mengambil daftar provinsi', 1);
        $prov = $this->bps->anak('provinsi', [''], $maju)[''] ?? null;
        if ($prov === null) {
            throw new RuntimeException('Daftar provinsi dari API BPS tidak bisa diambil. Cek koneksi server ke sig.bps.go.id.');
        }
        if ($provinsi) {
            $prov = array_values(array_filter($prov, fn ($r) => in_array($r['kode'], $provinsi, true)));
        }
        $this->resmi['provinsi'] = $this->kunciKode($prov, 'provinsi');

        $sebelumnya = 'provinsi';
        foreach (array_slice($tingkat, 1) as $t) {
            $induk = $this->resmi[$sebelumnya];
            $perBps = [];
            foreach ($induk as $r) {
                if ($r['kode_bps'] !== '') {
                    $perBps[$r['kode_bps']] = $r['kode'];
                }
            }

            $log && $log('Mengambil '.$t.' ('.count($perBps).' permintaan)', count($perBps));
            $jawaban = $this->bps->anak(self::TINGKAT[$t]['level'], array_keys($perBps), $maju);

            $baris = [];
            $this->gagal[$t] = [];
            foreach ($jawaban as $kodeBps => $anak) {
                if ($anak === null) {
                    $this->gagal[$t][$perBps[$kodeBps]] = true;

                    continue;
                }
                array_push($baris, ...$anak);
            }
            // Induk tanpa kode BPS juga tidak bisa dinilai anaknya.
            foreach ($induk as $r) {
                if ($r['kode_bps'] === '') {
                    $this->gagal[$t][$r['kode']] = true;
                }
            }
            // Hanya anak yang awalan kodenya provinsi terpilih (relasi BPS bisa menyeberang induk).
            $baris = array_filter($baris, fn ($r) => ! $provinsi || in_array(substr($r['kode'], 0, 2), $provinsi, true));
            $this->resmi[$t] = $this->kunciKode($baris, $t);
            $sebelumnya = $t;
        }
    }

    private function kunciKode(array $baris, string $t): array
    {
        $hasil = [];
        foreach ($baris as $r) {
            if (strlen($r['kode']) === self::PANJANG[$t]) {
                $hasil[$r['kode']] ??= $r;
            }
        }
        ksort($hasil, SORT_STRING);

        return $hasil;
    }

    public static function kodeInduk(string $t, string $kode): ?string
    {
        return match ($t) {
            'provinsi' => null,
            'kota' => substr($kode, 0, 2),
            'kecamatan' => substr($kode, 0, 4),
            'kelurahan' => substr($kode, 0, 6),
        };
    }

    private static function tingkatInduk(string $t): ?string
    {
        $daftar = array_keys(self::TINGKAT);
        $i = array_search($t, $daftar, true);

        return $i > 0 ? $daftar[$i - 1] : null;
    }

    private function bandingkan(string $t, array $provinsi, bool $simpan, bool $perbaiki): array
    {
        $meta = self::TINGKAT[$t];
        $this->memoInduk = [];
        $resmi = $this->resmi[$t];
        $baris = $this->barisEnesis($t, $provinsi);
        $petaInduk = $this->petaIndukNama($t);
        $hitung = ['cocok' => 0, 'beda_nama' => 0, 'beda_induk' => 0, 'baru' => 0, 'tidak_resmi' => 0, 'tambahan' => 0, 'tidak_dicek' => 0, 'diubah' => 0, 'ditambah' => 0];
        $penanda = [];
        $perbaikan = [];
        $ada = [];
        $sekarang = now();

        foreach ($baris as $r) {
            $kode = preg_replace('/\D/', '', (string) $r->kode);
            if ($kode === '' || strlen($kode) !== self::PANJANG[$t]) {
                $hitung['tambahan']++;
                $this->catat($t, 'tambahan', $r, null);
                $penanda[] = ['id' => $r->id, 'kode_bps' => null, 'sumber' => 'tambahan', 'sinkron_at' => $sekarang];

                continue;
            }

            $ada[$kode] = true;
            $res = $resmi[$kode] ?? null;
            if (! $res) {
                $induk = self::kodeInduk($t, $kode);
                if ($induk !== null && isset($this->gagal[$t][$induk])) {
                    $hitung['tidak_dicek']++;

                    continue;
                }
                $hitung['tidak_resmi']++;
                $this->catat($t, 'tidak_resmi', $r, null);
                $penanda[] = ['id' => $r->id, 'kode_bps' => null, 'sumber' => 'tidak_resmi', 'sinkron_at' => $sekarang];

                continue;
            }

            $status = [];
            if (! NamaWilayah::sama($r->{$meta['nama']}, $res['nama'])) {
                $status[] = 'beda_nama';
            }
            if ($t !== 'provinsi' && ! in_array(self::kodeInduk($t, $kode), $petaInduk[$this->kunciInduk($t, $r)] ?? [], true)) {
                $status[] = 'beda_induk';
            }
            foreach ($status as $s) {
                $hitung[$s]++;
            }
            if (! $status) {
                $hitung['cocok']++;
            } else {
                $this->catat($t, implode('+', $status), $r, $res);
                $perbaikan[] = [$r, $res, $status];
            }
            $penanda[] = ['id' => $r->id, 'kode_bps' => $res['kode_bps'] ?: null, 'sumber' => 'resmi', 'sinkron_at' => $sekarang];
        }

        $baru = array_diff_key($resmi, $ada);
        $hitung['baru'] = count($baru);
        foreach ($baru as $res) {
            $this->catat($t, 'baru', null, $res);
        }

        if ($simpan) {
            DB::transaction(function () use ($t, $meta, $penanda, $perbaikan, $baru, $perbaiki, $sekarang, &$hitung) {
                foreach (array_chunk($penanda, 500) as $potongan) {
                    DB::table($meta['tabel'])->upsert($potongan, ['id'], ['kode_bps', 'sumber', 'sinkron_at']);
                }
                if ($perbaiki) {
                    foreach ($perbaikan as [$r, $res, $status]) {
                        $hitung['diubah'] += $this->perbaiki($t, $r, $res, in_array('beda_nama', $status, true));
                    }
                }
                $hitung['ditambah'] = $this->tambah($t, $baru, $sekarang);
            });
        }

        return $hitung;
    }

    /** Baris Enesis dalam cakupan provinsi terpilih (berkode berawalan provinsi, atau tanpa kode di provinsi itu). */
    private function barisEnesis(string $t, array $provinsi): Collection
    {
        $meta = self::TINGKAT[$t];
        $kolom = array_merge(['id', 'kode', $meta['nama']], $meta['induk']);
        $q = DB::table($meta['tabel'])->select($kolom)->orderBy('id');

        if ($provinsi) {
            $namaProv = DB::table('provinsi')->whereIn('kode', array_map('intval', $provinsi))->pluck('nama_provinsi')->all();
            $q->where(function ($q) use ($provinsi, $namaProv, $t) {
                foreach ($provinsi as $p) {
                    $q->orWhere('kode', 'like', $p.'%');
                }
                if ($t !== 'provinsi') {
                    $q->orWhere(fn ($q) => $q->where(fn ($q) => $q->whereNull('kode')->orWhere('kode', ''))->whereIn('provinsi', $namaProv));
                }
            });
        }

        return $q->get();
    }

    /** Kunci nama induk sebuah baris, mis. "jawabarat|bogor" untuk kecamatan. */
    private function kunciInduk(string $t, object $r): string
    {
        return implode('|', array_map(fn ($k) => NamaWilayah::kunci((string) $r->{$k}), self::TINGKAT[$t]['induk']));
    }

    /**
     * Peta kunci nama induk → kode-kode induk yang memakai nama itu (dari tabel induk saat ini).
     * Dipakai untuk menilai induk tanpa terpengaruh perubahan nama: baris benar bila induk bernamanya berkode sama
     * dengan awalan kodenya sendiri.
     */
    private function petaIndukNama(string $t): array
    {
        $induk = self::tingkatInduk($t);
        if (! $induk) {
            return [];
        }
        $meta = self::TINGKAT[$induk];
        $peta = [];
        DB::table($meta['tabel'])->select(array_merge(['kode', $meta['nama']], $meta['induk']))->whereNotNull('kode')
            ->orderBy('id')->each(function ($r) use (&$peta, $meta) {
                $kunci = implode('|', array_merge(
                    array_map(fn ($k) => NamaWilayah::kunci((string) $r->{$k}), $meta['induk']),
                    [NamaWilayah::kunci((string) $r->{$meta['nama']})],
                ));
                $peta[$kunci][] = preg_replace('/\D/', '', (string) $r->kode);
            }, 2000);

        return $peta;
    }

    /** Nama induk (kolom teks) untuk kode wilayah resmi, dari baris induk berkode di Enesis. */
    private function namaInduk(string $t, string $kode): ?array
    {
        $induk = self::tingkatInduk($t);
        if (! $induk) {
            return [];
        }
        $meta = self::TINGKAT[$induk];
        $kodeInduk = self::kodeInduk($t, $kode);
        if (array_key_exists($kodeInduk, $this->memoInduk)) {
            return $this->memoInduk[$kodeInduk];
        }
        $r = DB::table($meta['tabel'])->where('kode', in_array($induk, ['provinsi', 'kota'], true) ? (int) $kodeInduk : $kodeInduk)
            ->orderByRaw("case when sumber = 'resmi' then 0 else 1 end")->orderBy('id')->first();
        if (! $r) {
            return $this->memoInduk[$kodeInduk] = null;
        }
        $nama = [];
        foreach ($meta['induk'] as $k) {
            $nama[$k] = $r->{$k};
        }
        $nama[self::TINGKAT[$t]['induk'][count($meta['induk'])]] = $r->{$meta['nama']};

        return $this->memoInduk[$kodeInduk] = $nama;
    }

    /** Ubah nama/induk satu baris sesuai data resmi, dan bawa anak-anaknya (relasi lewat nama). */
    private function perbaiki(string $t, object $r, array $res, bool $gantiNama): int
    {
        $meta = self::TINGKAT[$t];
        $kode = preg_replace('/\D/', '', (string) $r->kode);
        $induk = $this->namaInduk($t, $kode);
        if ($induk === null) {
            return 0;
        }

        $baru = $induk + [$meta['nama'] => $gantiNama ? NamaWilayah::rapikan($res['nama']) : $r->{$meta['nama']}];
        DB::table($meta['tabel'])->where('id', $r->id)->update($baru);

        // Anak yang merujuk baris ini lewat nama ikut diganti. Anak berkode hanya bila awalan kodenya sesuai
        // (anak salah induk diperbaiki sendiri di tingkat berikutnya); anak tanpa kode (tambahan) selalu ikut.
        $lama = [];
        foreach ($meta['induk'] as $k) {
            $lama[$k] = $r->{$k};
        }
        $daftar = array_keys(self::TINGKAT);
        foreach (array_slice($daftar, array_search($t, $daftar, true) + 1) as $anak) {
            $kolomAnak = self::TINGKAT[$anak]['induk'];
            $kolomIni = $kolomAnak[count($meta['induk'])];
            DB::table(self::TINGKAT[$anak]['tabel'])
                ->where(function ($q) use ($lama, $kolomIni, $r, $meta) {
                    foreach ($lama as $k => $v) {
                        $q->where($k, $v);
                    }
                    $q->where($kolomIni, $r->{$meta['nama']});
                })
                ->where(fn ($q) => $q->whereNull('kode')->orWhere('kode', '')->orWhere('kode', 'like', $kode.'%'))
                ->update(array_merge(array_intersect_key($baru, array_flip($meta['induk'])), [$kolomIni => $baru[$meta['nama']]]));
        }

        return 1;
    }

    /** Tambah wilayah resmi yang belum ada di Enesis (source_id kosong = bukan dari Marketama). */
    private function tambah(string $t, array $baru, $sekarang): int
    {
        $meta = self::TINGKAT[$t];
        $data = [];
        foreach ($baru as $res) {
            $induk = $this->namaInduk($t, $res['kode']);
            if ($induk === null) {
                continue;
            }
            $data[] = $induk + [
                'kode' => in_array($t, ['provinsi', 'kota'], true) ? (int) $res['kode'] : $res['kode'],
                $meta['nama'] => NamaWilayah::rapikan($res['nama']),
                'kode_bps' => $res['kode_bps'] ?: null,
                'sumber' => 'resmi',
                'sinkron_at' => $sekarang,
            ];
        }
        foreach (array_chunk($data, 500) as $potongan) {
            DB::table($meta['tabel'])->insert($potongan);
        }

        return count($data);
    }

    private function catat(string $t, string $status, ?object $r, ?array $res): void
    {
        $meta = self::TINGKAT[$t];
        $kode = $r ? (string) $r->kode : $res['kode'];
        $this->laporan[] = [
            'tingkat' => $t,
            'status' => $status,
            'id' => $r->id ?? '',
            'kode' => $kode,
            'kode_bps' => $res['kode_bps'] ?? '',
            'nama_enesis' => $r ? $r->{$meta['nama']} : '',
            'nama_resmi' => $res ? NamaWilayah::rapikan($res['nama']) : '',
            'induk_enesis' => $r ? implode(' / ', array_map(fn ($k) => $r->{$k}, $meta['induk'])) : '',
            'induk_resmi' => $res ? $this->indukResmi($t, $res['kode']) : '',
        ];
    }

    /** Rantai nama induk menurut data resmi, mis. "Jawa Barat / Kabupaten Bogor". */
    private function indukResmi(string $t, string $kode): string
    {
        $nama = [];
        while (($induk = self::tingkatInduk($t)) && ($kodeInduk = self::kodeInduk($t, $kode))) {
            array_unshift($nama, isset($this->resmi[$induk][$kodeInduk]) ? NamaWilayah::rapikan($this->resmi[$induk][$kodeInduk]['nama']) : '?'.$kodeInduk);
            [$t, $kode] = [$induk, $kodeInduk];
        }

        return implode(' / ', $nama);
    }

    public function tulisLaporan(string $berkas): void
    {
        if (! is_dir(dirname($berkas))) {
            mkdir(dirname($berkas), 0775, true);
        }
        $f = fopen($berkas, 'w');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['tingkat', 'status', 'id', 'kode', 'kode_bps', 'nama_enesis', 'nama_resmi', 'induk_enesis', 'induk_resmi'], escape: '');
        foreach ($this->laporan as $baris) {
            fputcsv($f, array_values($baris), escape: '');
        }
        fclose($f);
    }
}
