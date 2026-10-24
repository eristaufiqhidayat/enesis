@php
    $bisaUbah = \App\Support\Akses\Akses::bisa(auth()->user(), 'role', 'ubah');
    $waktu = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') : '—';
@endphp
@extends('layouts.app')

@section('title', 'Anggota role ' . $role->nama)
@section('breadcrumb_slug', 'role')

@section('content')
    <x-ui.molecules.page-header :title="'Anggota role ' . $role->nama">
        <x-slot:subtitle>{{ number_format($anggota->total(), 0, ',', '.') }} akun · cakupan data: {{ \App\Support\Akses\Akses::CAKUPAN[$role->cakupan][0] ?? $role->cakupan }}</x-slot:subtitle>
        <x-slot:actions>
            <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('role.edit', $role->id) }}">Atur hak akses</x-ui.atoms.button>
        </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">
        <form method="POST" action="{{ route('role.pindah', $role->id) }}" class="xl:col-span-2">
            @csrf
            <x-ui.molecules.card body-class="overflow-x-auto">
                <x-slot:header>
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div class="w-64">
                            <x-ui.atoms.label for="q" class="sr-only">Cari anggota</x-ui.atoms.label>
                            <x-ui.atoms.input type="search" id="q" name="q" form="form_cari" value="{{ $q }}" placeholder="Cari username / karyawan…" />
                        </div>
                        @if ($bisaUbah && $roleLain->isNotEmpty())
                            <div class="flex flex-wrap items-end gap-2">
                                <div>
                                    <x-ui.atoms.label for="ke">Pindahkan yang dipilih ke</x-ui.atoms.label>
                                    <x-ui.atoms.select id="ke" name="ke">
                                        @foreach ($roleLain as $id => $nama)
                                            <option value="{{ $id }}">{{ $nama }}</option>
                                        @endforeach
                                    </x-ui.atoms.select>
                                </div>
                                <x-ui.atoms.button type="submit">Pindahkan</x-ui.atoms.button>
                            </div>
                        @endif
                    </div>
                </x-slot:header>

                <table class="data-table">
                    <thead>
                        <tr>
                            @if ($bisaUbah) <th scope="col" class="w-8"><span class="sr-only">Pilih</span></th> @endif
                            <th scope="col">Username</th>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Depo</th>
                            <th scope="col">Status</th>
                            <th scope="col">Login terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($anggota as $a)
                            <tr>
                                @if ($bisaUbah)
                                    <td><x-ui.atoms.checkbox name="user_id[]" value="{{ $a->id }}" aria-label="Pilih {{ $a->username }}" /></td>
                                @endif
                                <td>
                                    @if (isset($menuAkun[$a->kategori]))
                                        <a href="{{ route('akun.' . $menuAkun[$a->kategori] . '.show', $a->id) }}" class="font-semibold">{{ $a->username }}</a>
                                    @else
                                        <span class="font-semibold">{{ $a->username }}</span>
                                    @endif
                                </td>
                                <td>{{ $a->karyawan ?? '-' }}</td>
                                <td>{{ $a->depo ?? '-' }}</td>
                                <td><x-ui.atoms.badge :variant="$a->aktif ? 'success' : 'secondary'">{{ $a->aktif ? 'Aktif' : 'Tidak aktif' }}</x-ui.atoms.badge></td>
                                <td>{{ $waktu($a->login_terakhir_pada) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-gray-500 py-4">Belum ada akun dengan role ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <x-slot:footer>
                    <div class="flex justify-end"><x-ui.molecules.pagination :paginator="$anggota" /></div>
                </x-slot:footer>
            </x-ui.molecules.card>
        </form>
        <form id="form_cari" method="GET" class="hidden"></form>

        <x-ui.molecules.card>
            <x-slot:header><h6 class="mb-0 font-semibold">Riwayat perubahan role</h6></x-slot:header>
            <ol class="list-none m-0 p-0 flex flex-col gap-3">
                @forelse ($riwayat as $r)
                    <li class="flex gap-2">
                        <span aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-600"></span>
                        <span>
                            <span class="block">{{ $r->keterangan ?? $r->aksi }}</span>
                            <span class="text-xs text-gray-500">{{ $r->aktor_nama ?? 'sistem' }} · {{ $waktu($r->created_at) }}</span>
                        </span>
                    </li>
                @empty
                    <li class="text-gray-500">Belum ada perubahan.</li>
                @endforelse
            </ol>
        </x-ui.molecules.card>
    </div>
@endsection
