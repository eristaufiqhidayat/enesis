@php
    $bisaTambah = \App\Support\Akses\Akses::bisa(auth()->user(), $menu, 'tambah');
    $bisaUbah = \App\Support\Akses\Akses::bisa(auth()->user(), $menu, 'ubah');
    $bisaHapus = \App\Support\Akses\Akses::bisa(auth()->user(), $menu, 'hapus');
    $bisaLihatSales = \App\Support\Akses\Akses::bisa(auth()->user(), 'user_sales');
@endphp
@extends('layouts.app')

@section('title', $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$judul">
        <x-slot:subtitle>Akun login web &amp; aplikasi HP · {{ number_format($jumlah['semua'], 0, ',', '.') }} akun · {{ number_format($jumlah['aktif'], 0, ',', '.') }} aktif</x-slot:subtitle>
            <x-slot:actions>
                @if ($bisaTambah)
                <x-ui.atoms.button tag="a" href="{{ route('akun.' . $menu . '.create') }}"><x-ui.atoms.icon name="plus" class="me-1" /> Tambah akun</x-ui.atoms.button>
                @endif
            </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Saring status">
        @foreach (\App\Http\Controllers\Admin\AkunController::STATUS as $kode => $label)
            <x-ui.atoms.button tag="a" :variant="$status === $kode ? 'primary' : 'outline-secondary'"
                href="{{ route('akun.'.$menu.'.index', array_filter(['status' => $kode === 'semua' ? null : $kode, 'q' => $q ?: null, 'depo' => $depo, 'koordinator' => $koordinator])) }}"
                :aria-current="$status === $kode ? 'true' : null">
                {{ $label }} <span class="ms-1 opacity-75">{{ number_format($jumlah[$kode], 0, ',', '.') }}</span>
            </x-ui.atoms.button>
        @endforeach
    </div>

    <x-ui.molecules.card body-class="overflow-x-auto">
        <x-slot:header>
            <form method="GET" class="flex flex-wrap items-end gap-2" role="search">
                @if ($status !== 'semua') <input type="hidden" name="status" value="{{ $status }}"> @endif
                <div class="grow min-w-[14rem]">
                    <x-ui.atoms.label for="q" class="sr-only">Cari akun</x-ui.atoms.label>
                    <x-ui.atoms.input type="search" id="q" name="q" value="{{ $q }}" placeholder="Cari username, nama karyawan, depo, koordinator…" />
                </div>
                <div>
                    <x-ui.atoms.label for="depo" class="sr-only">Depo</x-ui.atoms.label>
                    <x-ui.atoms.select id="depo" name="depo">
                        <option value="">Semua depo</option>
                        @foreach ($daftarDepo as $id => $nama)
                            <option value="{{ $id }}" @selected($depo === (int) $id)>{{ $nama }}</option>
                        @endforeach
                    </x-ui.atoms.select>
                </div>
                @if ($menu === 'user_sales' && $daftarKoordinator->isNotEmpty())
                    <div>
                        <x-ui.atoms.label for="koordinator" class="sr-only">Koordinator</x-ui.atoms.label>
                        <x-ui.atoms.select id="koordinator" name="koordinator">
                            <option value="">Semua koordinator</option>
                            @foreach ($daftarKoordinator as $id => $nama)
                                <option value="{{ $id }}" @selected($koordinator === (int) $id)>{{ $nama }}</option>
                            @endforeach
                        </x-ui.atoms.select>
                    </div>
                @endif
                <x-ui.atoms.button type="submit">Cari</x-ui.atoms.button>
            </form>
        </x-slot:header>

        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Username</th>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Depo · Koordinator</th>
                    @if ($menu === 'user_koordinator') <th scope="col" class="text-end">Sales aktif</th> @endif
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">HP aktif</th>
                    <th scope="col">Login terakhir</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($akun as $a)
                    @php($ket = \App\Http\Controllers\Admin\AkunController::keterangan($a))
                    <tr>
                        <td>
                            <a href="{{ route('akun.' . $menu . '.show', $a->id) }}" class="font-semibold">{{ $a->username }}</a>
                            @if ($a->nickname) <div class="text-gray-500">{{ $a->nickname }}</div> @endif
                        </td>
                        <td>{{ $a->karyawan ?? $a->name }}</td>
                        <td>{{ $a->depo ?? '-' }} @if ($a->koordinator) <div class="text-gray-500">Koor: {{ $a->koordinator }}</div> @endif</td>
                        @if ($menu === 'user_koordinator')
                            <td class="text-end">
                                @if ($a->karyawan_id && $bisaLihatSales)
                                    <a href="{{ route('akun.user_sales.index', ['koordinator' => $a->karyawan_id]) }}" title="Lihat sales di bawah {{ $a->karyawan ?? $a->username }}">{{ number_format($a->jumlah_sales, 0, ',', '.') }}</a>
                                @else
                                    {{ $a->karyawan_id ? number_format($a->jumlah_sales, 0, ',', '.') : '—' }}
                                @endif
                            </td>
                        @endif
                        <td>{{ $a->role ?? '-' }}</td>
                        <td>
                            <x-ui.atoms.badge :variant="$a->aktif ? 'success' : 'secondary'">{{ $a->aktif ? 'Aktif' : 'Tidak aktif' }}</x-ui.atoms.badge>
                            @if ($ket) <div class="text-amber-700">{{ $ket }}</div> @endif
                        </td>
                        <td>{{ $a->hp_model ? $a->hp_model.' · v'.$a->hp_versi : '—' }}</td>
                        <td>{{ $a->login_terakhir_pada ? \Illuminate\Support\Carbon::parse($a->login_terakhir_pada)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') : '—' }}</td>
                        <td class="text-end whitespace-nowrap">
                            @if ($bisaUbah)
                                <x-ui.atoms.button tag="a" variant="light" href="{{ route('akun.' . $menu . '.edit', $a->id) }}" aria-label="Edit {{ $a->username }}" title="Edit"><x-ui.atoms.icon name="pen" /></x-ui.atoms.button>
                            @endif
                            <span class="relative inline-block">
                                <x-ui.atoms.button variant="light" data-dropdown-toggle="aksi-{{ $a->id }}" aria-label="Aksi lain untuk {{ $a->username }}" title="Aksi lain"><x-ui.atoms.icon name="ellipsis-h" /></x-ui.atoms.button>
                                <div id="aksi-{{ $a->id }}" class="dropdown-menu hidden text-start">
                                    <a class="block py-1" href="{{ route('akun.' . $menu . '.show', $a->id) }}">Lihat detail &amp; sesi HP</a>
                                    @if ($bisaUbah)
                                        <a class="block py-1" href="{{ route('akun.' . $menu . '.show', ['id' => $a->id, 'buka' => 'modal-password']) }}">Atur password awal</a>
                                        @if ($a->hp_model)
                                            <a class="block py-1" href="{{ route('akun.' . $menu . '.show', ['id' => $a->id, 'buka' => 'modal-sesi']) }}">Cabut sesi HP</a>
                                        @endif
                                        <a class="block py-1" href="{{ route('akun.' . $menu . '.show', ['id' => $a->id, 'buka' => 'modal-status']) }}">{{ $a->aktif ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</a>
                                    @endif
                                    @if ($bisaHapus)
                                        <a class="block py-1 text-red-600" href="{{ route('akun.' . $menu . '.show', ['id' => $a->id, 'buka' => 'modal-hapus']) }}">Hapus akun</a>
                                    @endif
                                </div>
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $menu === 'user_koordinator' ? 9 : 8 }}" class="text-center text-gray-500 py-4">Tidak ada akun.</td></tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <small class="text-gray-500">Menampilkan {{ $akun->firstItem() ?? 0 }}–{{ $akun->lastItem() ?? 0 }} dari {{ number_format($akun->total(), 0, ',', '.') }}</small>
                <x-ui.molecules.pagination :paginator="$akun" />
            </div>
        </x-slot:footer>
    </x-ui.molecules.card>
@endsection
