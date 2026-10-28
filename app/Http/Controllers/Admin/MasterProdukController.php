<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Akses\Akses;
use App\Support\Master\MasterProduk;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// CRUD master produk: Produk, Produk Kompetitor, Kategori Produk, Brand, Satuan Produk (tabel mk_*, ID asli Marketama).
// Parameter route "menu" = slug menu; definisi kolom & field di App\Support\Master\MasterProduk.
// Perubahan sampai ke aplikasi HP lewat /api/mobile/v1/master (kolom diubah_pada).
class MasterProdukController extends Controller
{
    private const DISK = 'local';

    public function index(Request $request): Response
    {
        $slug = $this->slug($request);
        $def = MasterProduk::def($slug);
        $q = trim((string) $request->query('q'));

        $query = MasterProduk::query($slug)->when($q !== '', function ($w) use ($def, $q) {
            $w->where(function ($x) use ($def, $q) {
                foreach ($def['cari'] as $kolom) {
                    $x->orWhereRaw("LOWER({$kolom}) LIKE ?", ['%'.mb_strtolower($q).'%']);
                }
            });
        });

        $baris = MasterProduk::urutkan($slug, $query)
            ->paginate(25)->withQueryString()->onEachSide(1)
            ->through(fn ($r) => $this->barisDaftar($slug, $r));

        return Inertia::render('MasterProduk/Index', [
            'slug' => $slug,
            'judul' => $def['judul'],
            'nama' => $def['nama'],
            'keterangan' => $def['keterangan'],
            'kolom' => $def['kolom'],
            'baris' => $baris,
            'q' => $q,
            'total' => DB::table($def['tabel'])->count(),
            'bisa' => $this->bisa($request, $slug),
        ]);
    }

    // Tambah produk dibuka sebagai modal di daftar: permintaan JSON mengembalikan data form kosong.
    public function create(Request $request): Response|JsonResponse
    {
        $props = $this->propsForm($request, null);

        return $request->expectsJson() ? response()->json($props) : Inertia::render('MasterProduk/Form', $props);
    }

    // Edit dibuka sebagai modal di daftar: permintaan JSON mengembalikan data form yang sama dengan props halaman.
    public function edit(Request $request, int $id): Response|JsonResponse
    {
        $slug = $this->slug($request);
        $props = $this->propsForm($request, DB::table(MasterProduk::def($slug)['tabel'])->find($id) ?? abort(404));

        return $request->expectsJson() ? response()->json($props) : Inertia::render('MasterProduk/Form', $props);
    }

    public function store(Request $request): RedirectResponse
    {
        $slug = $this->slug($request);
        $def = MasterProduk::def($slug);
        $data = $this->validasi($request, $slug, null);
        $id = MasterProduk::idBaru($slug);
        $foto = $this->simpanFoto($request, $slug, $id);

        DB::table($def['tabel'])->insert(['id' => $id] + $data + ($foto ? ['foto' => $foto] : []) + $this->jejak($request));
        Akses::catat($request->user(), $slug, $id, 'dibuat', ucfirst($def['nama']).' dibuat: '.($data['nama'] ?? $id));

        return redirect("/{$slug}")->with('sukses', ucfirst($def['nama']).' '.($data['nama'] ?? '').' ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $slug = $this->slug($request);
        $def = MasterProduk::def($slug);
        $lama = DB::table($def['tabel'])->find($id) ?? abort(404);
        $data = $this->validasi($request, $slug, $lama);
        $foto = $this->simpanFoto($request, $slug, $id);

        if ($foto) {
            $data['foto'] = $foto;
        }

        $perubahan = $this->ringkasPerubahan($slug, $lama, $data);
        DB::table($def['tabel'])->where('id', $id)->update($data + $this->jejak($request));

        if ($foto && $lama->foto) {
            Storage::disk(self::DISK)->delete($lama->foto);
        }

        foreach ($perubahan ?: ['Disimpan tanpa perubahan isi'] as $baris) {
            Akses::catat($request->user(), $slug, $id, 'diubah', $baris);
        }

        // Kembali ke halaman asal: daftar (modal edit) atau halaman edit langsung.
        return back()->with('sukses', ucfirst($def['nama']).' '.($data['nama'] ?? '').' disimpan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $slug = $this->slug($request);
        $def = MasterProduk::def($slug);
        $lama = DB::table($def['tabel'])->find($id) ?? abort(404);

        if ($dipakai = MasterProduk::dipakai($slug, $id)) {
            return back()->withErrors(['hapus' => ucfirst($def['nama']).' ini masih dipakai '.implode(', ', $dipakai).', jadi tidak bisa dihapus.']);
        }

        DB::table($def['tabel'])->where('id', $id)->delete();

        if ($lama->foto ?? null) {
            Storage::disk(self::DISK)->delete($lama->foto);
        }

        Akses::catat($request->user(), $slug, $id, 'dihapus', ucfirst($def['nama']).' dihapus: '.($lama->nama ?? $id));

        return redirect("/{$slug}")->with('sukses', ucfirst($def['nama']).' '.($lama->nama ?? '').' dihapus.');
    }

    public function foto(Request $request, int $id)
    {
        $slug = $this->slug($request);
        $path = DB::table(MasterProduk::def($slug)['tabel'])->where('id', $id)->value('foto');

        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
    }

    public function ekspor(Request $request): StreamedResponse
    {
        $slug = $this->slug($request);
        $def = MasterProduk::def($slug);
        $kolom = array_diff_key($def['kolom'], ['foto' => true]);

        return response()->streamDownload(function () use ($slug, $kolom) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($out, ['ID', ...array_values($kolom)], escape: '');

            MasterProduk::urutkan($slug, MasterProduk::query($slug))->get()->each(function ($r) use ($out, $slug, $kolom) {
                $b = $this->barisDaftar($slug, $r);
                fputcsv($out, [$b['id'], ...array_map(fn ($k) => $b[$k] ?? '', array_keys($kolom))], escape: '');
            });

            fclose($out);
        }, $slug.'_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> props halaman/modal form tambah (item null) atau edit */
    private function propsForm(Request $request, ?object $item): array
    {
        $slug = $this->slug($request);
        $def = MasterProduk::def($slug);

        $field = array_map(function ($f) use ($item) {
            [$nama, $label, $jenis, $wajib, $maks, $bantuan] = $f;
            $sumber = str_starts_with($jenis, 'pilihan:') ? substr($jenis, 8) : null;

            return [
                'nama' => $nama, 'label' => $label, 'jenis' => $sumber ? 'pilihan' : $jenis, 'wajib' => $wajib, 'maks' => $maks, 'bantuan' => $bantuan,
                'opsi' => $sumber ? MasterProduk::opsi($sumber, $item?->{$nama} ?? null) : null,
            ];
        }, $def['field']);

        $nilai = [];
        foreach ($def['field'] as [$nama, , $jenis]) {
            if ($jenis === 'foto') {
                continue;
            }
            $v = $item?->{$nama} ?? null;
            $nilai[$nama] = match (true) {
                $v === null => $nama === 'aktif' ? '1' : '',
                is_bool($v) => $v ? '1' : '0',
                $jenis === 'rupiah' || $jenis === 'angka' => $this->angkaForm($v),
                default => (string) $v,
            };
        }

        $bisa = $this->bisa($request, $slug);

        return [
            'slug' => $slug,
            'judul' => $def['judul'],
            'nama' => $def['nama'],
            'item' => $item ? [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'foto' => ($item->foto ?? null) ? "/{$slug}/{$item->id}/foto?v=".substr(md5((string) $item->foto), 0, 8) : null,
                'foto_lama' => $item->foto_lama ?? null,
                'diubah' => $this->diubah($item),
            ] : null,
            'field' => $field,
            'nilai' => $nilai ?: new \stdClass,
            'dipakai' => $item ? MasterProduk::dipakai($slug, (int) $item->id) : [],
            'riwayat' => $item ? DB::table('riwayat_admin')->where('objek', $slug)->where('objek_id', $item->id)
                ->orderByDesc('id')->limit(15)->get(['aktor_nama', 'aksi', 'keterangan', 'created_at'])
                ->map(fn ($r) => ['oleh' => $r->aktor_nama, 'aksi' => $r->aksi, 'keterangan' => $r->keterangan, 'pada' => $this->waktu($r->created_at)]) : [],
            'bisa' => $bisa,
        ];
    }

    /** @return array<string, mixed> data siap simpan (tanpa foto) */
    private function validasi(Request $request, string $slug, ?object $lama): array
    {
        $def = MasterProduk::def($slug);
        $aturan = [];
        $label = [];

        // Angka Rupiah boleh diketik dengan titik ribuan ("5.750").
        $request->merge(collect($def['field'])->filter(fn ($f) => $f[2] === 'rupiah' && $request->filled($f[0]))
            ->mapWithKeys(fn ($f) => [$f[0] => str_replace(['.', ' ', 'Rp'], '', (string) $request->input($f[0]))])->all());

        foreach ($def['field'] as [$nama, $teks, $jenis, $wajib, $maks]) {
            $label[$nama] = mb_strtolower($teks);
            $dasar = [$wajib ? 'required' : 'nullable'];

            $aturan[$nama] = match (true) {
                $jenis === 'text', $jenis === 'textarea' => [...$dasar, 'string', 'max:'.$maks],
                $jenis === 'angka' => [...$dasar, 'numeric', 'min:0', 'max:999999'],
                $jenis === 'rupiah' => [...$dasar, 'numeric', 'min:0', 'max:9999999999'],
                $jenis === 'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
                str_starts_with($jenis, 'pilihan:') => [...$dasar, Rule::in(MasterProduk::idOpsi(substr($jenis, 8), $lama?->{$nama} ?? null))],
            };

            if (in_array($nama, $def['unik'], true)) {
                $aturan[$nama][] = Rule::unique($def['tabel'], $nama)->ignore($lama?->id);
            }
        }

        $data = $request->validate($aturan, [
            'unique' => ':Attribute ini sudah dipakai.',
            'in' => 'Pilih :attribute dari daftar.',
        ], $label);

        unset($data['foto']);

        foreach ($def['field'] as [$nama, , $jenis]) {
            if (! array_key_exists($nama, $data)) {
                continue;
            }
            $v = $data[$nama];
            $data[$nama] = match (true) {
                $v === null || $v === '' => null,
                in_array($nama, ['produk_fokus', 'aktif'], true) => (bool) (int) $v,
                str_starts_with($jenis, 'pilihan:') => (int) $v,
                default => is_string($v) ? trim($v) : $v,
            };
        }

        return $data;
    }

    private function simpanFoto(Request $request, string $slug, int $id): ?string
    {
        $file = $request->file('foto');

        return $file instanceof UploadedFile
            ? $file->storeAs("master-produk/{$slug}", $id.'-'.now()->format('YmdHis').'.'.strtolower($file->extension()), self::DISK)
            : null;
    }

    private function ringkasPerubahan(string $slug, object $lama, array $baru): array
    {
        $label = collect(MasterProduk::def($slug)['field'])->mapWithKeys(fn ($f) => [$f[0] => $f[1]]);
        $hasil = [];

        foreach ($baru as $kolom => $nilai) {
            $sebelum = $lama->{$kolom} ?? null;

            if ($kolom === 'foto') {
                $hasil[] = 'Foto diganti';

                continue;
            }

            if ($this->sama($sebelum, $nilai)) {
                continue;
            }

            $hasil[] = $label[$kolom].': '.$this->teks($sebelum).' → '.$this->teks($nilai);
        }

        return $hasil;
    }

    private function sama(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        if (is_bool($b)) {
            return $a !== null && (bool) $a === $b;
        }

        return (string) ($a ?? '') === (string) ($b ?? '');
    }

    private function teks(mixed $v): string
    {
        return match (true) {
            $v === null || $v === '' => '-',
            is_bool($v) => $v ? 'Ya' : 'Tidak',
            is_numeric($v) => $this->angkaForm($v),
            default => mb_strimwidth((string) $v, 0, 60, '…'),
        };
    }

    private function barisDaftar(string $slug, object $r): array
    {
        $b = ['id' => (int) $r->id, 'nama' => $r->nama, 'diubah' => $this->diubah($r)];

        return $b + match ($slug) {
            'produk' => [
                'foto' => $r->foto ? "/{$slug}/{$r->id}/foto?v=".substr(md5((string) $r->foto), 0, 8) : null,
                'kode' => $r->kode,
                'merk' => $r->merk,
                'merk_aktif' => $r->merk_aktif === null ? null : (bool) $r->merk_aktif,
                'kategori' => $r->kategori,
                'satuan' => $r->satuan,
                'harga_beli' => $this->rupiah($r->harga_beli),
                'harga_jual' => $this->rupiah($r->harga_jual),
                'produk_fokus' => Pengaturan::poinProduk($r)['fokus'] ? 'Ya' : 'Tidak',
                'drop_size' => $r->drop_size === null ? null : $this->angkaForm($r->drop_size),
                'maks_poin_per_invoice' => Pengaturan::poinProduk($r)['maks_poin'],
            ],
            'produk_kompetitor' => [
                'foto' => $r->foto ? "/{$slug}/{$r->id}/foto?v=".substr(md5((string) $r->foto), 0, 8) : null,
                'satuan' => $r->satuan,
            ],
            'kategori_produk' => ['kode' => $r->kode, 'total_produk' => (int) $r->total_produk],
            'merk_produk' => ['kode' => $r->kode, 'total_produk' => (int) $r->total_produk, 'aktif' => $r->aktif ? 'Aktif' : 'Tidak aktif'],
            'satuan_produk' => ['total_produk' => (int) $r->total_produk],
        };
    }

    private function jejak(Request $request): array
    {
        return ['diubah_oleh' => $request->user()?->username ?? $request->user()?->name, 'diubah_pada' => now()];
    }

    private function diubah(object $r): ?string
    {
        $pada = $this->waktu($r->diubah_pada ?? null);

        return $pada ? trim(($r->diubah_oleh ?? '').' · '.$pada, ' ·') : null;
    }

    private function waktu(mixed $waktu): ?string
    {
        return $waktu ? CarbonImmutable::parse($waktu, 'UTC')->setTimezone(Pengaturan::zona())->locale('id')->translatedFormat('j M Y H:i') : null;
    }

    private function rupiah(mixed $v): ?string
    {
        return $v === null ? null : 'Rp '.number_format((float) $v, 0, ',', '.');
    }

    private function angkaForm(mixed $v): string
    {
        $f = (float) $v;

        return $f == floor($f) ? (string) (int) $f : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
    }

    private function bisa(Request $request, string $slug): array
    {
        return collect(['tambah', 'ubah', 'hapus', 'ekspor'])->mapWithKeys(fn ($a) => [$a => Akses::bisa($request->user(), $slug, $a)])->all();
    }

    private function slug(Request $request): string
    {
        $slug = (string) $request->route('menu');
        abort_unless(MasterProduk::ada($slug), 404);

        return $slug;
    }
}
