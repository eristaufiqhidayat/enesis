<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Kelola akun login (tabel users) untuk menu Super Admin, User Koordinator, User Sales, dan
// User Lainnya: daftar, tambah, edit, detail + sesi HP, password awal, aktif/nonaktif,
// cabut sesi HP, keputusan akun ganda, dan hapus akun yang belum pernah dipakai.
class AkunController extends Controller
{
    /** slug menu => kategori akun */
    public const MENU = [
        'super_admin' => 'super_admin',
        'user_koordinator' => 'koordinator',
        'user_sales' => 'sales',
        'user_lainnya' => 'lainnya',
    ];

    public const STATUS = [
        'semua' => 'Semua',
        'aktif' => 'Aktif',
        'nonaktif' => 'Tidak aktif',
        'keputusan' => 'Perlu keputusan',
        'belum_ganti' => 'Belum ganti password',
    ];

    public const ALASAN_NONAKTIF = ['Akun ganda (karyawan sama)', 'Karyawan keluar', 'Pindah peran', 'HP hilang / disalahgunakan', 'Lainnya'];

    public function index(Request $request): View
    {
        $menu = $this->menu($request);
        $status = array_key_exists($request->query('status'), self::STATUS) ? $request->query('status') : 'semua';
        $q = trim((string) $request->query('q'));
        $depo = $request->integer('depo') ?: null;

        $dasar = $this->query($request->user(), $menu);

        if ($depo) {
            $dasar->where('k.depo_id', $depo);
        }

        if ($q !== '') {
            $dasar->where(function (Builder $w) use ($q) {
                foreach (['u.username', 'u.name', 'mu.nickname', 'k.nama', 'd.nama'] as $kolom) {
                    $w->orWhereRaw("LOWER({$kolom}) LIKE ?", ['%'.mb_strtolower($q).'%']);
                }
            });
        }

        $jumlah = [];
        foreach (array_keys(self::STATUS) as $s) {
            $jumlah[$s] = $this->saring(clone $dasar, $s)->count();
        }

        $akun = $this->saring($dasar, $status)->orderByRaw('LOWER(u.username)')
            ->paginate(50)->withQueryString();

        return view('akun.index', [
            'menu' => $menu,
            'judul' => $this->judul($menu),
            'akun' => $akun,
            'jumlah' => $jumlah,
            'status' => $status,
            'q' => $q,
            'depo' => $depo,
            'daftarDepo' => DB::table('mk_depo')->orderBy('nama')->pluck('nama', 'id'),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $menu = $this->menu($request);
        $akun = $this->cari($request, $menu, $id);
        $karyawan = $akun->karyawan_id ? DB::table('mk_karyawan')->where('id', $akun->karyawan_id)->first() : null;

        return view('akun.show', [
            'menu' => $menu,
            'judul' => $this->judul($menu),
            'akun' => $akun,
            'karyawan' => $karyawan,
            'area' => $karyawan ? DB::table('mk_karyawan_area')->where('karyawan_id', $karyawan->id)->where('aktif', true)->count() : 0,
            'kembar' => $this->akunLainKaryawanSama($akun),
            'sesi' => DB::table('mobile_tokens')->where('user_id', $id)->orderByDesc('id')->limit(10)->get(),
            'riwayat' => DB::table('riwayat_admin')->where('objek', 'user')->where('objek_id', $id)->orderByDesc('id')->limit(10)->get(),
            'alasanHapus' => $this->alasanTidakBisaHapus($request, $akun),
            'alasanNonaktif' => self::ALASAN_NONAKTIF,
        ]);
    }

    public function create(Request $request): View
    {
        $menu = $this->menu($request);

        return $this->form($request, $menu, null);
    }

    public function edit(Request $request, int $id): View
    {
        $menu = $this->menu($request);

        return $this->form($request, $menu, $this->cari($request, $menu, $id));
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $menu = $this->menu($request);
        $data = $this->validasi($request, null);
        $password = $data['password_mode'] === 'manual' ? $data['password'] : Str::password(10, symbols: false);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $password,
            'kategori' => $data['kategori'],
            'role_id' => $data['role_id'],
            'karyawan_id' => $data['karyawan_id'] ?? null,
            'aktif' => $data['aktif'],
            'wajib_ganti_password' => $data['password_mode'] === 'otomatis' || $request->boolean('wajib_ganti_password'),
        ]);

        Akses::catat($request->user(), 'user', $user->id, 'dibuat', 'Akun '.$user->username.' dibuat');
        $menuBaru = array_search($user->kategori, self::MENU, true) ?: $menu;

        if ($data['password_mode'] === 'otomatis') {
            return $this->tampilPassword($menuBaru, $user, $password);
        }

        return redirect()->route("akun.{$menuBaru}.show", $user->id)->with('sukses', 'Akun '.$user->username.' dibuat.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $menu = $this->menu($request);
        $lama = $this->cari($request, $menu, $id);
        $data = $this->validasi($request, $lama);
        $user = User::findOrFail($id);

        $user->forceFill([
            'name' => $data['name'],
            'username' => $data['username'],
            'kategori' => $data['kategori'],
            'role_id' => $data['role_id'],
            'karyawan_id' => $data['karyawan_id'] ?? null,
            'aktif' => $data['aktif'],
        ])->save();

        $teks = fn ($v) => is_bool($v) ? (string) (int) $v : (string) $v;
        $ubah = collect(['username', 'kategori', 'role_id', 'karyawan_id', 'aktif'])
            ->filter(fn ($k) => $teks($lama->{$k}) !== $teks($user->{$k}))->implode(', ');
        Akses::catat($request->user(), 'user', $id, 'diubah', $ubah ? 'Diubah: '.$ubah : 'Disimpan tanpa perubahan data utama');

        if (! $user->aktif && $lama->aktif) {
            $this->cabutToken($id, 'admin');
        }

        $menuBaru = array_search($user->kategori, self::MENU, true) ?: $menu;

        return redirect()->route("akun.{$menuBaru}.show", $id)->with('sukses', 'Akun '.$user->username.' disimpan.');
    }

    /** Buat password awal acak baru (tampil sekali, wajib diganti saat login). */
    public function aturPassword(Request $request, int $id): Response
    {
        $menu = $this->menu($request);
        $akun = $this->cari($request, $menu, $id);
        $password = Str::password(10, symbols: false);

        DB::table('users')->where('id', $id)->update([
            'password' => bcrypt($password),
            'wajib_ganti_password' => true,
            'updated_at' => now(),
        ]);
        Akses::catat($request->user(), 'user', $id, 'password', 'Password awal baru dibuat');

        return $this->tampilPassword($menu, $akun, $password);
    }

    public function ubahStatus(Request $request, int $id): RedirectResponse
    {
        $menu = $this->menu($request);
        $akun = $this->cari($request, $menu, $id);
        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:150'],
        ]);
        $aktif = (bool) $data['aktif'];

        if (! $aktif && $id === (int) $request->user()->id) {
            return back()->withErrors(['status' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }

        if ($aktif && $akun->karyawan_id && $this->akunLainKaryawanSama($akun)->where('aktif', true)->isNotEmpty()) {
            return back()->withErrors(['status' => 'Karyawan ini sudah punya akun aktif lain. Nonaktifkan akun itu dulu.']);
        }

        DB::table('users')->where('id', $id)->update(['aktif' => $aktif, 'updated_at' => now()]);

        if (! $aktif) {
            $this->cabutToken($id, 'admin');
        }

        Akses::catat($request->user(), 'user', $id, $aktif ? 'diaktifkan' : 'dinonaktifkan', $data['alasan'] ?? null);

        return redirect()->route("akun.{$menu}.show", $id)
            ->with('sukses', 'Akun '.$akun->username.($aktif ? ' diaktifkan.' : ' dinonaktifkan; sesi HP-nya dicabut.'));
    }

    public function cabutSesi(Request $request, int $id): RedirectResponse
    {
        $menu = $this->menu($request);
        $akun = $this->cari($request, $menu, $id);
        $jumlah = $this->cabutToken($id, 'admin');
        Akses::catat($request->user(), 'user', $id, 'cabut_sesi', $jumlah.' sesi HP dicabut');

        return back()->with('sukses', $jumlah ? 'Sesi HP '.$akun->username.' dicabut; pengguna harus login ulang.' : 'Tidak ada sesi HP aktif.');
    }

    /** Selesaikan "perlu keputusan": akun ini dipakai, akun aktif lain milik karyawan yang sama dinonaktifkan. */
    public function pakaiAkun(Request $request, int $id): RedirectResponse
    {
        $menu = $this->menu($request);
        $akun = $this->cari($request, $menu, $id);
        $lain = $akun->karyawan_id ? $this->akunLainKaryawanSama($akun)->where('aktif', true) : collect();

        DB::transaction(function () use ($id, $lain) {
            DB::table('users')->where('id', $id)->update(['aktif' => true, 'perlu_keputusan' => false, 'updated_at' => now()]);

            foreach ($lain as $l) {
                DB::table('users')->where('id', $l->id)->update(['aktif' => false, 'perlu_keputusan' => false, 'updated_at' => now()]);
                $this->cabutToken($l->id, 'admin');
            }
        });

        Akses::catat($request->user(), 'user', $id, 'keputusan', $lain->isEmpty()
            ? 'Ditandai sudah diputuskan'
            : 'Dipakai; dinonaktifkan: '.$lain->pluck('username')->implode(', '));

        foreach ($lain as $l) {
            Akses::catat($request->user(), 'user', $l->id, 'dinonaktifkan', 'Akun ganda; dipakai '.$akun->username);
        }

        return redirect()->route("akun.{$menu}.show", $id)->with('sukses', 'Keputusan disimpan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $menu = $this->menu($request);
        $akun = $this->cari($request, $menu, $id);

        if ($alasan = $this->alasanTidakBisaHapus($request, $akun)) {
            return back()->withErrors(['hapus' => $alasan]);
        }

        if (mb_strtolower(trim((string) $request->input('konfirmasi'))) !== mb_strtolower($akun->username)) {
            return back()->withErrors(['hapus' => 'Ketik username '.$akun->username.' untuk konfirmasi.']);
        }

        DB::table('users')->where('id', $id)->delete();
        Akses::catat($request->user(), 'user', $id, 'dihapus', 'Akun '.$akun->username.' dihapus');

        return redirect()->route("akun.{$menu}.index")->with('sukses', 'Akun '.$akun->username.' dihapus.');
    }

    /** Pencarian karyawan untuk form (JSON). */
    public function cariKaryawan(Request $request): JsonResponse
    {
        $menu = $this->menu($request);
        $q = mb_strtolower(trim((string) $request->query('q')));
        $kecuali = $request->integer('kecuali') ?: 0;

        if (mb_strlen($q) < 2 && ! ctype_digit($q)) {
            return response()->json([]);
        }

        $hasil = DB::table('mk_karyawan as k')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where(fn ($w) => ctype_digit($q) ? $w->where('k.id', (int) $q) : $w->whereRaw('LOWER(k.nama) LIKE ?', ["%{$q}%"]))
            ->where('k.aktif', true)
            ->orderBy('k.nama')->limit(15)
            ->get(['k.id', 'k.nama', 'k.kategori', 'd.nama as depo', 'ko.nama as koordinator']);

        $aktif = DB::table('users')->whereIn('karyawan_id', $hasil->pluck('id'))->where('aktif', true)
            ->where('id', '!=', $kecuali)->pluck('username', 'karyawan_id');

        return response()->json($hasil->map(fn ($k) => [
            'id' => (int) $k->id,
            'nama' => $k->nama,
            'kategori' => $k->kategori,
            'depo' => $k->depo,
            'koordinator' => $k->koordinator,
            'akun_aktif' => $aktif[$k->id] ?? null,
        ]));
    }

    /** Keterangan singkat status akun untuk daftar & detail. */
    public static function keterangan(object $a): ?string
    {
        return match (true) {
            (bool) $a->perlu_keputusan => 'Perlu keputusan admin',
            ! $a->karyawan_id && $a->kategori !== 'super_admin' => 'Belum terhubung ke karyawan',
            $a->karyawan_id && $a->karyawan_aktif !== null && ! $a->karyawan_aktif => 'Karyawan sudah tidak aktif',
            (bool) $a->aktif && (bool) $a->wajib_ganti_password => 'Belum ganti password awal',
            default => null,
        };
    }

    // ------------------------------------------------------------------

    /** Query daftar akun satu menu, sudah dibatasi cakupan data role pengguna. */
    private function query(User $saya, string $menu): Builder
    {
        $q = DB::table('users as u')
            ->leftJoin('mk_user as mu', function ($j) {
                $j->on('mu.jenis', '=', 'u.sumber_jenis')->on('mu.id', '=', 'u.sumber_id');
            })
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoin('roles as r', function ($j) {
                // Akun tanpa role_id memakai role bawaan sesuai kategori.
                $j->whereRaw('r.id = COALESCE(u.role_id, (SELECT rb.id FROM roles rb WHERE rb.kode = u.kategori))');
            })
            ->where('u.kategori', self::MENU[$menu])
            ->select([
                'u.id', 'u.username', 'u.name', 'u.kategori', 'u.karyawan_id', 'u.role_id', 'u.aktif', 'u.perlu_keputusan',
                'u.wajib_ganti_password', 'u.catatan_migrasi', 'u.sumber_jenis', 'u.login_terakhir_pada', 'u.created_at',
                'mu.nickname', 'k.nama as karyawan', 'k.aktif as karyawan_aktif', 'k.depo_id', 'd.nama as depo',
                'ko.nama as koordinator', 'r.nama as role',
            ])
            // Sesi HP aktif (satu akun satu HP).
            ->selectSub($this->tokenAktif('device_model'), 'hp_model')
            ->selectSub($this->tokenAktif('app_version'), 'hp_versi');

        return match (Akses::cakupan($saya)) {
            'depo' => $q->where('k.depo_id', DB::table('mk_karyawan')->where('id', $saya->karyawan_id ?? 0)->value('depo_id') ?? -1),
            'downline' => $q->where(fn ($w) => $w->where('k.koordinator_id', $saya->karyawan_id ?? -1)->orWhere('u.id', $saya->id)),
            'diri' => $q->where('u.id', $saya->id),
            default => $q,
        };
    }

    private function tokenAktif(string $kolom): Builder
    {
        return DB::table('mobile_tokens as t')->whereColumn('t.user_id', 'u.id')->whereNull('t.revoked_at')
            ->orderByDesc('t.id')->limit(1)->select("t.{$kolom}");
    }

    private function saring(Builder $q, string $status): Builder
    {
        // Boolean dipakai tanpa "= 1" supaya jalan di SQLite, MySQL, PostgreSQL.
        return match ($status) {
            'aktif' => $q->where('u.aktif', true),
            'nonaktif' => $q->where('u.aktif', false),
            'keputusan' => $q->where('u.perlu_keputusan', true),
            'belum_ganti' => $q->where('u.aktif', true)->where('u.wajib_ganti_password', true),
            default => $q,
        };
    }

    /** Satu akun dalam menu & cakupan pengguna; di luar itu 404. */
    private function cari(Request $request, string $menu, int $id): object
    {
        return $this->query($request->user(), $menu)->where('u.id', $id)->first() ?? abort(404);
    }

    /** Slug menu dari route (lihat routes/web.php: defaults "menu"). */
    private function menu(Request $request): string
    {
        return (string) $request->route('menu');
    }

    private function judul(string $menu): string
    {
        return config("marketama.pages.{$menu}.title") ?? 'Users';
    }

    private function form(Request $request, string $menu, ?object $akun): View
    {
        $kategori = collect(self::MENU)->filter(fn ($k, $slug) => Akses::bisa($request->user(), $slug, $akun ? 'ubah' : 'tambah'))
            ->mapWithKeys(fn ($k, $slug) => [$k => $this->judul($slug)]);
        $karyawan = $akun?->karyawan_id
            ? DB::table('mk_karyawan as k')->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
                ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
                ->where('k.id', $akun->karyawan_id)->first(['k.id', 'k.nama', 'd.nama as depo', 'ko.nama as koordinator'])
            : null;

        return view('akun.form', [
            'menu' => $menu,
            'judul' => $this->judul($menu),
            'akun' => $akun,
            'karyawan' => $karyawan,
            'kategoriAwal' => $akun->kategori ?? self::MENU[$menu],
            'pilihanKategori' => $kategori,
            'roles' => DB::table('roles')->orderByDesc('bawaan')->orderBy('nama')->get(['id', 'kode', 'nama']),
        ]);
    }

    private function validasi(Request $request, ?object $lama): array
    {
        $boleh = collect(self::MENU)->filter(fn ($k, $slug) => Akses::bisa($request->user(), $slug, $lama ? 'ubah' : 'tambah'))->values()->all();

        $data = $request->validate([
            'kategori' => ['required', Rule::in($boleh)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'username' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['nullable', 'string', 'max:120'],
            'karyawan_id' => ['nullable', 'integer', Rule::exists('mk_karyawan', 'id'),
                Rule::requiredIf(in_array($request->input('kategori'), ['sales', 'koordinator'], true))],
            'password_mode' => [$lama ? 'nullable' : 'required', Rule::in(['otomatis', 'manual'])],
            'password' => [Rule::requiredIf(! $lama && $request->input('password_mode') === 'manual'), 'nullable', 'string', 'min:8', 'confirmed',
                Rule::notIn([$request->input('username'), mb_strtolower((string) $request->input('username'))])],
        ], [
            'username.regex' => 'Username hanya huruf, angka, titik, garis bawah, atau strip (tanpa spasi).',
            'karyawan_id.required' => 'Akun sales dan koordinator wajib terhubung ke karyawan.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.not_in' => 'Password tidak boleh sama dengan username.',
        ], ['kategori' => 'kategori', 'karyawan_id' => 'karyawan', 'username' => 'username']);

        $data['aktif'] = $request->boolean('aktif');

        $kembar = DB::table('users')->whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])
            ->when($lama, fn ($q) => $q->where('id', '!=', $lama->id))->exists();
        if ($kembar) {
            throw ValidationException::withMessages(['username' => 'Username sudah dipakai (huruf besar/kecil dianggap sama).']);
        }

        if (! empty($data['karyawan_id']) && $data['aktif']) {
            $lain = DB::table('users')->where('karyawan_id', $data['karyawan_id'])->where('aktif', true)
                ->when($lama, fn ($q) => $q->where('id', '!=', $lama->id))->value('username');
            if ($lain) {
                throw ValidationException::withMessages(['karyawan_id' => "Karyawan ini sudah punya akun aktif: {$lain}. Satu karyawan hanya boleh satu akun aktif."]);
            }
        }

        $data['role_id'] ??= DB::table('roles')->where('kode', $data['kategori'])->value('id');

        if (blank($data['name'] ?? null)) {
            $data['name'] = ! empty($data['karyawan_id'])
                ? DB::table('mk_karyawan')->where('id', $data['karyawan_id'])->value('nama')
                : $data['username'];
        }

        return $data;
    }

    /** Password tampil langsung dari POST (tidak lewat session) dan tidak boleh di-cache browser. */
    private function tampilPassword(string $menu, object $akun, string $password): Response
    {
        return response()->view('akun.password', [
            'menu' => $menu,
            'judul' => $this->judul($menu),
            'akun' => $akun,
            'password' => $password,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function akunLainKaryawanSama(object $akun)
    {
        if (! $akun->karyawan_id) {
            return collect();
        }

        return DB::table('users')->where('karyawan_id', $akun->karyawan_id)->where('id', '!=', $akun->id)
            ->orderBy('username')->get(['id', 'username', 'kategori', 'aktif', 'login_terakhir_pada']);
    }

    private function alasanTidakBisaHapus(Request $request, object $akun): ?string
    {
        return match (true) {
            (int) $akun->id === (int) $request->user()->id => 'Tidak bisa menghapus akun sendiri.',
            $akun->sumber_jenis !== null => 'Akun dari data aplikasi lama tidak dihapus (akan dibuat lagi saat sinkron). Nonaktifkan saja.',
            $akun->login_terakhir_pada !== null || DB::table('mobile_tokens')->where('user_id', $akun->id)->exists() => 'Akun sudah pernah dipakai login. Nonaktifkan saja supaya riwayatnya tetap utuh.',
            default => null,
        };
    }

    private function cabutToken(int $userId, string $alasan): int
    {
        return DB::table('mobile_tokens')->where('user_id', $userId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $alasan, 'updated_at' => now()]);
    }
}
