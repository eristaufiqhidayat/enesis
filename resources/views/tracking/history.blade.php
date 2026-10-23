@extends('layouts.app')

@section('title', $page['title'])

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <style>
        #tracking_map { height: calc(100vh - 160px); min-height: 480px; border-radius: .375rem; }
        .person-item { cursor: pointer; }
        .person-item.no-data { opacity: .55; }
        .person-item.active { background: #e7f1ff; color: #212529; border-color: #dee2e6; border-left: 3px solid #0d6efd; }
        .layer-toggle .btn { font-size: 12px; font-weight: 600; }
        .tracking-name-tooltip { font-weight: 600; }
    </style>
@endpush

@section('content')
    <h5 class="mb-3 font-semibold">{{ $page['title'] }}</h5>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
        <div class="lg:col-span-4">
            <x-ui.molecules.card>
                <x-slot:header><h6 class="mb-0 font-semibold">Filter Tracking</h6></x-slot:header>

                <x-ui.atoms.label for="tracking_date">Tanggal</x-ui.atoms.label>
                <x-ui.atoms.input type="date" id="tracking_date" class="mb-3" value="{{ $tanggal }}" />

                @if (! empty($groups))
                    <x-ui.atoms.label for="group_filter">Koordinator</x-ui.atoms.label>
                    <x-ui.atoms.select id="group_filter" class="mb-3">
                        <option value="">Semua koordinator</option>
                        @foreach ($groups as $groupId => $groupName)
                            <option value="{{ $groupId }}">{{ $groupName }}</option>
                        @endforeach
                    </x-ui.atoms.select>
                @endif

                <x-ui.atoms.label for="person_search">{{ $label }}</x-ui.atoms.label>
                <x-ui.atoms.input type="search" id="person_search" class="mb-2" placeholder="Cari {{ strtolower($label) }}..." />
                <div class="text-xs text-gray-500 mb-2" id="person_summary"></div>
                <div class="list-group max-h-[50vh] overflow-y-auto" id="person_list">
                    @forelse ($people as $k)
                        <a class="list-group-item list-group-item-action person-item py-2" data-id="{{ $k->id }}" data-name="{{ $k->nama }}" data-group="{{ $k->group_id ?? '' }}">
                            <div class="flex justify-between items-start gap-2">
                                <span class="font-semibold">{{ $k->nama }}</span>
                                <x-ui.atoms.badge variant="light" class="point-count" />
                            </div>
                            @if (! empty($k->koordinator_nama))
                                <small class="text-gray-500 block"><i class="fas fa-user-tie me-1"></i>{{ $k->koordinator_nama }}</small>
                            @endif
                            <small class="text-gray-500"><i class="fas fa-warehouse me-1"></i>{{ $k->depo ?: '-' }}
                                @if ($k->telepon)<span class="ml-2"><i class="fas fa-phone me-1"></i>{{ $k->telepon }}</span>@endif
                            </small>
                        </a>
                    @empty
                        <div class="text-gray-500 text-xs p-3">Belum ada data. Jalankan <code>php artisan marketama:import</code>.</div>
                    @endforelse
                </div>
            </x-ui.molecules.card>
        </div>

        <div class="lg:col-span-8">
            <x-ui.molecules.card body-class="card-body p-2">
                <x-slot:header>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span><b>{{ $label }}:</b> <span id="person_name_display">-</span></span>
                        <span><b>Tanggal:</b> <span id="date_display">-</span></span>
                        <span><b>Info:</b> <span id="location_info">Pilih {{ strtolower($label) }}</span></span>
                    </div>
                </x-slot:header>
                <div id="tracking_map"></div>
            </x-ui.molecules.card>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        const locationsUrl = @json($locationsUrl);
        const countsUrl = @json($countsUrl);
        const idParam = @json($idParam);
        const label = @json(strtolower($label));
        const initialCounts = @json((object) $counts);
        const map = L.map('tracking_map', { center: [-6.177031, 106.839651], zoom: 11 });

        const osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        });
        const satelliteLayer = L.layerGroup([
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 19, attribution: 'Tiles &copy; Esri' }),
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 19 }),
        ]);
        const roadsLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 19 });
        satelliteLayer.addTo(map);

        // Tombol Satelit / 2D Map / Garis Jalan seperti di Marketama.
        const LayerToggle = L.Control.extend({
            options: { position: 'topright' },
            onAdd() {
                const el = L.DomUtil.create('div', 'layer-toggle flex flex-col items-end gap-1');
                el.innerHTML = '<div class="flex gap-1 shadow-sm rounded-md overflow-hidden"><button type="button" class="btn btn-sm btn-primary" data-layer="sat"><i class="fas fa-satellite me-1"></i>Satelit</button>'
                    + '<button type="button" class="btn btn-sm btn-light" data-layer="2d"><i class="fas fa-map me-1"></i>2D Map</button></div>'
                    + '<button type="button" class="btn btn-sm btn-light shadow-sm" data-layer="roads"><i class="fas fa-road me-1"></i>Garis Jalan</button>';
                L.DomEvent.disableClickPropagation(el);
                el.addEventListener('click', (e) => {
                    const btn = e.target.closest('button');
                    if (!btn) return;
                    const layer = btn.dataset.layer;
                    if (layer === 'roads') {
                        map.hasLayer(roadsLayer) ? map.removeLayer(roadsLayer) : roadsLayer.addTo(map);
                        btn.classList.toggle('btn-primary', map.hasLayer(roadsLayer));
                        btn.classList.toggle('btn-light', !map.hasLayer(roadsLayer));
                        return;
                    }
                    const sat = layer === 'sat';
                    map.removeLayer(sat ? osmLayer : satelliteLayer);
                    (sat ? satelliteLayer : osmLayer).addTo(map);
                    if (map.hasLayer(roadsLayer)) roadsLayer.bringToFront();
                    el.querySelectorAll('[data-layer="sat"],[data-layer="2d"]').forEach((b) => {
                        b.classList.toggle('btn-primary', b === btn);
                        b.classList.toggle('btn-light', b !== btn);
                    });
                });
                return el;
            },
        });
        map.addControl(new LayerToggle());

        function iconSvg(color, inner) {
            return L.divIcon({
                className: '',
                iconSize: [40, 40],
                iconAnchor: [20, 20],
                html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="40" height="40"><circle cx="32" cy="32" r="30" fill="' + color + '" opacity="0.9"/>' + inner + '</svg>',
            });
        }
        const moto = '<circle cx="16" cy="38" r="7" fill="none" stroke="#fff" stroke-width="3"/><circle cx="48" cy="38" r="7" fill="none" stroke="#fff" stroke-width="3"/>'
            + '<line x1="16" y1="38" x2="32" y2="28" stroke="#fff" stroke-width="2.5"/><line x1="32" y1="28" x2="48" y2="38" stroke="#fff" stroke-width="2.5"/><circle cx="38" cy="22" r="5" fill="#fff"/>';
        const house = '<rect x="18" y="32" width="28" height="18" fill="#fff"/><polygon points="12,33 32,14 52,33" fill="#fff"/>';

        const gmaps = (lat, lng) => '<a class="btn btn-sm btn-primary mt-2" style="font-size:11px" target="_blank" rel="noopener" href="https://maps.google.com/maps?q=loc:' + lat + ',' + lng + '&t=k"><i class="fas fa-map-marked-alt me-1"></i>Lihat di Google Map</a>';
        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function reverseGeocode(lat, lng) {
            return fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
                .then((r) => r.json())
                .then((d) => d.display_name || '-')
                .catch(() => '-');
        }

        let routeLayer = L.layerGroup().addTo(map);

        function pointPopup(label, loc, address) {
            return '<div style="min-width:200px"><strong>' + label + '</strong><br>'
                + '<small>Waktu: ' + escapeHtml(loc.waktu) + '</small><br>'
                + '<small class="text-gray-500">&#x1F4CD; ' + (address ? escapeHtml(address) : '<i>Memuat alamat...</i>') + '</small><br>'
                + '<small class="text-gray-500">Lat: ' + loc.lat + ' | Lng: ' + loc.lng + '</small>'
                + (loc.akurasi ? '<br><small class="text-gray-500">Akurasi: ' + loc.akurasi + 'm</small>' : '')
                + '<br>' + gmaps(loc.lat, loc.lng) + '</div>';
        }

        function draw(data, name) {
            routeLayer.clearLayers();
            const locs = data.locations;
            if (!locs.length) return;
            const path = locs.map((l) => [parseFloat(l.lat), parseFloat(l.lng)]);
            L.polyline(path, { color: '#0d6efd', opacity: 0.8, weight: 4 }).addTo(routeLayer);

            locs.forEach((loc, i) => {
                const first = i === 0;
                const last = i === locs.length - 1;
                const ll = [parseFloat(loc.lat), parseFloat(loc.lng)];
                const marker = first || last
                    ? L.marker(ll, { icon: iconSvg(first ? '#198754' : '#dc3545', moto), zIndexOffset: 100 })
                    : L.circleMarker(ll, { radius: 5, fillColor: '#0d6efd', color: '#fff', weight: 1, fillOpacity: 0.8 });
                const label = first ? '&#x1F7E2; Start' : (last ? '&#x1F534; End' : '&#x1F535; Titik ' + (i + 1));
                if (first || last) {
                    marker.bindTooltip(escapeHtml(name) + ' &middot; ' + (first ? 'Start' : 'End'), { permanent: true, direction: 'top', offset: [0, -20], className: 'tracking-name-tooltip' });
                }
                marker.bindPopup(pointPopup(label, loc));
                let loaded = false;
                marker.on('popupopen', () => {
                    if (loaded) return;
                    loaded = true;
                    reverseGeocode(loc.lat, loc.lng).then((addr) => marker.setPopupContent(pointPopup(label, loc, addr)));
                });
                marker.addTo(routeLayer);
            });

            if (data.depo && parseFloat(data.depo.latitude) && parseFloat(data.depo.longitude)) {
                const d = [parseFloat(data.depo.latitude), parseFloat(data.depo.longitude)];
                L.marker(d, { icon: iconSvg('#6f42c1', house), zIndexOffset: 50 })
                    .bindPopup('<div class="text-center"><h6 class="mb-0">' + escapeHtml(data.depo.nama) + '</h6><small class="text-gray-500">Depo</small><br>' + gmaps(d[0], d[1]) + '</div>')
                    .addTo(routeLayer);
            }

            map.fitBounds(L.latLngBounds(path), { padding: [30, 30] });
        }

        function load() {
            const active = document.querySelector('.person-item.active');
            const tanggal = document.getElementById('tracking_date').value;
            if (!active || !tanggal) return;
            const name = active.dataset.name;
            document.getElementById('person_name_display').textContent = name;
            document.getElementById('date_display').textContent = tanggal;
            document.getElementById('location_info').textContent = 'Memuat...';

            fetch(locationsUrl + '?' + new URLSearchParams({ [idParam]: active.dataset.id, tanggal }))
                .then((r) => r.json())
                .then((data) => {
                    document.getElementById('location_info').textContent = data.total ? data.total + ' titik lokasi' : 'Tidak ada data lokasi';
                    if (data.total) draw(data, name); else routeLayer.clearLayers();
                })
                .catch(() => { document.getElementById('location_info').textContent = 'Gagal memuat data'; });
        }

        document.querySelectorAll('.person-item').forEach((el) => el.addEventListener('click', () => {
            document.querySelectorAll('.person-item').forEach((x) => x.classList.remove('active'));
            el.classList.add('active');
            load();
        }));
        const groupFilter = document.getElementById('group_filter');
        const isShown = (el) => !el.classList.contains('hidden');

        // Sembunyikan item yang tidak cocok dengan pencarian nama atau filter koordinator.
        function applyFilter() {
            const q = document.getElementById('person_search').value.toLowerCase();
            const group = groupFilter ? groupFilter.value : '';
            document.querySelectorAll('.person-item').forEach((el) => {
                el.classList.toggle('hidden', !el.dataset.name.toLowerCase().includes(q) || (group !== '' && el.dataset.group !== group));
            });
        }

        // Tandai yang punya titik GPS pada tanggal terpilih, taruh di atas, lalu pilih otomatis
        // yang pertama kalau belum ada yang dipilih (atau yang dipilih tidak punya data).
        let lastCounts = initialCounts;
        function applyCounts(counts) {
            lastCounts = counts;
            const list = document.getElementById('person_list');
            const items = [...document.querySelectorAll('.person-item')];
            items.forEach((el) => {
                const n = counts[el.dataset.id] || 0;
                el.dataset.count = n;
                el.classList.toggle('no-data', n === 0);
                const badge = el.querySelector('.point-count');
                badge.textContent = n ? n + ' titik' : 'tidak ada GPS';
                badge.className = 'badge point-count ' + (n ? 'badge-primary' : 'badge-light');
            });
            items.sort((a, b) => (b.dataset.count > 0) - (a.dataset.count > 0) || a.dataset.name.localeCompare(b.dataset.name))
                .forEach((el) => list.appendChild(el));
            const shown = items.filter(isShown);
            const withData = shown.filter((el) => el.dataset.count > 0).length;
            document.getElementById('person_summary').textContent = withData + ' dari ' + shown.length + ' ' + label + ' punya data GPS pada tanggal ini.';

            const active = document.querySelector('.person-item.active');
            if (active && isShown(active) && active.dataset.count > 0) { load(); return; }
            const first = shown.find((el) => el.dataset.count > 0);
            if (first) { first.click(); first.scrollIntoView({ block: 'nearest' }); return; }
            if (active) active.classList.remove('active');
            routeLayer.clearLayers();
            document.getElementById('person_name_display').textContent = '-';
            document.getElementById('location_info').textContent = 'Tidak ada data lokasi pada tanggal ini';
        }

        document.getElementById('tracking_date').addEventListener('change', (e) => {
            if (!e.target.value) return;
            fetch(countsUrl + '?' + new URLSearchParams({ tanggal: e.target.value }))
                .then((r) => r.json())
                .then(applyCounts)
                .catch(() => { document.getElementById('location_info').textContent = 'Gagal memuat data'; });
        });
        applyCounts(initialCounts);
        document.getElementById('person_search').addEventListener('input', applyFilter);
        if (groupFilter) groupFilter.addEventListener('change', () => { applyFilter(); applyCounts(lastCounts); });

        // Klik peta: tampilkan info lokasi (alamat + koordinat).
        map.on('click', (e) => {
            const { lat, lng } = e.latlng;
            const popup = L.popup().setLatLng(e.latlng)
                .setContent('<strong>&#x1F4CD; Informasi Lokasi</strong><br><i class="text-gray-500">Memuat alamat...</i><br><small>Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6) + '</small>')
                .openOn(map);
            reverseGeocode(lat, lng).then((addr) => popup.setContent('<strong>&#x1F4CD; Informasi Lokasi</strong><br>' + escapeHtml(addr) + '<br><small class="text-gray-500">Lat: ' + lat.toFixed(6) + ' | Lng: ' + lng.toFixed(6) + '</small>'));
        });
    </script>
@endpush
