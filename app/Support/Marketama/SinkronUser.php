<?php

namespace App\Support\Marketama;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Bangun akun login Enesis (tabel users) dari akun aplikasi lama (mk_user) dan karyawannya
// (mk_karyawan), dengan aturan yang disetujui:
//  1. Akun aktif milik karyawan nonaktif -> nonaktif.
//  2. Username kembar (tanpa beda huruf besar/kecil): satu akun aktif memakai username itu;
//     akun lain diberi akhiran "_<id>". Bila ada >1 akun aktif -> perlu keputusan admin.
//  3. Karyawan dengan >1 akun aktif -> semua ditandai perlu keputusan admin (tetap aktif).
// Password lama tidak bisa diambil: akun baru diberi password acak + wajib ganti.
// Aman dijalankan ulang; password yang sudah diatur tidak ditimpa.
class SinkronUser
{
    /**
     * @return array{dibuat:int, diperbarui:int, aktif:int, nonaktif_karyawan:int, username_diganti:int, perlu_keputusan:int}
     */
    public function jalankan(): array
    {
        $karyawan = DB::table('mk_karyawan')->get(['id', 'nama', 'aktif'])->keyBy('id');
        $akun = DB::table('mk_user')->orderBy('jenis')->orderBy('id')->get();

        $baris = [];

        foreach ($akun as $u) {
            $k = $u->karyawan_id ? $karyawan->get($u->karyawan_id) : null;
            $catatan = [];
            $aktif = (bool) $u->aktif;

            if ($aktif && $k && $k->aktif !== null && ! $k->aktif) {
                $aktif = false;
                $catatan[] = 'dinonaktifkan: karyawan sudah tidak aktif';
            }

            if (! $u->karyawan_id) {
                $catatan[] = 'tidak tersambung ke karyawan';
            }

            $baris[] = [
                'sumber_jenis' => $u->jenis,
                'sumber_id' => (int) $u->id,
                'username_asli' => trim((string) $u->username),
                'username' => trim((string) $u->username),
                'name' => $k->nama ?? $u->nickname ?? $u->username,
                'kategori' => $u->jenis,
                'karyawan_id' => $u->karyawan_id ? (int) $u->karyawan_id : null,
                'aktif' => $aktif,
                'perlu_keputusan' => false,
                'catatan' => $catatan,
            ];
        }

        $usernameDiganti = $this->aturUsernameKembar($baris);
        $this->tandaiAkunAktifGanda($baris);

        return $this->simpan($baris) + [
            'aktif' => count(array_filter($baris, fn ($b) => $b['aktif'])),
            'nonaktif_karyawan' => count(array_filter($baris, fn ($b) => in_array('dinonaktifkan: karyawan sudah tidak aktif', $b['catatan'], true))),
            'username_diganti' => $usernameDiganti,
            'perlu_keputusan' => count(array_filter($baris, fn ($b) => $b['perlu_keputusan'])),
        ];
    }

    /** Username unik tanpa beda huruf besar/kecil. Mengembalikan jumlah username yang diganti. */
    private function aturUsernameKembar(array &$baris): int
    {
        $grup = [];

        foreach ($baris as $i => $b) {
            $grup[mb_strtolower($b['username'])][] = $i;
        }

        $diganti = 0;

        // Username akun yang dibuat manual (bukan hasil migrasi) tidak boleh diambil.
        $dipakaiManual = DB::table('users')->whereNull('sumber_jenis')->whereNotNull('username')->pluck('username')
            ->map(fn ($u) => mb_strtolower($u))->flip();

        foreach ($baris as $i => $b) {
            if (isset($dipakaiManual[mb_strtolower($b['username'])])) {
                $baris[$i]['username'] = $b['username_asli'].'_'.$b['sumber_id'];
                $baris[$i]['catatan'][] = "username diganti (asli: {$b['username_asli']}, dipakai akun lain)";
                $diganti++;
            }
        }

        foreach ($grup as $indeks) {
            if (count($indeks) < 2) {
                continue;
            }

            $aktif = array_values(array_filter($indeks, fn ($i) => $baris[$i]['aktif']));
            // Pemakai username: akun aktif (yang pertama bila >1), atau akun pertama bila semua nonaktif.
            $pemakai = $aktif[0] ?? $indeks[0];

            foreach ($indeks as $i) {
                if (count($aktif) > 1 && $baris[$i]['aktif']) {
                    $baris[$i]['perlu_keputusan'] = true;
                    $baris[$i]['catatan'][] = 'username kembar dengan akun aktif lain';
                }

                if ($i === $pemakai) {
                    continue;
                }

                $baris[$i]['username'] = $baris[$i]['username_asli'].'_'.$baris[$i]['sumber_id'];
                $baris[$i]['catatan'][] = "username diganti (asli: {$baris[$i]['username_asli']})";
                $diganti++;
            }
        }

        return $diganti;
    }

    private function tandaiAkunAktifGanda(array &$baris): void
    {
        $perKaryawan = [];

        foreach ($baris as $i => $b) {
            if ($b['aktif'] && $b['karyawan_id']) {
                $perKaryawan[$b['karyawan_id']][] = $i;
            }
        }

        foreach ($perKaryawan as $indeks) {
            if (count($indeks) > 1) {
                foreach ($indeks as $i) {
                    $baris[$i]['perlu_keputusan'] = true;
                    $baris[$i]['catatan'][] = 'karyawan punya lebih dari satu akun aktif';
                }
            }
        }
    }

    /** @return array{dibuat:int, diperbarui:int} */
    private function simpan(array $baris): array
    {
        $ada = DB::table('users')->whereNotNull('sumber_jenis')->get(['id', 'sumber_jenis', 'sumber_id'])
            ->keyBy(fn ($r) => $r->sumber_jenis.'|'.$r->sumber_id);
        $now = now();
        $dibuat = 0;
        $diperbarui = 0;

        DB::transaction(function () use ($baris, $ada, $now, &$dibuat, &$diperbarui) {
            // Kosongkan dulu username akun hasil migrasi supaya pertukaran username tidak bentrok unik.
            // (NULL boleh berulang pada indeks unik di SQLite, MySQL, dan PostgreSQL.)
            DB::table('users')->whereNotNull('sumber_jenis')->update(['username' => null]);

            foreach ($baris as $b) {
                $data = [
                    'name' => $b['name'],
                    'username' => $b['username'],
                    'kategori' => $b['kategori'],
                    'karyawan_id' => $b['karyawan_id'],
                    'aktif' => $b['aktif'],
                    'perlu_keputusan' => $b['perlu_keputusan'],
                    'catatan_migrasi' => $b['catatan'] ? Str::limit(implode('; ', $b['catatan']), 250) : null,
                    'updated_at' => $now,
                ];

                $kunci = $b['sumber_jenis'].'|'.$b['sumber_id'];

                if ($lama = $ada->get($kunci)) {
                    DB::table('users')->where('id', $lama->id)->update($data);
                    $diperbarui++;

                    continue;
                }

                DB::table('users')->insert($data + [
                    'sumber_jenis' => $b['sumber_jenis'],
                    'sumber_id' => $b['sumber_id'],
                    'email' => null,
                    // Password acak yang tidak diketahui siapa pun; admin mengatur password awal.
                    'password' => Hash::make(Str::random(40)),
                    'wajib_ganti_password' => true,
                    'created_at' => $now,
                ]);
                $dibuat++;
            }
        });

        return ['dibuat' => $dibuat, 'diperbarui' => $diperbarui];
    }
}
