<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Mobile\Lokasi;
use App\Support\Mobile\Pengaturan;
use App\Support\Tracking\TandaOutletMotoris;
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

        // Tanda aktif motoris yang sama pada tanggal lain dalam jendela hari terakhir, supaya pola lintas hari terlihat
        // dan koordinator bisa langsung meninjau (ubah catatan / cabut) tanpa pindah tanggal dulu.
        $awal = Pengaturan::sekarang()->subDays((int) config('marketama.tanda_motoris_hari') - 1)->toDateString();
        $lainnya = DB::table('tanda_motoris')
            ->where('karyawan_id', $motoris->id)->whereNull('dicabut_at')->where('tanggal', '>=', $awal)->where('tanggal', '<>', $tanggal)
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn ($t) => [...$this->bentuk($t, $request->user()), 'tanggal' => substr((string) $t->tanggal, 0, 10)]);

        // Tanda outlet pada outlet yang didaftarkan motoris ini (ikut terhitung di kolom Ditandai). Dikelola di menu
        // All Outlet (hak outlet_all), jadi di sini hanya untuk ditinjau.
        $outlet = TandaOutletMotoris::aktif(false, $motoris->id, $motoris->nama)
            ->orderByDesc('tout.created_at')->orderByDesc('tout.id')->limit(50)
            ->select(['tout.id', 'tout.outlet_id', 'o.nama as outlet', 'o.latitude', 'o.longitude', 'tout.kategori', 'tout.catatan', 'o.dibuat_pada', 'tout.dibuat_nama', 'tout.created_at'])
            ->selectSub(DB::table('trx_outlet_baru as nb')->whereColumn('nb.outlet_id', 'o.id')->selectRaw('MIN(COALESCE(nb.recorded_at, nb.received_at))'), 'daftar_hp')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'outlet_id' => (int) $t->outlet_id,
                'outlet' => $t->outlet,
                'latitude' => $t->latitude !== null ? (float) $t->latitude : null,
                'longitude' => $t->longitude !== null ? (float) $t->longitude : null,
                'kategori_label' => config("marketama.tanda_outlet.{$t->kategori}", $t->kategori),
                // Kapan outlet didaftarkan sebagai NOO: dari HP (UTC -> lokal) bila ada, selain itu data Marketama apa adanya.
                'didaftarkan' => JamLokal::format($t->daftar_hp, 'Y-m-d H:i') ?? $t->dibuat_pada,
                'catatan' => $t->catatan,
                'oleh' => $t->dibuat_nama,
                'waktu' => JamLokal::format($t->created_at, 'Y-m-d H:i'),
                'tanggal' => JamLokal::format($t->created_at, 'Y-m-d'),
            ]);

        return response()->json([
            'tanda' => $tanda->values(),
            'lainnya' => $lainnya->values(),
            'outlet' => $outlet->values(),
            'bisa_tambah' => Akses::bisa($request->user(), self::MENU, 'tambah'),
            'kategori' => config('marketama.tanda_motoris'),
        ]);
    }

    /** Batas jumlah outlet bertanda di sekitar yang ditampilkan. */
    public const MAKS_SEKITAR = 20;

    /**
     * Outlet lain yang punya tanda outlet aktif di sekitar satu outlet (terdekat dulu), untuk konteks di peta.
     * Radius = ambang "jarak dekat" yang diatur pengguna di browser-nya (localStorage), dikirim sebagai radius_m;
     * tanpa itu memakai bawaan config('marketama.deteksi_jarak_kunjungan.jarak_dekat_meter').
     */
    public function outletTerdekat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'integer'],
            'radius_m' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $radius = (int) ($data['radius_m'] ?? config('marketama.deteksi_jarak_kunjungan.jarak_dekat_meter'));

        $acuan = DB::table('mk_outlet')->where('id', $data['outlet_id'])->first(['id', 'latitude', 'longitude']);

        abort_unless($acuan, 404);

        if (! Lokasi::sah($acuan->latitude, $acuan->longitude)) {
            return response()->json(['sekitar' => [], 'radius_m' => $radius]);
        }

        $lat = (float) $acuan->latitude;
        $lng = (float) $acuan->longitude;
        [$latMin, $latMax, $lngMin, $lngMax] = Lokasi::kotak($lat, $lng, $radius);

        $sekitar = DB::table('tanda_outlet as tout')
            ->join('mk_outlet as o', 'o.id', '=', 'tout.outlet_id')
            ->whereNull('tout.dicabut_at')->where('o.id', '<>', $acuan->id)
            ->whereBetween('o.latitude', [$latMin, $latMax])->whereBetween('o.longitude', [$lngMin, $lngMax])
            ->orderBy('tout.id')
            ->get(['o.id as outlet_id', 'o.nama as outlet', 'o.latitude', 'o.longitude', 'tout.kategori', 'tout.catatan', 'tout.created_at'])
            ->groupBy('outlet_id')
            ->map(function ($tanda) use ($lat, $lng) {
                $o = $tanda->first();

                return [
                    'outlet_id' => (int) $o->outlet_id,
                    'outlet' => $o->outlet,
                    'latitude' => (float) $o->latitude,
                    'longitude' => (float) $o->longitude,
                    'jarak_m' => Lokasi::jarak($lat, $lng, (float) $o->latitude, (float) $o->longitude),
                    'jumlah_tanda' => $tanda->count(),
                    'kategori_label' => $tanda->map(fn ($t) => config("marketama.tanda_outlet.{$t->kategori}", $t->kategori))->unique()->implode(', '),
                    'ditandai_pada' => JamLokal::format($tanda->max('created_at'), 'Y-m-d'),
                ];
            })
            ->filter(fn ($o) => $o['jarak_m'] <= $radius)
            ->sortBy('jarak_m')->take(self::MAKS_SEKITAR)->values();

        return response()->json(['sekitar' => $sekitar, 'radius_m' => $radius]);
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

    /** Ubah catatan satu tanda (kategori dan tanggal tetap): hanya pemberinya atau akun dengan hak Hapus. */
    public function update(Request $request, int $tanda): JsonResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:500']]);

        $user = $request->user();
        $baris = DB::table('tanda_motoris')->where('id', $tanda)->whereNull('dicabut_at')->first();

        abort_unless($baris, 404);

        $motoris = $this->motoris($request, (int) $baris->karyawan_id);

        abort_unless($this->bolehUbah($baris, $user), 403, 'Anda tidak boleh mengubah tanda ini.');

        DB::table('tanda_motoris')->where('id', $tanda)->update(['catatan' => $data['catatan'] ?? null, 'updated_at' => now()]);

        Akses::catat($user, 'tanda_motoris', $tanda, 'ubah', "Ubah catatan tanda {$motoris->nama} (".substr((string) $baris->tanggal, 0, 10).'): '.$this->label($baris->kategori));

        return response()->json($this->bentuk(DB::table('tanda_motoris')->find($tanda), $user));
    }

    /** Cabut (soft) satu tanda: hanya pemberinya atau akun dengan hak Hapus. */
    public function cabut(Request $request, int $tanda): JsonResponse
    {
        $user = $request->user();
        $baris = DB::table('tanda_motoris')->where('id', $tanda)->whereNull('dicabut_at')->first();

        abort_unless($baris, 404);

        $motoris = $this->motoris($request, (int) $baris->karyawan_id);

        abort_unless($this->bolehUbah($baris, $user), 403, 'Anda tidak boleh mencabut tanda ini.');

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

    /** Boleh mengubah catatan atau mencabut tanda ini: pemberinya (dengan hak Tambah) atau akun dengan hak Hapus. */
    private function bolehUbah(object $tanda, ?User $user): bool
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
            'bisa_cabut' => $this->bolehUbah($tanda, $user),
        ];
    }
}
