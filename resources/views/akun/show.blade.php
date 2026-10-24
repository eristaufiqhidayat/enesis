@php
    $bisaUbah = \App\Support\Akses\Akses::bisa(auth()->user(), $menu, 'ubah');
    $bisaHapus = \App\Support\Akses\Akses::bisa(auth()->user(), $menu, 'hapus');
    $ket = \App\Http\Controllers\Admin\AkunController::keterangan($akun);
    $sesiAktif = $sesi->firstWhere('revoked_at', null);
    $waktu = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') : '—';
    $alasanSesi = ['logout' => 'Logout', 'device_replaced' => 'Diganti HP lain', 'relogin' => 'Login ulang', 'idle' => 'Lama tidak dipakai', 'admin' => 'Dicabut admin'];
@endphp
@extends('layouts.app')

@section('title', $akun->username . ' · ' . $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.flash />

    <x-ui.molecules.card class="mb-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-lg font-bold text-blue-800">{{ mb_strtoupper(mb_substr($akun->karyawan ?? $akun->username, 0, 1)) }}</span>
                <div>
                    <h5 class="mb-0 font-semibold">{{ $akun->karyawan ?? $akun->name }}</h5>
                    <div class="text-gray-500">{{ '@' . $akun->username }} · {{ $judul }} · role {{ $akun->role ?? '-' }}{{ $akun->sumber_jenis ? ' · dari aplikasi lama' : '' }}</div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <x-ui.atoms.badge :variant="$akun->aktif ? 'success' : 'secondary'">{{ $akun->aktif ? 'Aktif' : 'Tidak aktif' }}</x-ui.atoms.badge>
                        @if ($ket) <x-ui.atoms.badge variant="warning">{{ $ket }}</x-ui.atoms.badge> @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($bisaUbah)
                    <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('akun.' . $menu . '.edit', $akun->id) }}"><x-ui.atoms.icon name="pen" class="me-1" /> Edit</x-ui.atoms.button>
                    <x-ui.atoms.button variant="outline-secondary" data-modal-open="modal-password"><x-ui.atoms.icon name="key" class="me-1" /> Atur password</x-ui.atoms.button>
                    <x-ui.atoms.button :variant="$akun->aktif ? 'outline-warning' : 'success'" data-modal-open="modal-status">{{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</x-ui.atoms.button>
                @endif
                @if ($bisaHapus)
                    <x-ui.atoms.button variant="outline-danger" data-modal-open="modal-hapus"><x-ui.atoms.icon name="trash" class="me-1" /> Hapus</x-ui.atoms.button>
                @endif
            </div>
        </div>
    </x-ui.molecules.card>

    @if ($akun->perlu_keputusan)
        <x-ui.molecules.alert variant="danger" class="mb-3">
            <strong>Perlu keputusan admin.</strong>
            @if ($kembar->isNotEmpty())
                Karyawan ini punya akun lain:
                @foreach ($kembar as $k)
                    <a href="{{ route('akun.' . (array_search($k->kategori, \App\Http\Controllers\Admin\AkunController::MENU, true) ?: $menu) . '.show', $k->id) }}">{{ $k->username }}</a>{{ $k->aktif ? ' (aktif)' : ' (nonaktif)' }}{{ $loop->last ? '.' : ',' }}
                @endforeach
                Pilih akun yang dipakai; akun aktif lain milik karyawan yang sama akan dinonaktifkan.
            @else
                {{ $akun->catatan_migrasi ?: 'Periksa akun ini lalu tandai sudah diputuskan.' }}
            @endif
            @if ($bisaUbah)
                <form method="POST" action="{{ route('akun.' . $menu . '.pakai', $akun->id) }}" class="mt-2">
                    @csrf
                    <x-ui.atoms.button type="submit">{{ $kembar->where('aktif', true)->isNotEmpty() ? 'Pakai akun ini' : 'Tandai sudah diputuskan' }}</x-ui.atoms.button>
                </form>
            @endif
        </x-ui.molecules.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
        <x-ui.molecules.card>
            <x-slot:header><h6 class="mb-0 font-semibold">Data karyawan</h6></x-slot:header>
            @if ($karyawan)
                <dl class="grid grid-cols-2 gap-3 mb-0">
                    <div><dt class="text-xs text-gray-500">ID karyawan</dt><dd class="font-semibold mb-0">{{ $karyawan->id }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Status karyawan</dt><dd class="font-semibold mb-0">{{ $karyawan->aktif === null ? '-' : ($karyawan->aktif ? 'Aktif' : 'Tidak aktif') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Depo</dt><dd class="font-semibold mb-0">{{ $akun->depo ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Koordinator</dt><dd class="font-semibold mb-0">{{ $akun->koordinator ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Area rute aktif</dt><dd class="font-semibold mb-0">{{ $area }} area</dd></div>
                    <div><dt class="text-xs text-gray-500">Login terakhir</dt><dd class="font-semibold mb-0">{{ $waktu($akun->login_terakhir_pada) }}</dd></div>
                </dl>
            @else
                <p class="text-gray-500 mb-0">Akun ini belum terhubung ke data karyawan.@if ($bisaUbah) <a href="{{ route('akun.' . $menu . '.edit', $akun->id) }}">Hubungkan</a>.@endif</p>
            @endif
        </x-ui.molecules.card>

        <x-ui.molecules.card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-2">
                    <h6 class="mb-0 font-semibold">Sesi HP aktif</h6>
                    @if ($sesiAktif && $bisaUbah)
                        <x-ui.atoms.button variant="outline-danger" data-modal-open="modal-sesi">Cabut sesi</x-ui.atoms.button>
                    @endif
                </div>
            </x-slot:header>
            @if ($sesiAktif)
                <div class="flex items-center gap-3 rounded-md bg-gray-50 p-3 mb-2">
                    <x-ui.atoms.icon name="mobile-alt" class="text-2xl text-blue-600" />
                    <div>
                        <strong>{{ $sesiAktif->device_model ?: 'HP' }}</strong>
                        <div class="text-gray-500">Aplikasi v{{ $sesiAktif->app_version ?? '?' }} · login {{ $waktu($sesiAktif->created_at) }} · dipakai {{ $waktu($sesiAktif->last_used_at) }}</div>
                    </div>
                </div>
            @else
                <p class="text-gray-500">Tidak ada HP yang sedang login dengan akun ini.</p>
            @endif
            <p class="text-xs text-gray-500 mb-0">Satu akun hanya aktif di satu HP. Cabut sesi bila HP hilang atau berganti; data yang belum terkirim di HP lama ikut hilang.</p>
        </x-ui.molecules.card>
    </div>

    @include('akun._sales-bawahan')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <x-ui.molecules.card body-class="overflow-x-auto">
            <x-slot:header><h6 class="mb-0 font-semibold">Riwayat sesi HP</h6></x-slot:header>
            <table class="data-table">
                <thead><tr><th scope="col">HP</th><th scope="col">Versi</th><th scope="col">Login</th><th scope="col">Terakhir dipakai</th><th scope="col">Berakhir</th></tr></thead>
                <tbody>
                    @forelse ($sesi as $s)
                        <tr>
                            <td>{{ $s->device_model ?: 'HP' }}</td>
                            <td>{{ $s->app_version ?? '-' }}</td>
                            <td>{{ $waktu($s->created_at) }}</td>
                            <td>{{ $waktu($s->last_used_at) }}</td>
                            <td>
                                @if ($s->revoked_at)
                                    {{ $alasanSesi[$s->revoked_reason] ?? $s->revoked_reason }} · {{ $waktu($s->revoked_at) }}
                                @else
                                    <x-ui.atoms.badge variant="success">Masih aktif</x-ui.atoms.badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gray-500 py-3">Belum pernah login di aplikasi HP.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.molecules.card>

        <x-ui.molecules.card>
            <x-slot:header><h6 class="mb-0 font-semibold">Riwayat perubahan akun</h6></x-slot:header>
            <ol class="list-none m-0 p-0 flex flex-col gap-2">
                @forelse ($riwayat as $r)
                    <li>
                        <div>{{ $r->keterangan ?? $r->aksi }}</div>
                        <div class="text-xs text-gray-500">{{ $r->aktor_nama ?? 'sistem' }} · {{ $waktu($r->created_at) }}</div>
                    </li>
                @empty
                    <li class="text-gray-500">Belum ada perubahan oleh admin.</li>
                @endforelse
            </ol>
        </x-ui.molecules.card>
    </div>

    @if ($bisaUbah)
        <x-ui.molecules.modal id="modal-password" data-modal role="dialog" aria-modal="true" aria-labelledby="judul-password">
            <form method="POST" action="{{ route('akun.' . $menu . '.password', $akun->id) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h6 id="judul-password" class="mb-0 font-semibold">Atur password awal {{ $akun->username }}?</h6><button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button></div>
                <div class="modal-body">Password lama tidak berlaku lagi. Password awal acak baru ditampilkan <strong>sekali</strong>, dan pengguna wajib menggantinya saat login berikutnya (web maupun HP).</div>
                <div class="modal-footer flex justify-end gap-2">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Batal</x-ui.atoms.button>
                    <x-ui.atoms.button type="submit">Buat password awal</x-ui.atoms.button>
                </div>
            </form>
        </x-ui.molecules.modal>

        <x-ui.molecules.modal id="modal-sesi" data-modal role="dialog" aria-modal="true" aria-labelledby="judul-sesi">
            <form method="POST" action="{{ route('akun.' . $menu . '.cabut-sesi', $akun->id) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h6 id="judul-sesi" class="mb-0 font-semibold">Cabut sesi HP {{ $akun->username }}?</h6><button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button></div>
                <div class="modal-body">HP {{ $sesiAktif->device_model ?? '' }} langsung keluar dan harus login ulang. Data di antrian HP yang belum terkirim ikut hilang.</div>
                <div class="modal-footer flex justify-end gap-2">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Batal</x-ui.atoms.button>
                    <x-ui.atoms.button type="submit" variant="outline-danger">Cabut sesi</x-ui.atoms.button>
                </div>
            </form>
        </x-ui.molecules.modal>

        <x-ui.molecules.modal id="modal-status" data-modal role="dialog" aria-modal="true" aria-labelledby="judul-status">
            <form method="POST" action="{{ route('akun.' . $menu . '.status', $akun->id) }}" class="modal-content">
                @csrf
                <input type="hidden" name="aktif" value="{{ $akun->aktif ? 0 : 1 }}">
                <div class="modal-header"><h6 id="judul-status" class="mb-0 font-semibold">{{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $akun->username }}?</h6><button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button></div>
                <div class="modal-body">
                    @if ($akun->aktif)
                        <ul class="list-disc ps-4 mb-3">
                            <li>Tidak bisa login di web maupun aplikasi HP.</li>
                            <li>Sesi HP aktif langsung dicabut.</li>
                            <li>Data transaksi tetap ada; akun bisa diaktifkan lagi.</li>
                        </ul>
                    @else
                        <p>Akun bisa login lagi dengan password terakhirnya.</p>
                    @endif
                    <x-ui.atoms.label for="alasan">Alasan</x-ui.atoms.label>
                    <x-ui.atoms.select id="alasan" name="alasan">
                        @foreach ($akun->aktif ? $alasanNonaktif : ['Kembali bekerja', 'Salah nonaktifkan', 'Lainnya'] as $a)
                            <option>{{ $a }}</option>
                        @endforeach
                    </x-ui.atoms.select>
                </div>
                <div class="modal-footer flex justify-end gap-2">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Batal</x-ui.atoms.button>
                    <x-ui.atoms.button type="submit" :variant="$akun->aktif ? 'outline-warning' : 'success'">{{ $akun->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</x-ui.atoms.button>
                </div>
            </form>
        </x-ui.molecules.modal>
    @endif

    @if ($bisaHapus)
        <x-ui.molecules.modal id="modal-hapus" data-modal role="dialog" aria-modal="true" aria-labelledby="judul-hapus">
            <form method="POST" action="{{ route('akun.' . $menu . '.destroy', $akun->id) }}" class="modal-content">
                @csrf
                @method('DELETE')
                <div class="modal-header"><h6 id="judul-hapus" class="mb-0 font-semibold">Hapus akun {{ $akun->username }}?</h6><button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button></div>
                <div class="modal-body">
                    <p>Hanya untuk akun yang <strong>belum pernah login</strong> dan dibuat di Enesis. Akun yang sudah dipakai cukup dinonaktifkan supaya riwayatnya tetap utuh.</p>
                    @if ($alasanHapus)
                        <x-ui.molecules.alert variant="danger">{{ $alasanHapus }}</x-ui.molecules.alert>
                    @else
                    <x-ui.atoms.label for="konfirmasi">Ketik <code>{{ $akun->username }}</code> untuk konfirmasi</x-ui.atoms.label>
                    <x-ui.atoms.input id="konfirmasi" name="konfirmasi" autocomplete="off" />
                    @endif
                </div>
                <div class="modal-footer flex justify-end gap-2">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Batal</x-ui.atoms.button>
                    @unless ($alasanHapus)
                        <x-ui.atoms.button type="submit" variant="outline-danger">Hapus permanen</x-ui.atoms.button>
                    @endunless
                </div>
            </form>
        </x-ui.molecules.modal>
    @endif
@endsection
