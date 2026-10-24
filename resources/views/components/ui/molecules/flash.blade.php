@props([])

{{-- Pesan sukses (session "sukses") dan kesalahan validasi di atas konten halaman. --}}
@if (session('sukses'))
    <x-ui.molecules.alert variant="success" class="mb-3" role="status">{{ session('sukses') }}</x-ui.molecules.alert>
@endif
@if ($errors->any())
    <x-ui.molecules.alert variant="danger" class="mb-3" role="alert">
        @foreach ($errors->all() as $pesan)
            <div>{{ $pesan }}</div>
        @endforeach
    </x-ui.molecules.alert>
@endif
