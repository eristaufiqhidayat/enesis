<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// Tanda outlet: koordinator menandai outlet yang berpotensi curang (outlet fiktif, alamat tidak sesuai, dll.) dari
// drawer Riwayat Lacak Motoris atau menu All Outlet. Berbeda dari tanda motoris, tanda ini melekat pada OUTLET
// (bukan per kunjungan/motoris/tanggal): kecurigaan terhadap outlet berlaku siapa pun yang mengunjunginya, jadi
// tidak dibatasi cakupan role seperti tanda motoris. Hak mengikuti menu outlet_all: Lihat = melihat tanda,
// Tambah = memberi tanda dan mencabut tanda sendiri, Hapus = mencabut tanda siapa pun. Pencabutan soft.
class TandaOutletController extends Controller
{
    private const MENU = 'outlet_all';

    /** Tanda aktif satu outlet. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'integer'],
        ]);

        $outlet = $this->outlet((int) $data['outlet_id']);

        $tanda = DB::table('tanda_outlet')
            ->where('outlet_id', $outlet->id)->whereNull('dicabut_at')
            ->orderBy('id')->get()
            ->map(fn ($t) => $this->bentuk($t, $request->user()));

        return response()->json([
            'tanda' => $tanda->values(),
            'bisa_tambah' => Akses::bisa($request->user(), self::MENU, 'tambah'),
            'kategori' => config('marketama.tanda_outlet'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'integer'],
            'kategori' => ['required', Rule::in(array_keys(config('marketama.tanda_outlet')))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $outlet = $this->outlet((int) $data['outlet_id']);
        $user = $request->user();

        $id = DB::table('tanda_outlet')->insertGetId([
            'outlet_id' => $outlet->id,
            'kategori' => $data['kategori'],
            'catatan' => $data['catatan'] ?? null,
            'dibuat_oleh' => $user->id,
            'dibuat_nama' => $user->username ?? $user->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Akses::catat($user, 'tanda_outlet', $id, 'tambah', "Tandai outlet {$outlet->nama}: ".$this->label($data['kategori']));

        return response()->json($this->bentuk(DB::table('tanda_outlet')->find($id), $user), 201);
    }

    /** Cabut (soft) satu tanda: hanya pemberinya atau akun dengan hak Hapus. */
    public function cabut(Request $request, int $tanda): JsonResponse
    {
        $user = $request->user();
        $baris = DB::table('tanda_outlet')->where('id', $tanda)->whereNull('dicabut_at')->first();

        abort_unless($baris, 404);
        abort_unless($this->bolehCabut($baris, $user), 403, 'Anda tidak boleh mencabut tanda ini.');

        // Dua pencabut bersamaan: hanya yang pertama berhasil.
        $berubah = DB::table('tanda_outlet')->where('id', $tanda)->whereNull('dicabut_at')
            ->update(['dicabut_at' => now(), 'dicabut_oleh' => $user->id, 'updated_at' => now()]);

        abort_unless($berubah, 404);

        $outlet = DB::table('mk_outlet')->where('id', $baris->outlet_id)->first(['nama']);
        Akses::catat($user, 'tanda_outlet', $tanda, 'hapus', 'Cabut tanda outlet '.($outlet?->nama ?? $baris->outlet_id).': '.$this->label($baris->kategori));

        return response()->json(['ok' => true]);
    }

    /** Outlet master (mk_outlet); selain itu 404. */
    private function outlet(int $id): object
    {
        $outlet = DB::table('mk_outlet')->where('id', $id)->first(['id', 'nama']);

        abort_unless($outlet, 404);

        return $outlet;
    }

    private function label(string $kategori): string
    {
        return config("marketama.tanda_outlet.{$kategori}", $kategori);
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
