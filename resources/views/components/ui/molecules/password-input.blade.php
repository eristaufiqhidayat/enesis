{{-- Input password dengan tombol mata untuk menampilkan/menyembunyikan isinya. --}}
@props(['id', 'name'])

<div class="password-field">
    <x-ui.atoms.input type="password" :id="$id" :name="$name" {{ $attributes }} />
    <button type="button" class="password-toggle" data-password-toggle="{{ $id }}" aria-label="Tampilkan password">
        <x-ui.atoms.icon name="eye" data-password-icon-tampil />
        <x-ui.atoms.icon name="eye-off" class="hidden" data-password-icon-sembunyi />
    </button>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-password-toggle]').forEach((tombol) => {
                tombol.addEventListener('click', () => {
                    const input = document.getElementById(tombol.dataset.passwordToggle);
                    const tampil = input.type === 'password';
                    input.type = tampil ? 'text' : 'password';
                    tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
                    tombol.querySelector('[data-password-icon-tampil]').classList.toggle('hidden', tampil);
                    tombol.querySelector('[data-password-icon-sembunyi]').classList.toggle('hidden', !tampil);
                });
            });
        </script>
    @endpush
@endonce
