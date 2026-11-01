<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Mobile\Pengaturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Tanda motoris: koordinator menandai motoris yang berpotensi curang (penjualan fiktif, lokasi palsu, dll.) dari drawer
// Riwayat Lacak Motoris. Tanda melekat pada motoris + tanggal yang dilihat. Hak mengikuti menu tracking_sales:
// Lihat = melihat tanda, Tambah = memberi tanda dan mencabut tanda sendiri, Hapus = mencabut tanda siapa pun.
// Hanya motoris dalam cakupan role akun; pencabutan soft supaya jejak tetap ada.
class TandaMotorisController extends Controller
{
    private const MENU = 'tracking_sales';

    /** Tanda aktif satu motoris pada satu tanggal. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
        ]);

        $motoris = $this->motoris($request, (int) $data['sales_id']);

        $tanggal = substr($data['tanggal'], 0, 10);
        $tanda = DB::table('tanda_motoris')
            ->where('karyawan_id', $motoris->id)->where('tanggal', $tanggal)->whereNull('dicabut_at')
            ->orderBy('id')->get()
            ->map(fn ($t) => $this->bentuk($t, $request->user()));

        // Tanda aktif motoris yang sama pada tanggal lain dalam jendela hari terakhir, supaya pola lintas hari terlihat.
        $awal = Pengaturan::sekarang()->subDays((int) config('marketama.tanda_motoris_hari') - 1)->toDateString();
        $lainnya = DB::table('tanda_motoris')
            ->where('karyawan_id', $motoris->id)->whereNull('dicabut_at')->where('tanggal', '>=', $awal)->where('tanggal', '<>', $tanggal)
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'tanggal' => substr((string) $t->tanggal, 0, 10),
                'kategori_label' => $this->label($t->kategori),
                'oleh' => $t->dibuat_nama,
            ]);

        return response()->json([
            'tanda' => $tanda->values(),
            'lainnya' => $lainnya->values(),
            'bisa_tambah' => Akses::bisa($request->user(), self::MENU, 'tambah'),
            'kategori' => config('marketama.tanda_motoris'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date', 'before_or_equal:'.Pengaturan::sekarang()->toDateString()],
            'kategori' => ['required', Rule::in(array_keys(config('marketama.tanda_motoris')))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $motoris = $this->motoris($request, (int) $data['sales_id']);
        $user = $request->user();
        $tanggal = substr($data['tanggal'], 0, 10);

        // Satu motoris hanya punya satu koordinator, jadi tanda kembar (kategori sama, tanggal sama) bukan penegasan orang kedua.
        $sudah = DB::table('tanda_motoris')->where('karyawan_id', $motoris->id)->where('tanggal', $tanggal)
            ->where('kategori', $data['kategori'])->whereNull('dicabut_at')->first();

        if ($sudah) {
            throw ValidationException::withMessages(['kategori' => "Kategori ini sudah ditandai oleh {$sudah->dibuat_nama} pada ".JamLokal::format($sudah->created_at, 'Y-m-d H:i').'.']);
        }

        $id = DB::table('tanda_motoris')->insertGetId([
            'karyawan_id' => $motoris->id,
            'tanggal' => $tanggal,
            'kategori' => $data['kategori'],
            'catatan' => $data['catatan'] ?? null,
            'dibuat_oleh' => $user->id,
            'dibuat_nama' => $user->username ?? $user->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Akses::catat($user, 'tanda_motoris', $id, 'tambah', "Tandai {$motoris->nama} ({$tanggal}): ".$this->label($data['kategori']));

        return response()->json($this->bentuk(DB::table('tanda_motoris')->find($id), $user), 201);
    }

    /** Cabut (soft) satu tanda: hanya pemberinya atau akun dengan hak Hapus. */
    public function cabut(Request $request, int $tanda): JsonResponse
    {
        $user = $request->user();
        $baris = DB::table('tanda_motoris')->where('id', $tanda)->whereNull('dicabut_at')->first();

        abort_unless($baris, 404);

        $motoris = $this->motoris($request, (int) $baris->karyawan_id);

        abort_unless($this->bolehCabut($baris, $user), 403, 'Anda tidak boleh mencabut tanda ini.');

        // Dua pencabut bersamaan: hanya yang pertama berhasil.
        $berubah = DB::table('tanda_motoris')->where('id', $tanda)->whereNull('dicabut_at')
            ->update(['dicabut_at' => now(), 'dicabut_oleh' => $user->id, 'updated_at' => now()]);

        abort_unless($berubah, 404);

        Akses::catat($user, 'tanda_motoris', $tanda, 'hapus', "Cabut tanda {$motoris->nama} (".substr((string) $baris->tanggal, 0, 10).'): '.$this->label($baris->kategori));

        return response()->json(['ok' => true]);
    }

    /** Motoris (kategori sales) dalam cakupan role akun; selain itu 404. */
    private function motoris(Request $request, int $id): object
    {
        $motoris = Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.id', $id)->where('k.kategori', 'sales'), $request->user())
            ->first(['k.id', 'k.nama']);

        abort_unless($motoris, 404);

        return $motoris;
    }

    private function label(string $kategori): string
    {
        return config("marketama.tanda_motoris.{$kategori}", $kategori);
    }

    private function bolehCabut(object $tanda, ?User $user): bool
    {
        return $tanda->dicabut_at === null && (
            Akses::bisa($user, self::MENU, 'hapus')
            || ((int) $tanda->dibuat_oleh === $user?->id && Akses::bisa($user, self::MENU, 'tambah'))
        );
    }

    /** @return array<string, mixed> */
    private function bentuk(object $tanda, ?User $user): array
    {
        return [
            'id' => (int) $tanda->id,
            'kategori' => $tanda->kategori,
            'kategori_label' => $this->label($tanda->kategori),
            'catatan' => $tanda->catatan,
            'oleh' => $tanda->dibuat_nama,
            'waktu' => JamLokal::format($tanda->created_at, 'Y-m-d H:i'),
            'bisa_cabut' => $this->bolehCabut($tanda, $user),
        ];
    }
}
