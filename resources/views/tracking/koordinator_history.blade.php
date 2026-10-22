@extends('layouts.app')

@section('title', $page['title'])

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <style>
        #tracking_map { height: calc(100vh - 160px); min-height: 480px; border-radius: .375rem; }
        .koordinator-item { cursor: pointer; }
        .koordinator-item.no-data { opacity: .55; }
        .koordinator-item.active { background: #e7f1ff; color: #212529; border-color: #dee2e6; border-left: 3px solid #0d6efd; }
        .layer-toggle .btn { font-size: 12px; font-weight: 600; }
        .tracking-name-tooltip { font-weight: 600; }
    </style>
@endpush

@section('content')
    <h5 class="mb-3">{{ $page['title'] }}</h5>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light"><h6 class="mb-0">Filter Tracking</h6></div>
                <div class="card-body">
                    <label class="form-label" for="tracking_date">Tanggal</label>
                    <input type="date" id="tracking_date" class="form-control form-control-sm mb-3" value="{{ $tanggal }}">

                    <label class="form-label" for="koordinator_search">Koordinator</label>
                    <input type="search" id="koordinator_search" class="form-control form-control-sm mb-2" placeholder="Cari koordinator...">
                    <div class="small text-muted mb-2" id="koordinator_summary"></div>
                    <div class="list-group" id="koordinator_list" style="max-height: 50vh; overflow-y: auto;">
                        @forelse ($koordinators as $k)
                            <a class="list-group-item list-group-item-action koordinator-item py-2" data-id="{{ $k->koordinator_id }}" data-name="{{ $k->nama }}">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="fw-semibold">{{ $k->nama }}</span>
                                    <span class="badge rounded-pill point-count"></span>
                                </div>
                                <small class="text-muted"><i class="fas fa-warehouse me-1"></i>{{ $k->depo ?: '-' }}
                                    @if ($k->telepon)<span class="ms-2"><i class="fas fa-phone me-1"></i>{{ $k->telepon }}</span>@endif
                                </small>
                            </a>
                        @empty
                            <div class="text-muted small">Belum ada data. Jalankan <code>php artisan marketama:import</code>.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-light d-flex flex-wrap gap-3 small">
                    <span><b>Koordinator:</b> <span id="koordinator_name_display">-</span></span>
                    <span><b>Tanggal:</b> <span id="date_display">-</span></span>
                    <span><b>Info:</b> <span id="location_info">Pilih koordinator</span></span>
                </div>
                <div class="card-body p-2">
                    <div id="tracking_map"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        const locationsUrl = @json(route('tracking-koordinator.locations'));
        const countsUrl = @json(route('tracking-koordinator.counts'));
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
                const el = L.DomUtil.create('div', 'layer-toggle d-flex flex-column align-items-end gap-1');
                el.innerHTML = '<div class="btn-group shadow-sm"><button type="button" class="btn btn-sm btn-primary" data-layer="sat"><i class="fas fa-satellite me-1"></i>Satelit</button>'
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

        const gmaps = (lat, lng) => '<a class="btn btn-sm btn-primary mt-2 text-white" style="font-size:11px" target="_blank" rel="noopener" href="https://maps.google.com/maps?q=loc:' + lat + ',' + lng + '&t=k"><i class="fas fa-map-marked-alt me-1"></i>Lihat di Google Map</a>';
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
                + '<small class="text-secondary">&#x1F4CD; ' + (address ? escapeHtml(address) : '<i>Memuat alamat...</i>') + '</small><br>'
                + '<small class="text-muted">Lat: ' + loc.lat + ' | Lng: ' + loc.lng + '</small>'
                + (loc.akurasi ? '<br><small class="text-muted">Akurasi: ' + loc.akurasi + 'm</small>' : '')
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
                    .bindPopup('<div class="text-center"><h6 class="mb-0">' + escapeHtml(data.depo.nama) + '</h6><small class="text-muted">Depo</small><br>' + gmaps(d[0], d[1]) + '</div>')
                    .addTo(routeLayer);
            }

            map.fitBounds(L.latLngBounds(path), { padding: [30, 30] });
        }

        function load() {
            const active = document.querySelector('.koordinator-item.active');
            const tanggal = document.getElementById('tracking_date').value;
            if (!active || !tanggal) return;
            const name = active.dataset.name;
            document.getElementById('koordinator_name_display').textContent = name;
            document.getElementById('date_display').textContent = tanggal;
            document.getElementById('location_info').textContent = 'Memuat...';

            fetch(locationsUrl + '?' + new URLSearchParams({ koordinator_id: active.dataset.id, tanggal }))
                .then((r) => r.json())
                .then((data) => {
                    document.getElementById('location_info').textContent = data.total ? data.total + ' titik lokasi' : 'Tidak ada data lokasi';
                    if (data.total) draw(data, name); else routeLayer.clearLayers();
                })
                .catch(() => { document.getElementById('location_info').textContent = 'Gagal memuat data'; });
        }

        document.querySelectorAll('.koordinator-item').forEach((el) => el.addEventListener('click', () => {
            document.querySelectorAll('.koordinator-item').forEach((x) => x.classList.remove('active'));
            el.classList.add('active');
            load();
        }));
        // Tandai koordinator yang punya titik GPS pada tanggal terpilih, taruh di atas, lalu pilih otomatis
        // yang pertama kalau belum ada yang dipilih (atau yang dipilih tidak punya data).
        function applyCounts(counts) {
            const list = document.getElementById('koordinator_list');
            const items = [...document.querySelectorAll('.koordinator-item')];
            let withData = 0;
            items.forEach((el) => {
                const n = counts[el.dataset.id] || 0;
                el.dataset.count = n;
                el.classList.toggle('no-data', n === 0);
                const badge = el.querySelector('.point-count');
                badge.textContent = n ? n + ' titik' : 'tidak ada GPS';
                badge.className = 'badge rounded-pill point-count ' + (n ? 'bg-primary' : 'bg-light text-muted');
                if (n) withData++;
            });
            items.sort((a, b) => (b.dataset.count > 0) - (a.dataset.count > 0) || a.dataset.name.localeCompare(b.dataset.name))
                .forEach((el) => list.appendChild(el));
            document.getElementById('koordinator_summary').textContent = withData + ' dari ' + items.length + ' koordinator punya data GPS pada tanggal ini.';

            const active = document.querySelector('.koordinator-item.active');
            if (active && active.dataset.count > 0) { load(); return; }
            const first = items.find((el) => el.dataset.count > 0);
            if (first) { first.click(); first.scrollIntoView({ block: 'nearest' }); return; }
            if (active) active.classList.remove('active');
            routeLayer.clearLayers();
            document.getElementById('koordinator_name_display').textContent = '-';
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
        document.getElementById('koordinator_search').addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('.koordinator-item').forEach((el) => {
                el.classList.toggle('d-none', !el.dataset.name.toLowerCase().includes(q));
            });
        });

        // Klik peta: tampilkan info lokasi (alamat + koordinat).
        map.on('click', (e) => {
            const { lat, lng } = e.latlng;
            const popup = L.popup().setLatLng(e.latlng)
                .setContent('<strong>&#x1F4CD; Informasi Lokasi</strong><br><i class="text-muted">Memuat alamat...</i><br><small>Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6) + '</small>')
                .openOn(map);
            reverseGeocode(lat, lng).then((addr) => popup.setContent('<strong>&#x1F4CD; Informasi Lokasi</strong><br>' + escapeHtml(addr) + '<br><small class="text-muted">Lat: ' + lat.toFixed(6) + ' | Lng: ' + lng.toFixed(6) + '</small>'));
        });
    </script>
@endpush
