@extends('layouts.app')

@section('title', 'Password awal ' . $akun->username)
@section('breadcrumb_slug', $menu)

@section('content')
    {{-- Halaman ini hanya hasil langsung dari POST (tidak disimpan di session), jadi password tampil sekali. --}}
    <div class="max-w-md mx-auto mt-4">
        <x-ui.molecules.card>
            <x-slot:header><h6 class="mb-0 font-semibold">Password awal untuk {{ $akun->username }}</h6></x-slot:header>
            <p>Berikan ke pengguna secara langsung. Password ini <strong>hanya tampil sekali</strong>; saat login pertama (web atau HP) pengguna wajib menggantinya.</p>
            <div class="flex items-center gap-2 rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 mb-3">
                <code id="password_awal" class="grow text-xl font-bold tracking-widest">{{ $password }}</code>
                <x-ui.atoms.button variant="outline-secondary" id="salin_password">Salin</x-ui.atoms.button>
            </div>
            <label class="form-check mb-3">
                <x-ui.atoms.checkbox id="sudah_dicatat" />
                Sudah saya catat / berikan ke pengguna
            </label>
            <x-slot:footer>
                <div class="flex justify-end">
                    <x-ui.atoms.button tag="a" id="tutup_password" href="{{ route('akun.' . $menu . '.show', $akun->id) }}" class="opacity-50 pointer-events-none" aria-disabled="true">Tutup</x-ui.atoms.button>
                </div>
            </x-slot:footer>
        </x-ui.molecules.card>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tutup = document.getElementById('tutup_password');
            document.getElementById('sudah_dicatat').addEventListener('change', (e) => {
                tutup.classList.toggle('opacity-50', !e.target.checked);
                tutup.classList.toggle('pointer-events-none', !e.target.checked);
                tutup.setAttribute('aria-disabled', e.target.checked ? 'false' : 'true');
            });
            document.getElementById('salin_password').addEventListener('click', async (e) => {
                try {
                    await navigator.clipboard.writeText(document.getElementById('password_awal').textContent.trim());
                    e.target.textContent = 'Tersalin';
                } catch {
                    e.target.textContent = 'Salin manual';
                }
            });
        })();
    </script>
@endpush
