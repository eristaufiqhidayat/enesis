<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Akses\HierarkiSales;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// CRUD karyawan (menu Karyawan Sales, Koordinator, Supervisor, Lainnya) di tabel mk_karyawan — tabel yang sama
// dengan aplikasi HP, akun login, dan laporan motoris. Form mengikuti Marketama (nama, depo, koordinator, area,
// wilayah depo, kontak, tanggal mulai, status, gaji & tunjangan, foto). Koordinator sales diatur lewat HierarkiSales,
// area sales di mk_karyawan_area. Karyawan yang diubah di sini tidak ditimpa marketama:tarik (diubah_pada terisi).
class KaryawanController extends Controller
{
    /** slug menu => kategori mk_karyawan */
    public const MENU = [
        'karyawan_sales' => 'sales',
        'karyawan_koordinator' => 'koordinator',
        'karyawan_supervisor' => 'supervisor',
        'karyawan_lainnya' => 'lainnya',
    ];

    /** ID karyawan buatan Enesis mulai dari sini supaya tidak bertabrakan dengan ID asli Marketama. */
    public const ID_AWAL = 100_000_000;

    /** Jabatan Karyawan Lainnya (Kategori Karyawan Marketama selain Sales/Koordinator/Supervisor Nasional). */
    public const JABATAN = ['Supervisor', 'Principal', 'Auditor'];

    private const DISK = 'local';

    private const JUDUL = [
        'sales' => ['Karyawan Sales', 'sales'],
        'koordinator' => ['Karyawan Koordinator', 'koordinator'],
        'supervisor' => ['Karyawan Supervisor', 'supervisor'],
        'lainnya' => ['Karyawan Lainnya', 'karyawan'],
    ];

    private const GAJI = ['gaji_pokok' => 'Gaji pokok', 'tunjangan_bbm_makan' => 'Tunjangan BBM & makan', 'sewa_motor' => 'Sewa motor', 'tunjangan_internet' => 'Tunjangan kuota internet'];

    public function index(Request $request): Response
    {
        [$slug, $kategori] = $this->menu($request);
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['aktif', 'tidak_aktif'], true) ? $request->query('status') : 'semua';
        $depo = $request->integer('depo') ?: null;

        $dasar = $this->query($request, $kategori);
        $jumlah = ['semua' => (clone $dasar)->count(), 'aktif' => (clone $dasar)->where('k.aktif', true)->count()];
        $jumlah['tidak_aktif'] = $jumlah['semua'] - $jumlah['aktif'];

        $baris = $dasar
            ->when($status !== 'semua', fn ($w) => $w->where('k.aktif', $status === 'aktif'))
            ->when($depo, fn ($w) => $w->where('k.depo_id', $depo))
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                foreach (['k.nama', 'k.no_hp', 'k.email', 'd.nama', 'ko.nama', 'k.jabatan'] as $kolom) {
                    $x->orWhereRaw("LOWER({$kolom}) LIKE ?", ['%'.mb_strtolower($q).'%']);
                }
            }))
            ->orderByDesc('k.aktif')->orderBy('k.nama')
            ->paginate(25)->withQueryString()->onEachSide(1);
        $areaAktif = $kategori === 'sales' ? $this->namaAreaAktif($baris->getCollection()->pluck('id')->all()) : [];
        $baris->through(fn ($k) => $this->barisDaftar($kategori, $k, $areaAktif[$k->id] ?? null));

        return Inertia::render('Karyawan/Index', [
            'slug' => $slug,
            'kategori' => $kategori,
            'judul' => self::JUDUL[$kategori][0],
            'nama' => self::JUDUL[$kategori][1],
            'kolom' => $this->kolom($kategori),
            'baris' => $baris,
            'q' => $q,
            'status' => $status,
            'depo' => $depo,
            'jumlah' => $jumlah,
            'daftarDepo' => in_array($kategori, ['sales', 'koordinator'], true) ? $this->daftarDepo() : [],
            'bisa' => $this->bisa($request, $slug),
        ]);
    }

    public function create(Request $request): Response|JsonResponse
    {
        $props = $this->propsForm($request, null);

        return $request->expectsJson() ? response()->json($props) : Inertia::render('Karyawan/Form', $props);
    }

    public function edit(Request $request, int $id): Response|JsonResponse
    {
        $props = $this->propsForm($request, $this->cari($request, $id));

        return $request->expectsJson() ? response()->json($props) : Inertia::render('Karyawan/Form', $props);
    }

    /** Pilihan yang bergantung pada depo: koordinator & area (sales), wilayah depo (koordinator). */
    public function opsi(Request $request): JsonResponse
    {
        [, $kategori] = $this->menu($request);
        $depo = $request->integer('depo') ?: null;

        return response()->json($this->opsiDepo($kategori, $depo));
    }

    public function store(Request $request): RedirectResponse
    {
        [$slug, $kategori] = $this->menu($request);
        $data = $this->validasi($request, $kategori, null);
        $id = max(self::ID_AWAL, (int) DB::table('mk_karyawan')->where('id', '>=', self::ID_AWAL)->max('id') + 1);

        DB::transaction(function () use ($request, $kategori, $data, $id) {
            DB::table('mk_karyawan')->insert(['id' => $id, 'kategori' => $kategori, 'detail_lengkap' => true]
                + $this->kolomSimpan($data) + $this->jejak($request));
            $this->simpanRelasi($request, $kategori, $id, $data, null);
        });

        if ($foto = $this->simpanFoto($request, $id)) {
            DB::table('mk_karyawan')->where('id', $id)->update(['foto' => $foto]);
        }

        Akses::catat($request->user(), 'karyawan', $id, 'dibuat', self::JUDUL[$kategori][0].' dibuat: '.$data['nama']);

        return redirect("/{$slug}")->with('sukses', "{$data['nama']} ditambahkan.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        [$slug, $kategori] = $this->menu($request);
        $lama = $this->cari($request, $id);
        $data = $this->validasi($request, $kategori, $lama);
        $areaLama = $this->areaKaryawan($id);
        $perubahan = $this->ringkasPerubahan($lama, $data, $areaLama);

        DB::transaction(function () use ($request, $kategori, $id, $data, $lama) {
            DB::table('mk_karyawan')->where('id', $id)->update($this->kolomSimpan($data) + $this->jejak($request));
            $this->simpanRelasi($request, $kategori, $id, $data, $lama);
        });

        if ($foto = $this->simpanFoto($request, $id)) {
            DB::table('mk_karyawan')->where('id', $id)->update(['foto' => $foto]);
            $perubahan[] = 'Foto diganti';

            if ($lama->foto) {
                Storage::disk(self::DISK)->delete($lama->foto);
            }
        }

        foreach ($perubahan ?: ['Disimpan tanpa perubahan isi'] as $baris) {
            Akses::catat($request->user(), 'karyawan', $id, 'diubah', $baris);
        }

        return redirect("/{$slug}")->with('sukses', "{$data['nama']} disimpan.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        [$slug] = $this->menu($request);
        $lama = $this->cari($request, $id);

        if ($dipakai = $this->dipakai($id)) {
            return back()->withErrors(['hapus' => "{$lama->nama} masih dipakai ".implode(', ', $dipakai).'. Ubah status menjadi Tidak Aktif saja.']);
        }

        DB::transaction(function () use ($id) {
            DB::table('mk_karyawan_area')->where('karyawan_id', $id)->delete();
            DB::table('hierarki_sales')->where('karyawan_id', $id)->delete();
            DB::table('mk_karyawan')->where('id', $id)->delete();
        });

        if ($lama->foto) {
            Storage::disk(self::DISK)->delete($lama->foto);
        }

        Akses::catat($request->user(), 'karyawan', $id, 'dihapus', "Karyawan dihapus: {$lama->nama}");

        return redirect("/{$slug}")->with('sukses', "{$lama->nama} dihapus.");
    }

    /** Pindahkan semua area sales ini ke sales lain di depo yang sama (ganti atau gabung), seperti Marketama. */
    public function pindahArea(Request $request, int $id): RedirectResponse
    {
        [$slug, $kategori] = $this->menu($request);
        abort_unless($kategori === 'sales', 404);
        $asal = $this->cari($request, $id);
        $data = $request->validate([
            'tujuan_id' => ['required', 'integer', Rule::notIn([$id])],
            'mode' => ['required', Rule::in(['ganti', 'gabung'])],
        ], ['tujuan_id.required' => 'Pilih sales tujuan.', 'tujuan_id.not_in' => 'Sales tujuan harus berbeda.']);

        $tujuan = DB::table('mk_karyawan')->where('id', $data['tujuan_id'])->where('kategori', 'sales')->where('depo_id', $asal->depo_id)->first();

        if (! $tujuan) {
            throw ValidationException::withMessages(['tujuan_id' => 'Sales tujuan harus sales di depo yang sama.']);
        }

        $area = DB::table('mk_karyawan_area')->where('karyawan_id', $id)->get();

        if ($area->isEmpty()) {
            return back()->with('galat', "{$asal->nama} tidak punya area untuk dipindahkan.");
        }

        DB::transaction(function () use ($request, $id, $tujuan, $data, $area) {
            if ($data['mode'] === 'ganti') {
                DB::table('mk_karyawan_area')->where('karyawan_id', $tujuan->id)->delete();
            }

            $sudah = DB::table('mk_karyawan_area')->where('karyawan_id', $tujuan->id)->pluck('area_id')->flip();
            DB::table('mk_karyawan_area')->insert($area->reject(fn ($a) => isset($sudah[$a->area_id]))
                ->map(fn ($a) => ['karyawan_id' => $tujuan->id, 'area_id' => $a->area_id, 'aktif' => (bool) $a->aktif])->values()->all());
            DB::table('mk_karyawan_area')->where('karyawan_id', $id)->delete();

            foreach ([$id, (int) $tujuan->id] as $k) {
                DB::table('mk_karyawan')->where('id', $k)->update(['area_aktif_id' => $this->areaAktifPertama($k)] + $this->jejak($request));
            }
        });

        $teks = ($data['mode'] === 'ganti' ? 'menggantikan' : 'ditambahkan ke').' area '.$tujuan->nama;
        Akses::catat($request->user(), 'karyawan', $id, 'pindah_area', "{$area->count()} area dipindah, {$teks}");
        Akses::catat($request->user(), 'karyawan', (int) $tujuan->id, 'terima_area', "{$area->count()} area dari {$asal->nama}");

        return redirect("/{$slug}")->with('sukses', "{$area->count()} area {$asal->nama} dipindahkan ke {$tujuan->nama}.");
    }

    public function foto(Request $request, int $id)
    {
        $path = $this->cari($request, $id)->foto;
        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
    }

    public function ekspor(Request $request): StreamedResponse
    {
        [$slug, $kategori] = $this->menu($request);
        $kolom = array_diff_key($this->kolom($kategori), ['foto' => true])
            + ['email' => 'Email', 'tanggal_mulai' => 'Tanggal Mulai'];
        $query = $this->query($request, $kategori)->orderBy('k.nama');

        return response()->streamDownload(function () use ($query, $kategori, $kolom) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', ...array_values($kolom)], escape: '');

            $query->get()->each(function ($k) use ($out, $kategori, $kolom) {
                $b = $this->barisDaftar($kategori, $k) + ['tanggal_mulai' => $this->tanggalForm($k->tanggal_mulai)];
                fputcsv($out, [$b['id'], ...array_map(fn ($x) => $b[$x] ?? '', array_keys($kolom))], escape: '');
            });

            fclose($out);
        }, $slug.'_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ query & tampilan

    private function query(Request $request, string $kategori): Builder
    {
        $area = DB::table('mk_karyawan_area')->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, COUNT(*) AS total_area');
        $sales = DB::table('mk_karyawan')->where('kategori', 'sales')->where('aktif', true)->whereNotNull('koordinator_id')
            ->groupBy('koordinator_id')->selectRaw('koordinator_id, COUNT(*) AS jumlah_sales');

        $q = DB::table('mk_karyawan as k')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoin('mk_wilayah_depo as w', 'w.id', '=', 'k.wilayah_depo_id')
            ->leftJoin('mk_area as aa', 'aa.id', '=', 'k.area_aktif_id')
            ->leftJoinSub($area, 'ar', 'ar.karyawan_id', '=', 'k.id')
            ->leftJoinSub($sales, 'js', 'js.koordinator_id', '=', 'k.id')
            ->where('k.kategori', $kategori)
            ->select(['k.*', 'd.nama as depo', 'ko.nama as koordinator', 'w.nama as wilayah_depo', 'aa.nama as area_aktif',
                DB::raw('COALESCE(ar.total_area, 0) as total_area'), DB::raw('COALESCE(js.jumlah_sales, 0) as jumlah_sales')]);

        return Akses::batasiKaryawan($q, $request->user());
    }

    private function kolom(string $kategori): array
    {
        return match ($kategori) {
            'sales' => ['foto' => 'Foto', 'nama' => 'Nama Lengkap', 'koordinator' => 'Koordinator', 'depo' => 'Depo', 'area_aktif' => 'Area Aktif',
                'total_area' => 'Total Area', 'no_hp' => 'No HP', 'aktif' => 'Status', 'diubah' => 'Diubah'],
            'koordinator' => ['foto' => 'Foto', 'nama' => 'Nama Lengkap', 'depo' => 'Depo', 'wilayah_depo' => 'Wilayah Depo', 'jumlah_sales' => 'Jumlah Sales',
                'no_hp' => 'No HP', 'aktif' => 'Status', 'diubah' => 'Diubah'],
            'supervisor' => ['foto' => 'Foto', 'nama' => 'Nama Lengkap', 'no_hp' => 'No HP', 'email' => 'Email', 'aktif' => 'Status', 'diubah' => 'Diubah'],
            'lainnya' => ['foto' => 'Foto', 'nama' => 'Nama Lengkap', 'jabatan' => 'Kategori', 'no_hp' => 'No HP', 'email' => 'Email', 'aktif' => 'Status', 'diubah' => 'Diubah'],
        };
    }

    /**
     * Nama area aktif per sales (maks. 2 nama + "+n"); bila tidak ada yang aktif, semua areanya jadi rute.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function namaAreaAktif(array $ids): array
    {
        return DB::table('mk_karyawan_area as ka')->join('mk_area as a', 'a.id', '=', 'ka.area_id')
            ->whereIn('ka.karyawan_id', $ids ?: [0])->orderBy('a.nama')->get(['ka.karyawan_id', 'ka.aktif', 'a.nama'])
            ->groupBy('karyawan_id')
            ->map(function ($area) {
                $aktif = $area->filter(fn ($a) => (bool) $a->aktif);
                $nama = ($aktif->isNotEmpty() ? $aktif : $area)->pluck('nama');

                return $nama->take(2)->implode(', ').($nama->count() > 2 ? ' +'.($nama->count() - 2) : '');
            })->all();
    }

    private function barisDaftar(string $kategori, object $k, ?string $areaAktif = null): array
    {
        return [
            'id' => (int) $k->id,
            'foto' => $k->foto ? $this->urlFoto($kategori, $k) : null,
            'nama' => $k->nama,
            'koordinator' => $k->koordinator,
            'depo' => $k->depo,
            'wilayah_depo' => $k->wilayah_depo,
            'area_aktif' => $areaAktif ?? $k->area_aktif,
            'total_area' => (int) $k->total_area,
            'jumlah_sales' => (int) $k->jumlah_sales,
            'jabatan' => $k->jabatan,
            'no_hp' => $k->no_hp,
            'email' => $k->email,
            'aktif' => $k->aktif === null ? '-' : ($k->aktif ? 'Aktif' : 'Tidak aktif'),
            'diubah' => $this->diubah($k),
        ];
    }

    private function propsForm(Request $request, ?object $item): array
    {
        [$slug, $kategori] = $this->menu($request);
        $area = $item ? $this->areaKaryawan((int) $item->id) : collect();
        $depo = $item?->depo_id ? (int) $item->depo_id : null;

        return [
            'slug' => $slug,
            'kategori' => $kategori,
            'judul' => self::JUDUL[$kategori][0],
            'nama' => self::JUDUL[$kategori][1],
            'item' => $item ? [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'foto' => $item->foto ? $this->urlFoto($kategori, $item) : null,
                'diubah' => $this->diubah($item),
                'akun' => DB::table('users')->where('karyawan_id', $item->id)->pluck('username')->all(),
            ] : null,
            'nilai' => [
                'nama' => $item->nama ?? '',
                'depo_id' => $depo ? (string) $depo : '',
                'koordinator_id' => $item?->koordinator_id ? (string) $item->koordinator_id : '',
                'wilayah_depo_id' => $item?->wilayah_depo_id ? (string) $item->wilayah_depo_id : '',
                'area_ids' => $area->pluck('area_id')->map(fn ($v) => (string) $v)->values()->all(),
                'area_aktif_ids' => $area->where('aktif', true)->pluck('area_id')->map(fn ($v) => (string) $v)->values()->all(),
                'jabatan' => $item->jabatan ?? '',
                'email' => $item->email ?? '',
                'no_hp' => $item->no_hp ?? '',
                'tanggal_mulai' => $this->tanggalForm($item->tanggal_mulai ?? null),
                'aktif' => $item ? ($item->aktif === null ? '' : ($item->aktif ? '1' : '0')) : '1',
            ] + collect(self::GAJI)->mapWithKeys(fn ($l, $k) => [$k => $item?->{$k} === null ? '' : (string) (int) round((float) $item->{$k})])->all(),
            'opsi' => ['depo' => $this->daftarDepo(), 'jabatan' => $this->daftarJabatan($item?->jabatan)] + $this->opsiDepo($kategori, $depo, $item),
            'dipakai' => $item ? $this->dipakai((int) $item->id) : [],
            'salesSeDepo' => $item && $kategori === 'sales' ? $this->salesSeDepo($item) : [],
            'riwayat' => $item ? DB::table('riwayat_admin')->where('objek', 'karyawan')->where('objek_id', $item->id)
                ->orderByDesc('id')->limit(15)->get(['aktor_nama', 'aksi', 'keterangan', 'created_at'])
                ->map(fn ($r) => ['oleh' => $r->aktor_nama, 'aksi' => $r->aksi, 'keterangan' => $r->keterangan, 'pada' => $this->waktu($r->created_at)]) : [],
            'bisa' => $this->bisa($request, $slug),
        ];
    }

    /** @return array{koordinator: list<array>, area: list<array>, wilayah: list<array>} */
    private function opsiDepo(string $kategori, ?int $depo, ?object $item = null): array
    {
        $opsi = ['koordinator' => [], 'area' => [], 'wilayah' => []];

        if (! $depo) {
            return $opsi;
        }

        if ($kategori === 'sales') {
            $opsi['koordinator'] = DB::table('mk_karyawan')->where('kategori', 'koordinator')->where('depo_id', $depo)
                ->where(fn ($w) => $w->where('aktif', true)->when($item?->koordinator_id, fn ($x) => $x->orWhere('id', $item->koordinator_id)))
                ->orderBy('nama')->get(['id', 'nama', 'aktif'])
                ->map(fn ($k) => ['value' => (string) $k->id, 'label' => $k->nama.($k->aktif ? '' : ' (tidak aktif)')])->all();
            $opsi['area'] = DB::table('mk_area')->where('depo_id', $depo)->orderBy('nama')->get(['id', 'nama', 'kode'])
                ->map(fn ($a) => ['value' => (string) $a->id, 'label' => $a->nama])->all();
        }

        if ($kategori === 'koordinator') {
            $opsi['wilayah'] = DB::table('mk_wilayah_depo')->where('depo_id', $depo)->orderBy('nama')->get(['id', 'nama'])
                ->map(fn ($w) => ['value' => (string) $w->id, 'label' => $w->nama])->all();
        }

        return $opsi;
    }

    private function salesSeDepo(object $item): array
    {
        return DB::table('mk_karyawan')->where('kategori', 'sales')->where('depo_id', $item->depo_id)->where('id', '!=', $item->id)
            ->orderByDesc('aktif')->orderBy('nama')->get(['id', 'nama', 'aktif'])
            ->map(fn ($k) => ['value' => (string) $k->id, 'label' => $k->nama.($k->aktif ? '' : ' (tidak aktif)')])->all();
    }

    // ------------------------------------------------------------------ simpan

    private function validasi(Request $request, string $kategori, ?object $lama): array
    {
        foreach (array_keys(self::GAJI) as $k) {
            if ($request->filled($k)) {
                $request->merge([$k => str_replace(['.', ' ', 'Rp'], '', (string) $request->input($k))]);
            }
        }

        $depo = $request->integer('depo_id') ?: null;
        $lainnya = $kategori === 'lainnya';
        $aturan = [
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'no_hp' => [$lainnya ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[0-9+\- ]{5,30}$/'],
            'tanggal_mulai' => [$lainnya ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'aktif' => ['required', Rule::in(['1', '0'])],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ] + collect(self::GAJI)->mapWithKeys(fn ($l, $k) => [$k => ['nullable', 'numeric', 'min:0', 'max:999999999']])->all();

        if (in_array($kategori, ['sales', 'koordinator'], true)) {
            $aturan['depo_id'] = ['required', Rule::exists('mk_depo', 'id')];
        }

        if ($kategori === 'sales') {
            $koordinator = array_column($this->opsiDepo('sales', $depo, $lama)['koordinator'], 'value');
            $area = array_column($this->opsiDepo('sales', $depo)['area'], 'value');
            $aturan['koordinator_id'] = ['required', Rule::in($koordinator)];
            $aturan['area_ids'] = ['required', 'array', 'min:1'];
            $aturan['area_ids.*'] = [Rule::in($area)];
            $aturan['area_aktif_ids'] = ['nullable', 'array'];
            $aturan['area_aktif_ids.*'] = [Rule::in((array) $request->input('area_ids', []))];
        }

        if ($kategori === 'koordinator') {
            $aturan['wilayah_depo_id'] = ['nullable', Rule::in(array_column($this->opsiDepo('koordinator', $depo)['wilayah'], 'value'))];
        }

        if ($lainnya) {
            $aturan['jabatan'] = ['required', Rule::in(array_column($this->daftarJabatan($lama?->jabatan), 'value'))];
        }

        return $request->validate($aturan, [
            'koordinator_id.in' => 'Pilih koordinator dari depo yang sama.',
            'area_ids.required' => 'Pilih minimal satu area.',
            'area_ids.*.in' => 'Area harus milik depo yang dipilih.',
            'area_aktif_ids.*.in' => 'Area aktif harus salah satu area sales ini.',
            'no_hp.regex' => 'No HP hanya boleh angka, spasi, + dan -.',
        ], [
            'nama' => 'nama lengkap', 'no_hp' => 'no HP', 'tanggal_mulai' => 'tanggal mulai bekerja', 'aktif' => 'status',
            'depo_id' => 'depo', 'koordinator_id' => 'koordinator', 'wilayah_depo_id' => 'wilayah depo', 'jabatan' => 'kategori',
        ] + array_map('mb_strtolower', self::GAJI));
    }

    /** Kolom mk_karyawan dari data valid (koordinator & area disimpan terpisah). */
    private function kolomSimpan(array $d): array
    {
        $kolom = [
            'nama' => trim($d['nama']),
            'email' => $d['email'] ?? null,
            'no_hp' => isset($d['no_hp']) ? trim($d['no_hp']) : null,
            'tanggal_mulai' => $d['tanggal_mulai'] ?? null,
            'aktif' => $d['aktif'] === '1',
        ];

        foreach (['depo_id', 'wilayah_depo_id'] as $k) {
            if (array_key_exists($k, $d)) {
                $kolom[$k] = $d[$k] ? (int) $d[$k] : null;
            }
        }

        if (array_key_exists('jabatan', $d)) {
            $kolom['jabatan'] = $d['jabatan'];
        }

        foreach (array_keys(self::GAJI) as $k) {
            $kolom[$k] = isset($d[$k]) && $d[$k] !== '' ? (float) $d[$k] : null;
        }

        return $kolom;
    }

    private function simpanRelasi(Request $request, string $kategori, int $id, array $d, ?object $lama): void
    {
        if ($kategori !== 'sales') {
            return;
        }

        $koordinator = (int) $d['koordinator_id'];

        if ((int) ($lama->koordinator_id ?? 0) !== $koordinator) {
            HierarkiSales::atur($id, $koordinator, $request->user()?->id);
        }

        $aktif = array_flip($d['area_aktif_ids'] ?? []);
        DB::table('mk_karyawan_area')->where('karyawan_id', $id)->delete();
        DB::table('mk_karyawan_area')->insert(array_map(fn ($a) => [
            'karyawan_id' => $id, 'area_id' => (int) $a, 'aktif' => isset($aktif[$a]),
        ], array_values(array_unique($d['area_ids']))));
        DB::table('mk_karyawan')->where('id', $id)->update(['area_aktif_id' => $this->areaAktifPertama($id)]);
    }

    private function areaAktifPertama(int $id): ?int
    {
        $aktif = DB::table('mk_karyawan_area')->where('karyawan_id', $id)->orderByDesc('aktif')->orderBy('area_id')->first();

        return $aktif && $aktif->aktif ? (int) $aktif->area_id : null;
    }

    private function areaKaryawan(int $id)
    {
        return DB::table('mk_karyawan_area')->where('karyawan_id', $id)->orderBy('area_id')->get()
            ->map(fn ($a) => (object) ['area_id' => (int) $a->area_id, 'aktif' => (bool) $a->aktif]);
    }

    private function simpanFoto(Request $request, int $id): ?string
    {
        $file = $request->file('foto');

        return $file instanceof UploadedFile
            ? $file->storeAs('karyawan', $id.'-'.now()->format('YmdHis').'.'.strtolower($file->extension()), self::DISK)
            : null;
    }

    /** @return list<string> */
    private function dipakai(int $id): array
    {
        $hasil = [];
        $akun = DB::table('users')->where('karyawan_id', $id)->count();
        $bawahan = DB::table('mk_karyawan')->where('koordinator_id', $id)->count();

        if ($akun) {
            $hasil[] = "{$akun} akun login";
        }

        if ($bawahan) {
            $hasil[] = "{$bawahan} sales di bawahnya";
        }

        // [tabel, kolom karyawan, label]; join visit mencatat koordinator dan sales di kolom terpisah.
        foreach ([['trx_absensi', ['karyawan_id'], 'absensi'], ['trx_kunjungan', ['karyawan_id'], 'kunjungan'],
            ['trx_penjualan', ['karyawan_id'], 'penjualan'], ['trx_canvassing', ['karyawan_id'], 'canvassing'],
            ['trx_join_visit', ['koordinator_id', 'sales_id'], 'join visit'], ['trx_outlet_baru', ['karyawan_id'], 'outlet baru']] as [$tabel, $kolom, $label]) {
            $kolom = array_values(array_filter($kolom, fn ($k) => Schema::hasColumn($tabel, $k)));

            if ($kolom && ($n = DB::table($tabel)->where(fn ($w) => array_map(fn ($k) => $w->orWhere($k, $id), $kolom))->count())) {
                $hasil[] = number_format($n, 0, ',', '.')." {$label}";
            }
        }

        return $hasil;
    }

    private function ringkasPerubahan(object $lama, array $d, $areaLama): array
    {
        $baru = $this->kolomSimpan($d);
        $label = ['nama' => 'Nama', 'email' => 'Email', 'no_hp' => 'No HP', 'tanggal_mulai' => 'Tanggal mulai', 'aktif' => 'Status',
            'depo_id' => 'Depo', 'wilayah_depo_id' => 'Wilayah depo', 'jabatan' => 'Kategori'] + self::GAJI;
        $nama = fn ($k, $v) => match ($k) {
            'aktif' => $v === null ? '-' : ($v ? 'Aktif' : 'Tidak aktif'),
            'depo_id' => $v ? DB::table('mk_depo')->where('id', $v)->value('nama') : '-',
            'wilayah_depo_id' => $v ? DB::table('mk_wilayah_depo')->where('id', $v)->value('nama') : '-',
            'tanggal_mulai' => $this->tanggalForm($v) ?: '-',
            default => $v === null || $v === '' ? '-' : (is_numeric($v) && isset(self::GAJI[$k]) ? number_format((float) $v, 0, ',', '.') : (string) $v),
        };
        $hasil = [];

        foreach ($baru as $k => $v) {
            $sebelum = $lama->{$k} ?? null;
            $sama = isset(self::GAJI[$k]) ? (float) $sebelum === (float) $v && ($sebelum === null) === ($v === null)
                : ($k === 'aktif' ? $sebelum !== null && (bool) $sebelum === $v : ($k === 'tanggal_mulai' ? $this->tanggalForm($sebelum) === ($v ?? '')
                    : (string) ($sebelum ?? '') === (string) ($v ?? '')));

            if (! $sama) {
                $hasil[] = ($label[$k] ?? $k).': '.$nama($k, $sebelum).' → '.$nama($k, $v);
            }
        }

        if (array_key_exists('koordinator_id', $d) && (int) ($lama->koordinator_id ?? 0) !== (int) $d['koordinator_id']) {
            $namaKoor = fn ($id) => $id ? DB::table('mk_karyawan')->where('id', $id)->value('nama') : '-';
            $hasil[] = 'Koordinator: '.$namaKoor($lama->koordinator_id).' → '.$namaKoor($d['koordinator_id']);
        }

        if (array_key_exists('area_ids', $d)) {
            $lamaIds = $areaLama->pluck('area_id')->sort()->values()->all();
            $baruIds = collect($d['area_ids'])->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
            $lamaAktif = $areaLama->where('aktif', true)->pluck('area_id')->sort()->values()->all();
            $baruAktif = collect($d['area_aktif_ids'] ?? [])->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();

            if ($lamaIds !== $baruIds) {
                $hasil[] = 'Area: '.count($lamaIds).' → '.count($baruIds).' area';
            }

            if ($lamaAktif !== $baruAktif) {
                $hasil[] = 'Area aktif: '.count($lamaAktif).' → '.count($baruAktif).' area';
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------ bantuan

    /** @return array{0: string, 1: string} [slug, kategori] */
    private function menu(Request $request): array
    {
        $slug = (string) $request->route('menu');
        abort_unless(isset(self::MENU[$slug]), 404);

        return [$slug, self::MENU[$slug]];
    }

    /** Karyawan kategori menu ini, dalam cakupan role akun. */
    private function cari(Request $request, int $id): object
    {
        [, $kategori] = $this->menu($request);

        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', $kategori)->where('k.id', $id), $request->user())
            ->select('k.*')->first() ?? abort(404);
    }

    private function daftarDepo(): array
    {
        return DB::table('mk_depo')->orderBy('nama')->get(['id', 'nama', 'aktif'])
            ->map(fn ($d) => ['value' => (string) $d->id, 'label' => $d->nama.($d->aktif === false || $d->aktif === 0 ? ' (tidak aktif)' : '')])->all();
    }

    private function daftarJabatan(?string $sekarang = null): array
    {
        $daftar = collect(self::JABATAN);

        // Tabel salinan Kategori Karyawan Marketama: kolom "kategori", kategori bawaan berakhiran " Default".
        if (Schema::hasColumn('kategori_karyawan', 'kategori')) {
            $daftar = $daftar->merge(DB::table('kategori_karyawan')->pluck('kategori'));
        }

        return $daftar->push($sekarang)->filter()->map(fn ($j) => trim(preg_replace('/\s+Default$/i', '', (string) $j)))
            ->reject(fn ($j) => in_array(mb_strtolower($j), ['sales', 'koordinator', 'supervisor nasional'], true))
            ->unique()->sort()->values()->map(fn ($j) => ['value' => $j, 'label' => $j])->all();
    }

    private function urlFoto(string $kategori, object $k): string
    {
        return '/'.array_search($kategori, self::MENU, true)."/{$k->id}/foto?v=".substr(md5((string) $k->foto), 0, 8);
    }

    private function jejak(Request $request): array
    {
        return ['diubah_oleh' => $request->user()?->username ?? $request->user()?->name, 'diubah_pada' => now()];
    }

    private function diubah(object $k): ?string
    {
        $pada = $this->waktu($k->diubah_pada ?? null);

        return $pada ? trim(($k->diubah_oleh ?? '').' · '.$pada, ' ·') : null;
    }

    private function waktu(mixed $waktu): ?string
    {
        return $waktu ? CarbonImmutable::parse($waktu, 'UTC')->setTimezone(Pengaturan::zona())->locale('id')->translatedFormat('j M Y H:i') : null;
    }

    /** Tanggal mulai tersimpan bebas (aplikasi lama: "dd/mm/yyyy", "1 Maret 2026", ISO); form memakai Y-m-d. */
    private function tanggalForm(mixed $nilai): string
    {
        $nilai = trim((string) $nilai);

        if ($nilai === '' || $nilai === '-') {
            return '';
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $t = CarbonImmutable::createFromFormat($format, $nilai);

                if ($t && $t->format($format) === $nilai) {
                    return $t->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        $bulan = ['januari' => 'January', 'februari' => 'February', 'maret' => 'March', 'april' => 'April', 'mei' => 'May', 'juni' => 'June',
            'juli' => 'July', 'agustus' => 'August', 'september' => 'September', 'oktober' => 'October', 'november' => 'November', 'desember' => 'December'];

        try {
            return CarbonImmutable::parse(str_ireplace(array_keys($bulan), array_values($bulan), $nilai))->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function bisa(Request $request, string $slug): array
    {
        return collect(['tambah', 'ubah', 'hapus', 'ekspor'])->mapWithKeys(fn ($a) => [$a => Akses::bisa($request->user(), $slug, $a)])->all();
    }
}
