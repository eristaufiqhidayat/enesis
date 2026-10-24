@extends('layouts.app')

@section('title', ($akun ? 'Edit akun ' . $akun->username : 'Tambah akun') . ' · ' . $judul)
@section('breadcrumb_slug', $menu)

@section('content')
    <x-ui.molecules.page-header :title="$akun ? 'Edit akun ' . $akun->username : 'Tambah akun'">
        <x-slot:subtitle>{{ $judul }}{{ $akun ? '' : ' · password awal ditampilkan sekali setelah disimpan' }}</x-slot:subtitle>
    </x-ui.molecules.page-header>

    <x-ui.molecules.flash />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <form method="POST" action="{{ $akun ? route('akun.' . $menu . '.update', $akun->id) : route('akun.' . $menu . '.store') }}" class="lg:col-span-2" autocomplete="off">
            @csrf
            @if ($akun) @method('PUT') @endif

            <x-ui.molecules.card>
                <fieldset class="mb-3">
                    <legend class="form-label">Kategori akun</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($pilihanKategori as $kode => $label)
                            <label class="form-check">
                                <x-ui.atoms.radio name="kategori" value="{{ $kode }}" :checked="old('kategori', $kategoriAwal) === $kode" />
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mb-3">
                    <x-ui.atoms.label for="cari_karyawan">Karyawan <span class="text-gray-500">(wajib untuk sales &amp; koordinator)</span></x-ui.atoms.label>
                    <input type="hidden" name="karyawan_id" id="karyawan_id" value="{{ old('karyawan_id', $akun->karyawan_id ?? '') }}">
                    <x-ui.atoms.input type="search" id="cari_karyawan" placeholder="Ketik nama atau ID karyawan…"
                        value="{{ $karyawan ? $karyawan->nama : '' }}" aria-describedby="karyawan_info" aria-controls="hasil_karyawan" />
                    <div id="karyawan_info" class="text-xs text-gray-500 mt-1">
                        @if ($karyawan)
                            Terpilih: <strong>{{ $karyawan->nama }}</strong> · ID {{ $karyawan->id }} · Depo {{ $karyawan->depo ?? '-' }} · Koor {{ $karyawan->koordinator ?? '-' }}
                        @else
                            Hanya karyawan aktif. Satu karyawan hanya boleh punya satu akun aktif.
                        @endif
                    </div>
                    <div id="hasil_karyawan" class="list-group mt-1 hidden" role="listbox" aria-label="Hasil cari karyawan"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <x-ui.atoms.label for="username">Username</x-ui.atoms.label>
                        <x-ui.atoms.input id="username" name="username" required maxlength="60" value="{{ old('username', $akun->username ?? '') }}" />
                        <div class="text-xs text-gray-500 mt-1">Huruf besar/kecil dianggap sama; tanpa spasi.</div>
                    </div>
                    <div>
                        <x-ui.atoms.label for="name">Nama tampilan</x-ui.atoms.label>
                        <x-ui.atoms.input id="name" name="name" maxlength="120" value="{{ old('name', $akun->name ?? '') }}" placeholder="Bawaan: nama karyawan" />
                    </div>
                </div>

                <div class="mb-3">
                    <x-ui.atoms.label for="role_id">Role</x-ui.atoms.label>
                    <x-ui.atoms.select id="role_id" name="role_id">
                        <option value="">Bawaan sesuai kategori</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->id }}" @selected((string) old('role_id', $akun->role_id ?? '') === (string) $r->id)>{{ $r->nama }}</option>
                        @endforeach
                    </x-ui.atoms.select>
                    <div class="text-xs text-gray-500 mt-1">Role menentukan menu, hak ubah, cakupan data, dan boleh login di web / aplikasi HP.</div>
                </div>

                @unless ($akun)
                    <fieldset class="mb-3 rounded-md border border-gray-200 p-3">
                        <legend class="form-label px-1">Password awal</legend>
                        <label class="form-check mb-2">
                            <x-ui.atoms.radio name="password_mode" value="otomatis" :checked="old('password_mode', 'otomatis') === 'otomatis'" />
                            <span><strong>Buat otomatis</strong> (10 karakter acak) — tampil sekali, wajib diganti saat login pertama.</span>
                        </label>
                        <label class="form-check mb-2">
                            <x-ui.atoms.radio name="password_mode" value="manual" :checked="old('password_mode') === 'manual'" />
                            <span><strong>Tentukan sendiri</strong> (minimal 8 karakter, tidak sama dengan username)</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                            <div>
                                <x-ui.atoms.label for="password">Password</x-ui.atoms.label>
                                <x-ui.atoms.input type="password" id="password" name="password" autocomplete="new-password" />
                            </div>
                            <div>
                                <x-ui.atoms.label for="password_confirmation">Ulangi password</x-ui.atoms.label>
                                <x-ui.atoms.input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
                            </div>
                        </div>
                        <label class="form-check mt-2">
                            <x-ui.atoms.checkbox name="wajib_ganti_password" value="1" :checked="old('wajib_ganti_password', '1') === '1'" />
                            Wajib ganti password saat login pertama
                        </label>
                    </fieldset>
                @endunless

                <div class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 p-3 mb-3">
                    <div>
                        <strong>Akun aktif</strong>
                        <div class="text-xs text-gray-500">Akun nonaktif tidak bisa login dan sesi HP-nya dicabut.</div>
                    </div>
                    <label class="form-check">
                        <input type="hidden" name="aktif" value="0">
                        <x-ui.atoms.checkbox name="aktif" value="1" role="switch" :checked="(bool) old('aktif', $akun->aktif ?? true)" />
                        Aktif
                    </label>
                </div>

                <x-slot:footer>
                    <div class="flex justify-end gap-2">
                        <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ $akun ? route('akun.' . $menu . '.show', $akun->id) : route('akun.' . $menu . '.index') }}">Batal</x-ui.atoms.button>
                        <x-ui.atoms.button type="submit">Simpan akun</x-ui.atoms.button>
                    </div>
                </x-slot:footer>
            </x-ui.molecules.card>
        </form>

        <div class="flex flex-col gap-3">
            <x-ui.molecules.card>
                <x-slot:header><h6 class="mb-0 font-semibold">Aturan akun</h6></x-slot:header>
                <ul class="list-disc ps-4 text-gray-600 flex flex-col gap-1">
                    <li>Username unik, huruf besar/kecil dianggap sama.</li>
                    <li>Sales &amp; koordinator wajib terhubung ke karyawan aktif; depo, area rute, dan koordinator ikut data karyawan.</li>
                    <li>Satu karyawan hanya boleh punya satu akun aktif.</li>
                    <li>Password tidak pernah ditampilkan lagi setelah halaman password awal ditutup.</li>
                </ul>
            </x-ui.molecules.card>
            @if ($akun && $akun->sumber_jenis)
                <x-ui.molecules.alert variant="info">
                    Akun ini dari data aplikasi lama. Menjalankan ulang <code>enesis:sinkron-user</code> menimpa username, status, dan karyawannya.
                </x-ui.molecules.alert>
            @endif
        </div>
    </div>

    <div class="mt-3">
        @include('akun._sales-bawahan')
    </div>
@endsection

@push('scripts')
    <script>
        // Cari karyawan (JSON) lalu isi karyawan_id tersembunyi.
        (() => {
            const input = document.getElementById('cari_karyawan');
            const hidden = document.getElementById('karyawan_id');
            const info = document.getElementById('karyawan_info');
            const hasil = document.getElementById('hasil_karyawan');
            const url = @json(route('akun.' . $menu . '.karyawan'));
            const kecuali = @json($akun->id ?? 0);
            let timer;

            const pilih = (k) => {
                hidden.value = k.id;
                input.value = k.nama;
                info.textContent = `Terpilih: ${k.nama} · ID ${k.id} · Depo ${k.depo ?? '-'} · Koor ${k.koordinator ?? '-'}`;
                hasil.classList.add('hidden');
                const nama = document.getElementById('name');
                if (!nama.value) nama.value = k.nama;
            };

            input.addEventListener('input', () => {
                clearTimeout(timer);
                if (!input.value.trim()) hidden.value = '';
                timer = setTimeout(async () => {
                    const q = input.value.trim();
                    if (q.length < 2) return hasil.classList.add('hidden');
                    const res = await fetch(`${url}?q=${encodeURIComponent(q)}&kecuali=${kecuali}`, { headers: { Accept: 'application/json' } });
                    const data = res.ok ? await res.json() : [];
                    hasil.replaceChildren(...data.map((k) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action text-start';
                        b.setAttribute('role', 'option');
                        b.textContent = `${k.nama} · ID ${k.id} · ${k.kategori} · Depo ${k.depo ?? '-'}` + (k.akun_aktif ? ` — sudah punya akun aktif: ${k.akun_aktif}` : '');
                        b.addEventListener('click', () => pilih(k));
                        return b;
                    }));
                    if (!data.length) {
                        const kosong = document.createElement('div');
                        kosong.className = 'list-group-item text-gray-500';
                        kosong.textContent = 'Tidak ada karyawan aktif yang cocok.';
                        hasil.replaceChildren(kosong);
                    }
                    hasil.classList.remove('hidden');
                }, 250);
            });
        })();
    </script>
@endpush
