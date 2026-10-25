{{-- Ikon kanan atas, sama seperti header Marketama. --}}
@props(['breadcrumbSlug' => null])

<div class="topbar flex justify-between items-center gap-2">
    <x-ui.molecules.breadcrumb :slug="$breadcrumbSlug" />
    <div class="flex items-center gap-2">
        <x-ui.atoms.tooltip label="Tambah Loading Barang" align="end"><a class="shortcut-btn" href="{{ url('loading_barang/add') }}"><x-ui.atoms.icon name="truck" /></a></x-ui.atoms.tooltip>
        <x-ui.atoms.tooltip label="Tambah Barang Canvassing" align="end"><a class="shortcut-btn" href="{{ url('barang_canvassing/add') }}"><x-ui.atoms.icon name="boxes" /></a></x-ui.atoms.tooltip>
        <x-ui.atoms.tooltip label="Design Polygon Area" align="end"><a class="shortcut-btn" href="{{ route('design-area.index') }}"><x-ui.atoms.icon name="shapes" /></a></x-ui.atoms.tooltip>
        <div class="relative">
            <x-ui.atoms.tooltip label="Notifikasi Transaksi" align="end"><a class="notif-bell" href="#" data-dropdown-toggle="notif_menu" aria-label="Notifikasi Transaksi"><x-ui.atoms.icon name="bell" /></a></x-ui.atoms.tooltip>
            <div class="dropdown-menu hidden" id="notif_menu">
                <x-ui.atoms.icon name="bell-off" class="me-1" /> Tidak ada notifikasi baru
            </div>
        </div>
        @auth
            <div class="topbar-user">
                <x-ui.atoms.icon name="circle-user" />
                <span class="topbar-user-name" title="{{ auth()->user()->username }}">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.atoms.button type="submit" variant="outline-secondary">Keluar</x-ui.atoms.button>
                </form>
            </div>
        @endauth
    </div>
</div>
