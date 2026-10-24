@php($bisaTambah = \App\Support\Akses\Akses::bisa(auth()->user(), 'role', 'tambah'))
@extends('layouts.app')

@section('title', 'Role & Hak Akses')
@section('breadcrumb_slug', 'role')

@section('content')
    <x-ui.molecules.page-header title="Role & Hak Akses">
        <x-slot:subtitle>Role menentukan menu yang bisa dibuka, aksi yang boleh dilakukan, cakupan data, dan tempat login. Setiap akun punya satu role.</x-slot:subtitle>
            <x-slot:actions>
                @if ($bisaTambah)
                <x-ui.atoms.button tag="a" href="{{ route('role.create') }}"><x-ui.atoms.icon name="plus" class="me-1" /> Tambah role</x-ui.atoms.button>
                @endif
            </x-slot:actions>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-3">
        @foreach ($roles as $r)
            <x-ui.molecules.card>
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <h6 class="mb-0 font-semibold text-base">{{ $r->nama }}</h6>
                        <div class="text-gray-500">{{ $r->keterangan }}</div>
                    </div>
                    <x-ui.atoms.badge :variant="$r->bawaan ? 'secondary' : 'primary'">{{ $r->bawaan ? 'BAWAAN' : 'BUATAN' }}</x-ui.atoms.badge>
                </div>
                <dl class="grid grid-cols-3 gap-2 mb-2">
                    <div><dt class="text-xs text-gray-500">Cakupan data</dt><dd class="font-semibold mb-0">{{ $cakupan[$r->cakupan][0] ?? $r->cakupan }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Hak akses</dt><dd class="font-semibold mb-0">{{ $r->jumlah_menu === null ? 'Penuh' : $r->jumlah_menu . ' menu' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Akun</dt><dd class="font-semibold mb-0">{{ number_format($r->jumlah_akun, 0, ',', '.') }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-1 mb-3">
                    @if ($r->login_web) <x-ui.atoms.badge variant="light">Web</x-ui.atoms.badge> @endif
                    @if ($r->login_mobile) <x-ui.atoms.badge variant="light">Aplikasi HP</x-ui.atoms.badge> @endif
                </div>
                <div class="flex gap-2 border-t border-gray-100 pt-2">
                    <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('role.edit', $r->id) }}">Atur hak akses</x-ui.atoms.button>
                    <x-ui.atoms.button tag="a" variant="light" href="{{ route('role.anggota', $r->id) }}">Lihat anggota</x-ui.atoms.button>
                </div>
            </x-ui.molecules.card>
        @endforeach
    </div>

    <x-ui.molecules.card>
        <div class="flex flex-wrap gap-x-5 gap-y-1">
            <strong>Cakupan data:</strong>
            @foreach ($cakupan as [$label, $desc])
                <span><strong>{{ $label }}</strong> — {{ $desc }}</span>
            @endforeach
        </div>
    </x-ui.molecules.card>
@endsection
