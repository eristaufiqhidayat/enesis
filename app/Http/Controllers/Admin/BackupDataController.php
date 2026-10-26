<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BuatBackup;
use App\Support\Akses\Akses;
use App\Support\Backup\DaftarTabel;
use App\Support\Backup\PembuatBackup;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Master Data & Pengaturan › Backup Data: pilih tabel + format (CSV / SQL), file dibuat di latar belakang,
// diunduh dari riwayat selama 7 hari. Buat & unduh butuh hak "ekspor"; tiap backup & unduhan dicatat di riwayat admin.
class BackupDataController extends Controller
{
    public const MENU = 'backup_data';

    public function index(Request $request): Response
    {
        return Inertia::render('Backup/Index', [
            'slug' => self::MENU,
            'judul' => config('marketama.pages.'.self::MENU.'.title') ?? 'Backup Data',
            'grup' => Inertia::defer(fn () => DaftarTabel::grup(), 'grup'),
            'riwayat' => self::riwayat(),
            'masaSimpan' => PembuatBackup::MASA_SIMPAN_HARI,
            'hariIni' => Pengaturan::sekarang()->toDateString(),
            'bolehBuat' => Akses::bisa($request->user(), self::MENU, 'ekspor'),
            'bolehHapus' => Akses::bisa($request->user(), self::MENU, 'hapus'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:csv,sql'],
            'tabel' => ['required', 'array', 'min:1'],
            'tabel.*' => ['string', 'max:100'],
            'header' => ['boolean'],
            'pemisah' => ['in:koma,titikkoma'],
            'struktur' => ['boolean'],
            'drop' => ['boolean'],
            'per_tabel' => ['boolean'],
            'rahasia' => ['boolean'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ], ['tabel.required' => 'Pilih minimal satu tabel.', 'sampai.after_or_equal' => 'Tanggal sampai harus sesudah tanggal dari.']);

        $tabel = DaftarTabel::saring($data['tabel']);
        abort_if(! $tabel, 422, 'Tabel tidak dikenal.');

        $id = DB::table('backup_data')->insertGetId([
            'format' => $data['format'],
            'tabel' => json_encode($tabel),
            'opsi' => json_encode([
                'header' => (bool) ($data['header'] ?? true),
                'pemisah' => $data['pemisah'] ?? 'koma',
                'struktur' => (bool) ($data['struktur'] ?? true),
                'drop' => (bool) ($data['drop'] ?? false),
                'per_tabel' => (bool) ($data['per_tabel'] ?? false),
                'rahasia' => (bool) ($data['rahasia'] ?? true),
                'dari' => ($data['dari'] ?? null) && ($data['sampai'] ?? null) ? $data['dari'] : null,
                'sampai' => ($data['dari'] ?? null) && ($data['sampai'] ?? null) ? $data['sampai'] : null,
            ]),
            'status' => 'menunggu',
            'dibuat_oleh' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Akses::catat($request->user(), 'backup', $id, 'buat', strtoupper($data['format']).', '.count($tabel).' tabel');
        BuatBackup::dispatchAfterResponse($id);

        return redirect()->route('backup-data.index')->with('sukses', 'Backup #'.$id.' sedang dibuat ('.count($tabel).' tabel). Halaman ini memperbarui progres otomatis.');
    }

    public function unduh(Request $request, int $id): BinaryFileResponse
    {
        $b = DB::table('backup_data')->find($id) ?? abort(404);
        $path = PembuatBackup::FOLDER.'/'.$b->nama_file;
        abort_unless($b->status === 'selesai' && $b->nama_file && Storage::disk('local')->exists($path), 404, 'File backup tidak tersedia (belum selesai atau sudah kedaluwarsa).');

        Akses::catat($request->user(), 'backup', $id, 'unduh', $b->nama_file);

        return response()->download(Storage::disk('local')->path($path), $b->nama_file);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $b = DB::table('backup_data')->find($id) ?? abort(404);
        abort_if(in_array($b->status, ['menunggu', 'proses'], true), 409, 'Backup masih diproses.');

        if ($b->nama_file) {
            Storage::disk('local')->delete(PembuatBackup::FOLDER.'/'.$b->nama_file);
        }

        DB::table('backup_data')->where('id', $id)->delete();
        Akses::catat($request->user(), 'backup', $id, 'hapus', $b->nama_file);

        return redirect()->route('backup-data.index')->with('sukses', 'Backup #'.$id.' dihapus.');
    }

    /** @return list<array<string, mixed>> 30 backup terakhir untuk tabel riwayat (diperbarui berkala selama ada yang diproses). */
    private static function riwayat(): array
    {
        $zona = Pengaturan::zona();
        $jam = fn ($t) => $t ? CarbonImmutable::parse($t, 'UTC')->setTimezone($zona)->locale('id')->translatedFormat('d M Y, H:i') : null;

        return DB::table('backup_data as b')->leftJoin('users as u', 'u.id', '=', 'b.dibuat_oleh')
            ->orderByDesc('b.id')->limit(30)
            ->get(['b.*', 'u.username as oleh'])
            ->map(function ($b) use ($jam) {
                $tabel = json_decode($b->tabel, true) ?: [];
                $opsi = json_decode($b->opsi, true) ?: [];
                $kedaluwarsa = $b->status === 'kedaluwarsa' || ($b->status === 'selesai' && $b->kedaluwarsa_at && now()->gt($b->kedaluwarsa_at));

                return [
                    'id' => (int) $b->id,
                    'dibuat' => $jam($b->created_at),
                    'format' => strtoupper($b->format),
                    'jumlahTabel' => count($tabel),
                    'rentang' => ($opsi['dari'] ?? null) ? $opsi['dari'].' s/d '.$opsi['sampai'] : null,
                    'oleh' => $b->oleh ?? 'terminal',
                    'status' => $kedaluwarsa ? 'kedaluwarsa' : $b->status,
                    'tabelSelesai' => (int) $b->tabel_selesai,
                    'tabelSekarang' => $b->tabel_sekarang,
                    'baris' => (int) $b->baris,
                    'namaFile' => $b->nama_file,
                    'ukuran' => $b->ukuran !== null ? (int) $b->ukuran : null,
                    'sampai' => $jam($b->kedaluwarsa_at),
                    'pesan' => $b->pesan,
                ];
            })->all();
    }
}
