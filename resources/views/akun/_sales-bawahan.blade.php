{{-- Daftar akun sales di bawah satu koordinator; dipakai di detail dan edit akun. --}}
@if ($salesBawahan !== null)
    @php($waktuSales = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') : '—')
    @php($bisaLihatSales = \App\Support\Akses\Akses::bisa(auth()->user(), 'user_sales'))
    <x-ui.molecules.card class="mb-3" body-class="overflow-x-auto">
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h6 class="mb-0 font-semibold">Sales di bawah koordinator ini <span class="text-gray-500 font-normal">· {{ $salesBawahan->where('aktif', true)->count() }} aktif dari {{ $salesBawahan->count() }} akun</span></h6>
                @if ($bisaLihatSales && $salesBawahan->isNotEmpty())
                    <x-ui.atoms.button tag="a" variant="outline-secondary" href="{{ route('akun.user_sales.index', ['koordinator' => $akun->karyawan_id]) }}">Buka di User Sales</x-ui.atoms.button>
                @endif
            </div>
        </x-slot:header>
        <table class="data-table">
            <thead><tr><th scope="col">Username</th><th scope="col">Karyawan</th><th scope="col">Depo</th><th scope="col">Status</th><th scope="col">HP aktif</th><th scope="col">Login terakhir</th></tr></thead>
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
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-gray-500 py-3">Belum ada akun sales yang karyawannya berada di bawah koordinator ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($salesBawahan->count() >= 100)
            <p class="text-xs text-gray-500 mt-2 mb-0">Menampilkan 100 akun pertama; daftar lengkap ada di User Sales.</p>
        @endif
    </x-ui.molecules.card>
@endif
