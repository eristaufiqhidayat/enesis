{{-- Daftar akun sales di bawah satu koordinator + tambah/lepas sales; dipakai di detail dan edit akun. --}}
@if ($salesBawahan !== null)
    @php
        $waktuSales = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') : '—';
        $bisaLihatSales = \App\Support\Akses\Akses::bisa(auth()->user(), 'user_sales');
        $bisaAturSales = \App\Support\Akses\Akses::bisa(auth()->user(), 'user_koordinator', 'ubah');
    @endphp
    <x-ui.molecules.card class="mb-3" body-class="overflow-x-auto">
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h6 class="mb-0 font-semibold">Sales di bawah koordinator ini <span class="text-gray-500 font-normal">· {{ $salesBawahan->where('aktif', true)->count() }} aktif dari {{ $salesBawahan->count() }} akun</span></h6>
                <div class="flex flex-wrap gap-2">
                    @if ($bisaLihatSales && $salesBawahan->isNotEmpty())
                        <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('akun.user_sales.index', ['koordinator' => $akun->karyawan_id]) }}">Buka di User Sales</x-ui.atoms.button>
                    @endif
                    @if ($bisaAturSales)
                        <x-ui.atoms.button data-modal-open="modal-tambah-sales"><x-ui.atoms.icon name="plus" class="me-1" /> Tambah sales</x-ui.atoms.button>
                    @endif
                </div>
            </div>
        </x-slot:header>
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Username</th><th scope="col">Karyawan</th><th scope="col">Depo</th><th scope="col">Status</th><th scope="col">HP aktif</th><th scope="col">Login terakhir</th>
                    @if ($bisaAturSales) <th scope="col" class="text-end">Aksi</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($salesBawahan as $s)
                    <tr>
                        <td>
                            @if ($bisaLihatSales)
                                <a href="{{ route('akun.user_sales.show', $s->id) }}" class="font-semibold">{{ $s->username }}</a>
                            @else
                                {{ $s->username }}
                            @endif
                        </td>
                        <td>{{ $s->karyawan ?? $s->name }}</td>
                        <td>{{ $s->depo ?? '-' }}</td>
                        <td><x-ui.atoms.badge :variant="$s->aktif ? 'success' : 'secondary'">{{ $s->aktif ? 'Aktif' : 'Tidak aktif' }}</x-ui.atoms.badge></td>
                        <td>{{ $s->hp_model ? $s->hp_model.' · v'.$s->hp_versi : '—' }}</td>
                        <td>{{ $waktuSales($s->login_terakhir_pada) }}</td>
                        @if ($bisaAturSales)
                            <td class="text-end">
                                <form method="POST" action="{{ route('akun.user_koordinator.sales.lepas', ['id' => $akun->id, 'sales' => $s->id]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.atoms.button type="submit" variant="light" title="Lepas {{ $s->username }} dari koordinator ini">Lepas</x-ui.atoms.button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bisaAturSales ? 7 : 6 }}" class="text-center text-gray-500 py-3">Belum ada akun sales di bawah koordinator ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($salesBawahan->count() >= 100)
            <p class="text-xs text-gray-500 mt-2 mb-0">Menampilkan 100 akun pertama; daftar lengkap ada di User Sales.</p>
        @endif
        <p class="text-xs text-gray-500 mt-2 mb-0">Diatur dari Enesis dan tidak tertimpa saat data karyawan ditarik ulang dari aplikasi lama.</p>
    </x-ui.molecules.card>

    @if ($bisaAturSales)
        <x-ui.molecules.modal id="modal-tambah-sales" data-modal role="dialog" aria-modal="true" aria-labelledby="judul-tambah-sales">
            <form method="POST" action="{{ route('akun.user_koordinator.sales.tambah', $akun->id) }}" class="modal-content" id="form-tambah-sales">
                @csrf
                <input type="hidden" name="sales_id" id="tambah_sales_id">
                <div class="modal-header"><h6 id="judul-tambah-sales" class="mb-0 font-semibold">Tambah sales ke {{ $akun->karyawan ?? $akun->username }}</h6><button type="button" class="btn-close" data-modal-dismiss aria-label="Tutup">&times;</button></div>
                <div class="modal-body">
                    <x-ui.atoms.label for="cari_sales">Cari akun sales</x-ui.atoms.label>
                    <x-ui.atoms.input type="search" id="cari_sales" placeholder="Ketik username atau nama sales…" aria-controls="hasil_sales" autocomplete="off" />
                    <div class="text-xs text-gray-500 mt-1">Sales yang sudah punya koordinator lain akan dipindah ke koordinator ini.</div>
                    <div id="hasil_sales" class="list-group mt-2" role="listbox" aria-label="Hasil cari sales"></div>
                </div>
                <div class="modal-footer flex justify-end gap-2">
                    <x-ui.atoms.button variant="light" data-modal-dismiss>Tutup</x-ui.atoms.button>
                </div>
            </form>
        </x-ui.molecules.modal>

        @push('scripts')
            <script>
                // Cari akun sales (JSON); klik hasil = kirim form tambah sales.
                (() => {
                    const input = document.getElementById('cari_sales');
                    const hasil = document.getElementById('hasil_sales');
                    const form = document.getElementById('form-tambah-sales');
                    const url = @json(route('akun.user_koordinator.sales.cari', $akun->id));
                    let timer;

                    const baris = (teks, kelas = 'list-group-item text-gray-500') => {
                        const el = document.createElement('div');
                        el.className = kelas;
                        el.textContent = teks;
                        return el;
                    };

                    input.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(async () => {
                            const q = input.value.trim();
                            if (q.length < 2) return hasil.replaceChildren();
                            const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                            const data = res.ok ? await res.json() : [];
                            if (!data.length) return hasil.replaceChildren(baris('Tidak ada akun sales yang cocok.'));
                            hasil.replaceChildren(...data.map((s) => {
                                const info = `${s.username} · ${s.karyawan ?? '-'} · Depo ${s.depo ?? '-'}` + (s.aktif ? '' : ' · tidak aktif');
                                if (s.sudah) return baris(`${info} — sudah di bawah koordinator ini`);
                                const b = document.createElement('button');
                                b.type = 'button';
                                b.className = 'list-group-item list-group-item-action text-start';
                                b.setAttribute('role', 'option');
                                b.textContent = info + (s.koordinator ? ` — pindah dari ${s.koordinator}` : ' — belum punya koordinator');
                                b.addEventListener('click', () => {
                                    form.querySelector('#tambah_sales_id').value = s.id;
                                    form.submit();
                                });
                                return b;
                            }));
                        }, 250);
                    });
                })();
            </script>
        @endpush
    @endif
@endif
