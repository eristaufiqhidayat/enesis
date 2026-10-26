<?php

namespace App\Support\Backup;

use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

// Membuat file backup satu baris backup_data:
// - CSV: satu file .csv per tabel (UTF-8 + BOM, judul kolom opsional, pemisah koma/titik koma) dibungkus .zip;
// - SQL: CREATE TABLE (opsional) + DROP TABLE (opsional) + INSERT per 200 baris, satu .sql.gz atau .zip per tabel.
// Kolom rahasia (password, token) dikosongkan bila diminta. Tabel trx_* / lap_* bisa dibatasi rentang tanggal.
// Kemajuan (tabel sekarang, jumlah selesai) ditulis ke backup_data supaya halaman bisa menampilkan progres.
class PembuatBackup
{
    public const FOLDER = 'backup';

    public const MASA_SIMPAN_HARI = 7;

    private const KOLOM_RAHASIA = ['password', 'remember_token', 'token_hash', 'api_token', 'token'];

    /** Kolom tanggal yang dipakai untuk rentang, dicoba berurutan. */
    private const KOLOM_TANGGAL = ['tanggal', 'date_trans', 'date_checkin', 'attendance_date', 'receive_date', 'return_date',
        'inspection_date', 'date_leave', 'date', 'tanggal_data', 'recorded_at'];

    private const BARIS_PER_INSERT = 200;

    private object $backup;

    private array $opsi;

    private string $folderKerja;

    public function __construct(private readonly int $id) {}

    /** Jalankan bila masih menunggu (diklaim atomik supaya tidak diproses dua kali). */
    public function jalankan(): void
    {
        $diklaim = DB::table('backup_data')->where('id', $this->id)->where('status', 'menunggu')
            ->update(['status' => 'proses', 'mulai_at' => now(), 'updated_at' => now()]);

        if (! $diklaim) {
            return;
        }

        @set_time_limit(0);
        @ignore_user_abort(true);
        $this->backup = DB::table('backup_data')->find($this->id);
        $this->opsi = json_decode($this->backup->opsi, true) ?: [];
        $tabel = DaftarTabel::saring(json_decode($this->backup->tabel, true) ?: []);

        try {
            Storage::disk('local')->makeDirectory(self::FOLDER);
            $this->folderKerja = Storage::disk('local')->path(self::FOLDER.'/tmp-'.$this->id);
            @mkdir($this->folderKerja, 0775, true);

            [$nama, $baris] = $this->backup->format === 'csv' ? $this->csv($tabel) : $this->sql($tabel);
            $path = Storage::disk('local')->path(self::FOLDER.'/'.$nama);

            DB::table('backup_data')->where('id', $this->id)->update([
                'status' => 'selesai', 'tabel_selesai' => count($tabel), 'tabel_sekarang' => null, 'baris' => $baris,
                'nama_file' => $nama, 'ukuran' => is_file($path) ? filesize($path) : null,
                'selesai_at' => now(), 'kedaluwarsa_at' => now()->addDays(self::MASA_SIMPAN_HARI), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            DB::table('backup_data')->where('id', $this->id)->update([
                'status' => 'gagal', 'pesan' => mb_substr($e->getMessage(), 0, 1000), 'selesai_at' => now(), 'updated_at' => now(),
            ]);
        } finally {
            $this->hapusFolder($this->folderKerja ?? null);
        }
    }

    public static function namaDasar(object $backup): string
    {
        return 'enesis_backup_'.CarbonImmutable::parse($backup->created_at, 'UTC')->setTimezone(Pengaturan::zona())->format('Y-m-d_Hi').'_'.$backup->id;
    }

    // ------------------------------------------------------------------

    /** @return array{0: string, 1: int} nama file & jumlah baris */
    private function csv(array $tabel): array
    {
        $nama = self::namaDasar($this->backup).'_csv.zip';
        $pemisah = ($this->opsi['pemisah'] ?? 'koma') === 'titikkoma' ? ';' : ',';
        $total = 0;
        $file = [];

        foreach ($tabel as $i => $t) {
            $this->kemajuan($i, $t, $total);
            $path = $this->folderKerja.'/'.$t.'.csv';
            $h = fopen($path, 'w');
            fwrite($h, "\xEF\xBB\xBF");
            $kolom = Schema::getColumnListing($t);

            if ($this->opsi['header'] ?? true) {
                fputcsv($h, $kolom, $pemisah, '"', '');
            }

            $total += $this->baris($t, function (array $b) use ($h, $kolom, $pemisah) {
                fputcsv($h, array_map(fn ($k) => $b[$k] ?? null, $kolom), $pemisah, '"', '');
            });
            fclose($h);
            $file[$t.'.csv'] = $path;
        }

        $this->zip($nama, $file);

        return [$nama, $total];
    }

    /** @return array{0: string, 1: int} */
    private function sql(array $tabel): array
    {
        $perTabel = (bool) ($this->opsi['per_tabel'] ?? false);
        $nama = self::namaDasar($this->backup).($perTabel ? '_sql.zip' : '.sql.gz');
        $total = 0;
        $file = [];
        $gz = $perTabel ? null : gzopen(Storage::disk('local')->path(self::FOLDER.'/'.$nama), 'wb6');
        $tulis = $gz ? fn (string $s) => gzwrite($gz, $s) : null;

        if ($gz) {
            $tulis($this->kepalaSql(count($tabel)));
        }

        foreach ($tabel as $i => $t) {
            $this->kemajuan($i, $t, $total);

            if ($perTabel) {
                $path = $this->folderKerja.'/'.$t.'.sql';
                $h = fopen($path, 'w');
                $tulis = fn (string $s) => fwrite($h, $s);
                $tulis($this->kepalaSql(1));
            }

            $total += $this->sqlTabel($t, $tulis);

            if ($perTabel) {
                $tulis($this->ekorSql());
                fclose($h);
                $file[$t.'.sql'] = $path;
            }
        }

        if ($gz) {
            $tulis($this->ekorSql());
            gzclose($gz);
        } else {
            $this->zip($nama, $file);
        }

        return [$nama, $total];
    }

    private function sqlTabel(string $t, \Closure $tulis): int
    {
        $grammar = DB::getQueryGrammar();
        $namaTabel = $grammar->wrapTable($t);
        $tulis("\n-- Tabel {$t}\n");

        if ($this->opsi['drop'] ?? false) {
            $tulis("DROP TABLE IF EXISTS {$namaTabel};\n");
        }

        if ($this->opsi['struktur'] ?? true) {
            $tulis($this->createTable($t).";\n");
        }

        $kolom = Schema::getColumnListing($t);
        $daftarKolom = implode(', ', array_map(fn ($k) => $grammar->wrap($k), $kolom));
        $pdo = DB::getPdo();
        $antre = [];
        $kirim = function () use (&$antre, $tulis, $namaTabel, $daftarKolom) {
            if ($antre) {
                $tulis("INSERT INTO {$namaTabel} ({$daftarKolom}) VALUES\n".implode(",\n", $antre).";\n");
                $antre = [];
            }
        };

        $n = $this->baris($t, function (array $b) use (&$antre, $kolom, $pdo, $kirim) {
            $antre[] = '('.implode(', ', array_map(fn ($k) => match (true) {
                ! array_key_exists($k, $b) || $b[$k] === null => 'NULL',
                is_bool($b[$k]) => $b[$k] ? '1' : '0',
                is_int($b[$k]) || is_float($b[$k]) => (string) $b[$k],
                default => $pdo->quote((string) $b[$k]),
            }, $kolom)).')';

            if (count($antre) >= self::BARIS_PER_INSERT) {
                $kirim();
            }
        });
        $kirim();

        return $n;
    }

    private function createTable(string $t): string
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $r = (array) DB::selectOne('SHOW CREATE TABLE '.DB::getQueryGrammar()->wrapTable($t));

            return (string) ($r['Create Table'] ?? array_values($r)[1]);
        }

        if ($driver === 'sqlite') {
            return (string) DB::table('sqlite_master')->where('type', 'table')->where('name', $t)->value('sql');
        }

        // PostgreSQL & lainnya: dari daftar kolom + primary key.
        $g = DB::getQueryGrammar();
        $baris = collect(Schema::getColumns($t))->map(fn ($c) => '  '.$g->wrap($c['name']).' '.$c['type']
            .($c['nullable'] ? '' : ' NOT NULL').($c['default'] !== null ? ' DEFAULT '.$c['default'] : ''));
        $pk = collect(Schema::getIndexes($t))->firstWhere('primary', true);

        if ($pk) {
            $baris->push('  PRIMARY KEY ('.implode(', ', array_map(fn ($k) => $g->wrap($k), $pk['columns'])).')');
        }

        return 'CREATE TABLE '.$g->wrapTable($t)." (\n".$baris->implode(",\n")."\n)";
    }

    private function kepalaSql(int $jumlah): string
    {
        $mysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        return "-- Backup Enesis #{$this->backup->id} · {$jumlah} tabel · dibuat ".now()->setTimezone(Pengaturan::zona())->format('Y-m-d H:i')." WIB\n"
            .'-- Database: '.DB::getDriverName()."\n"
            .($mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n" : '');
    }

    private function ekorSql(): string
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? "\nSET FOREIGN_KEY_CHECKS = 1;\n" : '';
    }

    /**
     * Baca semua baris tabel (sesuai rentang tanggal) per potongan; kolom rahasia dikosongkan.
     *
     * @param  \Closure(array<string, mixed>): void  $tiap
     */
    private function baris(string $t, \Closure $tiap): int
    {
        $kolom = Schema::getColumnListing($t);
        $rahasia = ($this->opsi['rahasia'] ?? true) ? array_values(array_intersect($kolom, self::KOLOM_RAHASIA)) : [];
        $q = $this->query($t, $kolom);
        $urut = in_array('id', $kolom, true) ? 'id' : ($kolom[0] ?? null);
        $n = 0;
        $tiapBaris = function ($b) use ($tiap, $rahasia, &$n) {
            $b = (array) $b;
            foreach ($rahasia as $k) {
                $b[$k] = $b[$k] === null ? null : '';
            }
            $tiap($b);
            $n++;
        };

        if ($urut === 'id') {
            $q->lazyById(1000, 'id')->each($tiapBaris);
        } elseif ($urut) {
            $q->orderBy($urut)->lazy(1000)->each($tiapBaris);
        }

        return $n;
    }

    private function query(string $t, array $kolom): Builder
    {
        $q = DB::table($t);
        $dari = $this->opsi['dari'] ?? null;
        $sampai = $this->opsi['sampai'] ?? null;

        if ($dari && $sampai && (str_starts_with($t, 'trx_') || str_starts_with($t, 'lap_'))) {
            $k = collect(self::KOLOM_TANGGAL)->first(fn ($c) => in_array($c, $kolom, true));

            if ($k) {
                $q->whereBetween($k, $k === 'recorded_at' ? ["{$dari} 00:00:00", "{$sampai} 23:59:59"] : [$dari, $sampai]);
            }
        }

        return $q;
    }

    private function kemajuan(int $selesai, string $tabel, int $baris): void
    {
        DB::table('backup_data')->where('id', $this->id)
            ->update(['tabel_selesai' => $selesai, 'tabel_sekarang' => $tabel, 'baris' => $baris, 'updated_at' => now()]);
    }

    /** @param  array<string, string>  $file  nama di zip => path */
    private function zip(string $nama, array $file): void
    {
        $zip = new ZipArchive;

        if ($zip->open(Storage::disk('local')->path(self::FOLDER.'/'.$nama), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File zip tidak bisa dibuat.');
        }

        foreach ($file as $di => $path) {
            $zip->addFile($path, $di);
        }

        $zip->close();
    }

    private function hapusFolder(?string $folder): void
    {
        if ($folder && is_dir($folder)) {
            array_map('unlink', glob($folder.'/*') ?: []);
            @rmdir($folder);
        }
    }

    /** Hapus file backup yang sudah kedaluwarsa; kembalikan jumlahnya. */
    public static function bersihkan(): int
    {
        $n = 0;

        foreach (DB::table('backup_data')->where('status', 'selesai')->where('kedaluwarsa_at', '<', now())->get() as $b) {
            if ($b->nama_file) {
                Storage::disk('local')->delete(self::FOLDER.'/'.$b->nama_file);
            }

            DB::table('backup_data')->where('id', $b->id)->update(['status' => 'kedaluwarsa', 'updated_at' => now()]);
            $n++;
        }

        return $n;
    }
}
