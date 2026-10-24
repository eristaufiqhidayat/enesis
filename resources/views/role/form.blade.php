@php
    $bisaUbah = \App\Support\Akses\Akses::bisa(auth()->user(), 'role', $role ? 'ubah' : 'tambah');
    $bisaHapus = $role && ! $role->bawaan && \App\Support\Akses\Akses::bisa(auth()->user(), 'role', 'hapus');
    $superAdmin = $role && $role->kode === 'super_admin';
    $nilai = fn ($menu, $aksi) => (bool) (old("hak.$menu.$aksi") ?? ($hak[$menu][$aksi] ?? false));
@endphp
@extends('layouts.app')

@section('title', $role ? 'Role ' . $role->nama : 'Tambah role')
@section('breadcrumb_slug', 'role')

@section('content')
    <x-ui.molecules.page-header :title="$role ? 'Edit role: ' . $role->nama : 'Tambah role'">
        <x-slot:subtitle>{{ $role ? 'Dipakai ' . number_format($jumlahAkun, 0, ',', '.') . ' akun. Perubahan berlaku pada halaman berikutnya yang mereka buka.' : 'Role baru belum punya anggota; pilih role ini di form akun.' }}</x-slot:subtitle>
            <x-slot:actions>
                @if ($role)
                <x-ui.atoms.button tag="a" variant="light" href="{{ route('role.anggota', $role->id) }}">Lihat {{ number_format($jumlahAkun, 0, ',', '.') }} anggota</x-ui.atoms.button>
                @if ($bisaHapus)
                    <x-ui.atoms.button variant="outline-danger" data-modal-open="modal-hapus-role">Hapus role</x-ui.atoms.button>
                @endif
                @endif
            </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <form method="POST" action="{{ $role ? route('role.update', $role->id) : route('role.store') }}">
        @csrf
        @if ($role) @method('PUT') @endif

        <fieldset @disabled(! $bisaUbah) class="contents">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-3">
                <x-ui.molecules.card>
                    <div class="mb-3">
                        <x-ui.atoms.label for="nama">Nama role</x-ui.atoms.label>
                        <x-ui.atoms.input id="nama" name="nama" required maxlength="80" value="{{ old('nama', $role->nama ?? '') }}" />
                    </div>
                    <div class="mb-3">
                        <x-ui.atoms.label for="keterangan">Keterangan</x-ui.atoms.label>
                        <x-ui.atoms.textarea id="keterangan" name="keterangan" rows="2" maxlength="255">{{ old('keterangan', $role->keterangan ?? '') }}</x-ui.atoms.textarea>
                    </div>
                    <fieldset>
                        <legend class="form-label">Bisa login di</legend>
                        <label class="form-check mb-1">
                            <x-ui.atoms.checkbox name="login_web" value="1" :checked="(bool) old('login_web', $role->login_web ?? true)" :disabled="$superAdmin" />
                            Web Enesis
                        </label>
                        @if ($superAdmin) <input type="hidden" name="login_web" value="1"> @endif
                        <br>
                        <label class="form-check">
                            <x-ui.atoms.checkbox name="login_mobile" value="1" :checked="(bool) old('login_mobile', $role->login_mobile ?? false)" />
                            Aplikasi HP (motoris / koordinator)
                        </label>
                    </fieldset>
                </x-ui.molecules.card>

                <x-ui.molecules.card class="lg:col-span-2">
                    <fieldset>
                        <legend class="form-label">Cakupan data</legend>
                        <p class="text-xs text-gray-500">Data di luar cakupan tidak tampil dan tidak bisa diubah. Saat ini berlaku di menu kelola akun; menu lain menyusul.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach ($cakupan as $kode => [$label, $desc])
                                <label class="flex items-start gap-2 rounded-md border border-gray-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                    <x-ui.atoms.radio name="cakupan" value="{{ $kode }}" class="mt-0.5" :checked="old('cakupan', $role->cakupan ?? 'depo') === $kode" />
                                    <span><strong>{{ $label }}</strong><br><span class="text-gray-500">{{ $desc }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </x-ui.molecules.card>
            </div>

            @if ($superAdmin)
                <x-ui.molecules.alert variant="info" class="mb-3">Super Admin selalu punya semua hak di semua menu, jadi matriks hak tidak dipakai.</x-ui.molecules.alert>
            @else
                <x-ui.molecules.card body-class="overflow-x-auto" class="mb-3">
                    <x-slot:header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h6 class="mb-0 font-semibold">Hak akses per menu</h6>
                                <span class="text-xs text-gray-500">Mengikuti grup menu di sidebar. Tanpa "Lihat", menu tidak tampil dan aksi lain tidak berlaku.</span>
                            </div>
                            <div class="w-56">
                                <x-ui.atoms.label for="cari_menu" class="sr-only">Cari menu</x-ui.atoms.label>
                                <x-ui.atoms.input type="search" id="cari_menu" placeholder="Cari menu…" />
                            </div>
                        </div>
                    </x-slot:header>
                    <table class="data-table" id="matriks_hak">
                        <thead>
                            <tr>
                                <th scope="col">Menu</th>
                                @foreach ($aksi as $label)
                                    <th scope="col" class="text-center w-20">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        @foreach ($grup as $g)
                            <tbody data-grup>
                                <tr class="bg-gray-50">
                                    <th scope="rowgroup" class="text-xs font-bold uppercase tracking-wide">{{ $g['label'] }}</th>
                                    @foreach ($aksi as $kodeAksi => $label)
                                        <td class="text-center">
                                            <x-ui.atoms.checkbox data-semua="{{ $kodeAksi }}" aria-label="{{ $label }} semua menu grup {{ $g['label'] }}" title="Semua di grup ini" />
                                        </td>
                                    @endforeach
                                </tr>
                                @foreach ($g['menu'] as $m)
                                    <tr data-menu="{{ mb_strtolower($m['title']) }}">
                                        <th scope="row" class="font-normal ps-5">{{ $m['title'] }}</th>
                                        @foreach ($aksi as $kodeAksi => $label)
                                            <td class="text-center">
                                                <x-ui.atoms.checkbox name="hak[{{ $m['slug'] }}][{{ $kodeAksi }}]" value="1" data-aksi="{{ $kodeAksi }}"
                                                    :checked="$nilai($m['slug'], $kodeAksi)" aria-label="{{ $label }} {{ $m['title'] }}" />
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </x-ui.molecules.card>
            @endif

            @if ($bisaUbah)
                <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-white px-3 py-2">
                    <span class="text-xs text-gray-500" id="status_ubah">Belum ada perubahan.</span>
                    <div class="flex gap-2">
                        <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('role.index') }}">Batal</x-ui.atoms.button>
                        <x-ui.atoms.button type="submit">Simpan role</x-ui.atoms.button>
                    </div>
                </div>
            @endif
        </fieldset>
    </form>

    @if ($bisaHapus)
        <x-ui.molecules.modal id="modal-hapus-role" data-modal role="dialog" aria-modal="true" aria-labelledby="judul-hapus-role">
            <form method="POST" action="{{ route('role.destroy', $role->id) }}" class="modal-content">
                @csrf
                @method('DELETE')
                <div class="modal-header"><h6 id="judul-hapus-role" class="mb-0 font-semibold">Hapus role {{ $role->nama }}?</h6><button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button></div>
                <div class="modal-body">{{ $jumlahAkun ? 'Role ini masih dipakai ' . $jumlahAkun . ' akun. Pindahkan dulu anggotanya ke role lain.' : 'Role tanpa anggota ini dihapus permanen.' }}</div>
                <div class="modal-footer flex justify-end gap-2">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Batal</x-ui.atoms.button>
                    @unless ($jumlahAkun)
                        <x-ui.atoms.button type="submit" variant="outline-danger">Hapus role</x-ui.atoms.button>
                    @endunless
                </div>
            </form>
        </x-ui.molecules.modal>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const tabel = document.getElementById('matriks_hak');
            const status = document.getElementById('status_ubah');
            if (!tabel) return;

            // Centang di baris grup = centang/hapus aksi itu untuk semua menu di grup.
            const segarkanGrup = (tbody) => tbody.querySelectorAll('[data-semua]').forEach((s) => {
                const kotak = [...tbody.querySelectorAll(`[data-aksi="${s.dataset.semua}"]`)];
                s.checked = kotak.length > 0 && kotak.every((k) => k.checked);
                s.indeterminate = !s.checked && kotak.some((k) => k.checked);
            });

            tabel.querySelectorAll('[data-grup]').forEach((tbody) => {
                segarkanGrup(tbody);
                tbody.querySelectorAll('[data-semua]').forEach((s) => s.addEventListener('change', () => {
                    tbody.querySelectorAll(`[data-aksi="${s.dataset.semua}"]`).forEach((k) => { k.checked = s.checked; });
                    if (s.checked && s.dataset.semua !== 'lihat') tbody.querySelectorAll('[data-aksi="lihat"]').forEach((k) => { k.checked = true; });
                    segarkanGrup(tbody);
                    tandaiUbah();
                }));
            });

            // Aksi selain "lihat" otomatis menyalakan "lihat"; mematikan "lihat" mematikan semuanya.
            let jumlahUbah = 0;
            const tandaiUbah = () => {
                jumlahUbah++;
                if (status) status.textContent = 'Ada perubahan belum disimpan.';
            };
            tabel.querySelectorAll('[data-aksi]').forEach((k) => k.addEventListener('change', () => {
                const baris = k.closest('tr');
                if (k.dataset.aksi !== 'lihat' && k.checked) baris.querySelector('[data-aksi="lihat"]').checked = true;
                if (k.dataset.aksi === 'lihat' && !k.checked) baris.querySelectorAll('[data-aksi]').forEach((x) => { x.checked = false; });
                segarkanGrup(k.closest('[data-grup]'));
                tandaiUbah();
            }));

            document.getElementById('cari_menu')?.addEventListener('input', (e) => {
                const q = e.target.value.trim().toLowerCase();
                tabel.querySelectorAll('tr[data-menu]').forEach((tr) => tr.classList.toggle('hidden', q !== '' && !tr.dataset.menu.includes(q)));
            });
        })();
    </script>
@endpush
