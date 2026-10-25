<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

// Role & hak akses: daftar role, tambah/edit (cakupan data, login web/HP, matriks hak per
// menu), anggota role + pindah role, riwayat perubahan, hapus role buatan yang kosong.
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = DB::table('roles')->orderByDesc('bawaan')->orderBy('nama')->get();
        $hak = DB::table('role_hak')
            ->where(fn ($q) => $q->where('lihat', true)->orWhere('tambah', true)->orWhere('ubah', true)->orWhere('hapus', true)->orWhere('ekspor', true))
            ->groupBy('role_id')->selectRaw('role_id, COUNT(*) AS jumlah')->pluck('jumlah', 'role_id');

        foreach ($roles as $r) {
            $r->jumlah_menu = $r->kode === 'super_admin' ? null : (int) ($hak[$r->id] ?? 0);
            $r->jumlah_akun = $this->anggotaQuery($r)->count();
        }

        return Inertia::render('Role/Index', [
            'roles' => $roles,
            'cakupan' => Akses::CAKUPAN,
            'bisaTambah' => Akses::bisa($request->user(), 'role', 'tambah'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $request);
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form(DB::table('roles')->find($id) ?? abort(404), $request);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request, null);
        $kode = Str::slug($data['nama'], '_');
        $kode = DB::table('roles')->where('kode', $kode)->exists() ? $kode.'_'.Str::lower(Str::random(4)) : $kode;

        $id = DB::transaction(function () use ($data, $kode, $request) {
            $id = DB::table('roles')->insertGetId([
                'kode' => $kode, 'nama' => $data['nama'], 'keterangan' => $data['keterangan'] ?? null, 'bawaan' => false,
                'cakupan' => $data['cakupan'], 'login_web' => $request->boolean('login_web'), 'login_mobile' => $request->boolean('login_mobile'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->simpanHak($id, $this->normalisasiHak($request->input('hak', [])));

            return $id;
        });

        Akses::catat($request->user(), 'role', $id, 'dibuat', 'Role dibuat');
        Akses::lupakan();

        return redirect()->route('role.index')->with('sukses', 'Role '.$data['nama'].' dibuat.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $role = DB::table('roles')->find($id) ?? abort(404);
        $data = $this->validasi($request, $role);
        $hakLama = Akses::hakRole($id);
        $hakBaru = $role->kode === 'super_admin' ? $hakLama : $this->normalisasiHak($request->input('hak', []));

        // Jangan sampai admin mengunci dirinya sendiri dari menu Role.
        $saya = $request->user();
        if ($role->kode !== 'super_admin' && (int) (Akses::role($saya)->id ?? 0) === $id && empty($hakBaru['role']['ubah'])) {
            throw ValidationException::withMessages(['hak' => 'Role ini dipakai akun Anda: hak Ubah pada menu Role & Hak Akses tidak boleh dicabut.']);
        }

        DB::transaction(function () use ($id, $role, $data, $request, $hakBaru) {
            DB::table('roles')->where('id', $id)->update([
                'nama' => $data['nama'], 'keterangan' => $data['keterangan'] ?? null, 'cakupan' => $data['cakupan'],
                'login_web' => $role->kode === 'super_admin' ? true : $request->boolean('login_web'),
                'login_mobile' => $request->boolean('login_mobile'),
                'updated_at' => now(),
            ]);

            if ($role->kode !== 'super_admin') {
                $this->simpanHak($id, $hakBaru);
            }
        });

        foreach ($this->ringkasPerubahan($role, $data, $request, $hakLama, $hakBaru) as $baris) {
            Akses::catat($request->user(), 'role', $id, 'diubah', $baris);
        }
        Akses::lupakan();

        return redirect()->route('role.edit', $id)->with('sukses', 'Role '.$data['nama'].' disimpan.');
    }

    public function anggota(Request $request, int $id): Response
    {
        $role = DB::table('roles')->find($id) ?? abort(404);
        $q = trim((string) $request->query('q'));
        $menuAkun = array_flip(AkunController::MENU);

        $anggota = $this->anggotaQuery($role)
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('LOWER(u.username) LIKE ?', ['%'.mb_strtolower($q).'%'])
                ->orWhereRaw('LOWER(k.nama) LIKE ?', ['%'.mb_strtolower($q).'%'])))
            ->orderByRaw('LOWER(u.username)')
            ->select(['u.id', 'u.username', 'u.kategori', 'u.aktif', 'u.login_terakhir_pada', 'k.nama as karyawan', 'd.nama as depo'])
            ->paginate(50)->withQueryString()
            // Presentasi untuk Vue: tautan ke halaman akun (masih Blade) dan waktu terformat.
            ->through(fn ($a) => [
                'id' => $a->id,
                'username' => $a->username,
                'aktif' => (bool) $a->aktif,
                'karyawan' => $a->karyawan,
                'depo' => $a->depo,
                'login_terakhir' => $this->waktu($a->login_terakhir_pada),
                'url' => isset($menuAkun[$a->kategori]) ? route('akun.'.$menuAkun[$a->kategori].'.show', $a->id) : null,
            ])
            ->onEachSide(1);

        return Inertia::render('Role/Anggota', [
            'role' => $role,
            'cakupan' => Akses::CAKUPAN,
            'anggota' => $anggota,
            'q' => $q,
            'roleLain' => DB::table('roles')->where('id', '!=', $id)->orderBy('nama')->pluck('nama', 'id'),
            'riwayat' => DB::table('riwayat_admin')->where('objek', 'role')->where('objek_id', $id)->orderByDesc('id')->limit(20)->get()
                ->map(fn ($r) => [
                    'keterangan' => $r->keterangan ?? $r->aksi,
                    'aktor' => $r->aktor_nama ?? 'sistem',
                    'waktu' => $this->waktu($r->created_at),
                ]),
            'bisaUbah' => Akses::bisa($request->user(), 'role', 'ubah'),
        ]);
    }

    public function pindahkan(Request $request, int $id): RedirectResponse
    {
        $role = DB::table('roles')->find($id) ?? abort(404);
        $data = $request->validate([
            'user_id' => ['required', 'array', 'min:1'],
            'user_id.*' => ['integer'],
            'ke' => ['required', 'integer', Rule::exists('roles', 'id'), Rule::notIn([$id])],
        ], ['user_id.required' => 'Pilih minimal satu akun.']);

        $ids = $this->anggotaQuery($role)->whereIn('u.id', $data['user_id'])->pluck('u.id');

        if ($ids->contains($request->user()->id) && ! Akses::penuh($request->user())) {
            throw ValidationException::withMessages(['user_id' => 'Akun Anda sendiri tidak bisa dipindah dari sini.']);
        }

        DB::table('users')->whereIn('id', $ids)->update(['role_id' => $data['ke'], 'updated_at' => now()]);
        $ke = DB::table('roles')->where('id', $data['ke'])->value('nama');
        Akses::catat($request->user(), 'role', $id, 'pindah', $ids->count().' akun dipindah ke '.$ke);
        Akses::catat($request->user(), 'role', (int) $data['ke'], 'pindah', $ids->count().' akun dipindah dari '.$role->nama);
        Akses::lupakan();

        return redirect()->route('role.anggota', $id)->with('sukses', $ids->count().' akun dipindah ke role '.$ke.'.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $role = DB::table('roles')->find($id) ?? abort(404);

        if ($role->bawaan) {
            return back()->withErrors(['hapus' => 'Role bawaan tidak bisa dihapus.']);
        }

        if ($this->anggotaQuery($role)->exists()) {
            return back()->withErrors(['hapus' => 'Pindahkan dulu semua anggota role ini ke role lain.']);
        }

        DB::transaction(function () use ($id) {
            DB::table('role_hak')->where('role_id', $id)->delete();
            DB::table('roles')->where('id', $id)->delete();
        });
        Akses::catat($request->user(), 'role', $id, 'dihapus', 'Role '.$role->nama.' dihapus');

        return redirect()->route('role.index')->with('sukses', 'Role '.$role->nama.' dihapus.');
    }

    // ------------------------------------------------------------------

    /** Akun anggota role; akun tanpa role_id ikut role bawaan sesuai kategorinya. */
    private function anggotaQuery(object $role): Builder
    {
        return DB::table('users as u')->where(function ($w) use ($role) {
            $w->where('u.role_id', $role->id);

            if ($role->bawaan) {
                $w->orWhere(fn ($x) => $x->whereNull('u.role_id')->where('u.kategori', $role->kode));
            }
        });
    }

    private function form(?object $role, Request $request): Response
    {
        return Inertia::render('Role/Form', [
            'role' => $role,
            'hak' => $role ? Akses::hakRole((int) $role->id) : [],
            'grup' => Akses::daftarMenu(),
            'aksi' => Akses::AKSI,
            'cakupan' => Akses::CAKUPAN,
            'jumlahAkun' => $role ? $this->anggotaQuery($role)->count() : 0,
            'bisaUbah' => Akses::bisa($request->user(), 'role', $role ? 'ubah' : 'tambah'),
            'bisaHapus' => (bool) $role && ! $role->bawaan && Akses::bisa($request->user(), 'role', 'hapus'),
            'superAdmin' => (bool) $role && $role->kode === 'super_admin',
        ]);
    }

    /** Waktu tampilan riwayat & login terakhir (zona & locale sama dengan halaman Blade sebelumnya). */
    private function waktu(?string $t): string
    {
        return $t ? Carbon::parse($t)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') : '—';
    }

    private function validasi(Request $request, ?object $role): array
    {
        $kembar = DB::table('roles')->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim((string) $request->input('nama')))])
            ->when($role, fn ($q) => $q->where('id', '!=', $role->id))->exists();

        if ($kembar) {
            throw ValidationException::withMessages(['nama' => 'Nama role sudah dipakai.']);
        }

        return $request->validate([
            'nama' => ['required', 'string', 'max:80', Rule::unique('roles', 'nama')->ignore($role?->id)],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'cakupan' => ['required', Rule::in(array_keys(Akses::CAKUPAN))],
            'hak' => ['nullable', 'array'],
        ], ['nama.unique' => 'Nama role sudah dipakai.'], ['nama' => 'nama role', 'cakupan' => 'cakupan data']);
    }

    /** @return array<string, array<string, bool>> hanya menu & aksi yang dikenal */
    private function normalisasiHak(array $input): array
    {
        $hasil = [];

        foreach (Akses::semuaSlug() as $menu) {
            foreach (array_keys(Akses::AKSI) as $aksi) {
                $hasil[$menu][$aksi] = ! empty($input[$menu][$aksi]);
            }

            // Tanpa "lihat" menu tidak tampil, jadi aksi lain ikut tidak berlaku.
            if (! $hasil[$menu]['lihat']) {
                $hasil[$menu] = array_fill_keys(array_keys(Akses::AKSI), false);
            }
        }

        return $hasil;
    }

    /** @param array<string, array<string, bool>> $hak hasil normalisasiHak() */
    private function simpanHak(int $roleId, array $hak): void
    {
        DB::table('role_hak')->where('role_id', $roleId)->delete();
        $baris = [];

        foreach ($hak as $menu => $a) {
            if (in_array(true, $a, true)) {
                $baris[] = ['role_id' => $roleId, 'menu' => $menu] + $a;
            }
        }

        foreach (array_chunk($baris, 200) as $potong) {
            DB::table('role_hak')->insert($potong);
        }
    }

    /** @return array<int, string> kalimat ringkas untuk riwayat */
    private function ringkasPerubahan(object $role, array $data, Request $request, array $lama, array $baru): array
    {
        $hasil = [];
        $judul = collect(Akses::daftarMenu())->flatMap(fn ($g) => array_column($g['menu'], 'nama', 'slug'));

        if ($role->nama !== $data['nama']) {
            $hasil[] = "Nama: {$role->nama} → {$data['nama']}";
        }

        if ($role->cakupan !== $data['cakupan']) {
            $hasil[] = 'Cakupan data: '.Akses::CAKUPAN[$role->cakupan][0].' → '.Akses::CAKUPAN[$data['cakupan']][0];
        }

        foreach (['login_web' => 'Login web', 'login_mobile' => 'Login aplikasi HP'] as $k => $label) {
            if ($role->kode !== 'super_admin' && (bool) $role->{$k} !== $request->boolean($k)) {
                $hasil[] = $label.': '.($request->boolean($k) ? 'diizinkan' : 'ditutup');
            }
        }

        $tambah = [];
        $cabut = [];
        foreach ($baru as $menu => $a) {
            foreach ($a as $aksi => $boleh) {
                $dulu = $lama[$menu][$aksi] ?? false;
                if ($boleh && ! $dulu) {
                    $tambah[] = Akses::AKSI[$aksi].' '.($judul[$menu] ?? $menu);
                } elseif (! $boleh && $dulu) {
                    $cabut[] = Akses::AKSI[$aksi].' '.($judul[$menu] ?? $menu);
                }
            }
        }

        foreach (['Hak ditambah' => $tambah, 'Hak dicabut' => $cabut] as $label => $daftar) {
            if ($daftar) {
                $hasil[] = $label.': '.implode(', ', array_slice($daftar, 0, 4)).(count($daftar) > 4 ? ' dan '.(count($daftar) - 4).' lainnya' : '');
            }
        }

        return $hasil ?: ['Disimpan tanpa perubahan'];
    }
}
