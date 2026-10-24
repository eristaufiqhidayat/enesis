{{-- Area rute dan outlet seorang sales (aturan sama dengan rute di aplikasi HP); dipakai di detail dan edit akun. --}}
@if ($outletSales !== null)
    @php([$areaSales, $outlet, $cariOutlet] = [$outletSales['area'], $outletSales['outlet'], $outletSales['q']])
    <x-ui.molecules.card class="mb-3" body-class="overflow-x-auto" id="outlet-sales">
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h6 class="mb-0 font-semibold">Outlet di bawah sales ini <span class="text-gray-500 font-normal">· {{ number_format($outlet->total(), 0, ',', '.') }} outlet{{ $cariOutlet !== '' ? ' cocok' : '' }} di {{ $areaSales->where('rute', true)->count() }} area rute</span></h6>
                <form method="GET" action="{{ url()->current() }}#outlet-sales" class="flex gap-2" role="search">
                    <x-ui.atoms.label for="cari_outlet" class="sr-only">Cari outlet</x-ui.atoms.label>
                    <x-ui.atoms.input type="search" id="cari_outlet" name="cari_outlet" value="{{ $cariOutlet }}" placeholder="Cari nama, kode, alamat outlet…" />
                    <x-ui.atoms.button type="submit" variant="outline-secondary">Cari</x-ui.atoms.button>
                </form>
            </div>
        </x-slot:header>

        <div class="flex flex-wrap gap-2 mb-3" aria-label="Area sales">
            @forelse ($areaSales as $a)
                <x-ui.atoms.badge :variant="$a->rute ? 'primary' : 'secondary'" title="{{ $a->rute ? 'Area rute' : 'Area tidak aktif' }}">
                    {{ $a->nama ?? 'Area #'.$a->area_id }} · {{ number_format($a->jumlah_outlet, 0, ',', '.') }} outlet{{ $a->rute ? '' : ' · tidak aktif' }}
                </x-ui.atoms.badge>
            @empty
                <span class="text-gray-500">Sales ini belum punya area, jadi belum punya outlet.</span>
            @endforelse
        </div>

        @if ($areaSales->isNotEmpty())
            <table class="data-table">
                <thead><tr><th scope="col">Kode</th><th scope="col">Outlet</th><th scope="col">Area</th><th scope="col">Alamat</th><th scope="col">Verifikasi</th><th scope="col">Lokasi</th></tr></thead>
                <tbody>
                    @forelse ($outlet as $o)
                        <tr>
                            <td>{{ $o->kode ?? '-' }}</td>
                            <td class="font-semibold">{{ $o->nama ?? '-' }}</td>
                            <td>{{ $o->area ?? '-' }}</td>
                            <td>
                                {{ $o->alamat ?? '-' }}
                                @if ($o->kelurahan || $o->kecamatan) <div class="text-gray-500">{{ collect([$o->kelurahan, $o->kecamatan])->filter()->join(', ') }}</div> @endif
                            </td>
                            <td>{{ $o->verifikasi ?? '-' }}</td>
                            <td>
                                @if ($o->latitude && $o->longitude)
                                    <a href="https://www.google.com/maps?q={{ $o->latitude }},{{ $o->longitude }}" target="_blank" rel="noopener">Peta</a>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gray-500 py-3">{{ $cariOutlet !== '' ? 'Tidak ada outlet yang cocok.' : 'Belum ada outlet di area rute sales ini.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif

        <x-slot:footer>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <small class="text-gray-500">Area rute = area yang ditandai aktif pada data karyawan (atau semua area bila tidak ada yang aktif), sama seperti rute di aplikasi HP.</small>
                <x-ui.molecules.pagination :paginator="$outlet" />
            </div>
        </x-slot:footer>
    </x-ui.molecules.card>
@endif
