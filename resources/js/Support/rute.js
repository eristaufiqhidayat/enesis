import { angka, angkaRingkas, desimal, durasiSingkat, jarakSingkat, ringkasanPenjualan } from '@/Support/format.js';
import { alamatDari, escapeHtml, ikonBendera, ikonDepo, ikonLingkaran, ikonMotoris, tautanGoogleMap } from '@/Support/peta.js';

// Penggambar rute GPS satu orang pada satu tanggal, dipakai halaman Riwayat Lokasi (Tracking/History.vue) dan drawer
// rute di tabel Tracking Last Seen. Data dari endpoint `locations`: { total, locations: [{ lat, lng, waktu, akurasi }], depo }.

function pointPopup(judul, loc, alamat) {
    return '<div style="min-width:200px"><strong>' + judul + '</strong><br>'
        + '<small>Waktu: ' + escapeHtml(loc.waktu) + '</small><br>'
        + '<small class="text-gray-500">&#x1F4CD; ' + (alamat ? escapeHtml(alamat) : '<i>Memuat alamat...</i>') + '</small><br>'
        + '<small class="text-gray-500">Lat: ' + loc.lat + ' | Lng: ' + loc.lng + '</small>'
        + (loc.akurasi ? '<br><small class="text-gray-500">Akurasi: ' + loc.akurasi + 'm</small>' : '')
        + '<br>' + tautanGoogleMap(loc.lat, loc.lng) + '</div>';
}

// Gambar rute ke layer group `rute` (dikosongkan dulu) lalu zoom ke seluruh rute.
export function gambarRute(L, map, rute, data, nama) {
    rute.clearLayers();
    const locs = data.locations;
    if (!locs.length) return;

    const path = locs.map((l) => [parseFloat(l.lat), parseFloat(l.lng)]);
    L.polyline(path, { color: '#0d6efd', opacity: 0.8, weight: 4 }).addTo(rute);

    locs.forEach((loc, i) => {
        const first = i === 0;
        const last = i === locs.length - 1;
        const ll = [parseFloat(loc.lat), parseFloat(loc.lng)];
        const marker = first || last
            ? L.marker(ll, { icon: first ? ikonLingkaran('#198754', ikonMotoris) : ikonLingkaran('#dc3545', ikonBendera), zIndexOffset: 100 })
            : L.circleMarker(ll, { radius: 5, fillColor: '#0d6efd', color: '#fff', weight: 1, fillOpacity: 0.8 });
        const judul = first ? '&#x1F7E2; Start' : (last ? '&#x1F534; End' : '&#x1F535; Titik ' + (i + 1));
        if (first || last) {
            marker.bindTooltip(escapeHtml(nama) + ' &middot; ' + (first ? 'Start' : 'End'), { permanent: true, direction: 'top', offset: [0, -20], className: 'tracking-name-tooltip' });
        }
        marker.bindPopup(pointPopup(judul, loc));
        let dimuat = false;
        marker.on('popupopen', () => {
            if (dimuat) return;
            dimuat = true;
            alamatDari(loc.lat, loc.lng).then((alamat) => marker.setPopupContent(pointPopup(judul, loc, alamat)));
        });
        marker.addTo(rute);
    });

    if (data.depo && parseFloat(data.depo.latitude) && parseFloat(data.depo.longitude)) {
        const d = [parseFloat(data.depo.latitude), parseFloat(data.depo.longitude)];
        L.marker(d, { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: 50 })
            .bindPopup('<div class="text-center"><h6 class="mb-0">' + escapeHtml(data.depo.nama) + '</h6><small class="text-gray-500">Depo</small><br>' + tautanGoogleMap(d[0], d[1]) + '</div>')
            .addTo(rute);
    }

    map.fitBounds(L.latLngBounds(path), { padding: [30, 30] });
}


// Popover penanda kunjungan: semua info kunjungan yang tersedia; baris yang datanya kosong tidak ditampilkan.
// Nomornya berupa lingkaran berwarna sama dengan penanda di peta.
function popupKunjungan(nomor, k) {
    const baris = [];
    const lencana = [];
    if (k.di_luar_rute) lencana.push('<span class="badge badge-warning">Di luar rute</span>');
    if (k.lokasi_palsu) lencana.push('<span class="badge badge-danger">Lokasi palsu</span>');
    if (k.outlet_ditandai) lencana.push('<span class="badge badge-danger">Ditandai</span>');

    baris.push('<small>' + escapeHtml(k.jam) + (k.keluar ? ' – ' + escapeHtml(k.keluar) : '') + (k.durasi_menit !== null ? ' (' + k.durasi_menit + ' menit)' : '') + '</small>');
    baris.push('<small class="text-gray-500">' + (k.beli ? 'Beli' + (k.penjualan ? ': ' + ringkasanPenjualan(k.penjualan) : '') : 'Tidak beli' + (k.keterangan ? ': ' + escapeHtml(k.keterangan) : '')) + '</small>');
    if (lencana.length) baris.push('<div class="popup-kunjungan-lencana">' + lencana.join('') + '</div>');
    if (k.perjalanan) {
        baris.push('<small class="text-gray-500">Dari kunjungan sebelumnya: ' + durasiSingkat(k.perjalanan.menit)
            + (k.perjalanan.jarak_m != null ? ' · ' + jarakSingkat(k.perjalanan.jarak_m) : '') + '</small>');
    }
    if (k.jarak_m != null) baris.push('<small class="text-gray-500">Jarak ke toko saat checkin: ' + jarakSingkat(k.jarak_m) + '</small>');
    // Penanda hanya digambar untuk kunjungan yang punya koordinat, jadi lat/lng di sini selalu ada.
    baris.push(tautanGoogleMap(k.lat, k.lng));

    return '<div class="popup-kunjungan">'
        + '<div class="popup-kunjungan-judul"><span class="rute-kunjungan-nomor ' + (k.beli ? 'rute-kunjungan-beli' : 'rute-kunjungan-tidak') + '">' + nomor + '</span>'
        + '<strong>' + escapeHtml(k.outlet) + '</strong></div>' + baris.join('') + '</div>';
}

// Penanda bernomor tiap kunjungan outlet yang punya koordinat (hijau = beli, oranye = tidak beli) ke layer group
// `lapisan` (dikosongkan dulu). Mengembalikan Map id kunjungan -> marker, untuk memusatkan peta dari daftar.
export function gambarKunjungan(L, lapisan, kunjungan) {
    lapisan.clearLayers();
    const penanda = new Map();

    kunjungan.forEach((k, i) => {
        if (k.lat === null || k.lng === null) return;

        const marker = L.marker([k.lat, k.lng], {
            icon: L.divIcon({
                className: '',
                html: '<span class="penanda-kunjungan-isi ' + (k.beli ? 'penanda-kunjungan-beli' : 'penanda-kunjungan-tidak') + '">' + (i + 1) + '</span>',
                iconSize: [26, 26],
            }),
            zIndexOffset: 200,
        }).bindPopup(popupKunjungan(i + 1, k));
        marker.addTo(lapisan);
        penanda.set(k.id, marker);
    });

    return penanda;
}

function popupLompatan(s) {
    return '<div class="popup-kunjungan"><strong>Lompatan posisi</strong>'
        + '<small>' + desimal(s.km) + ' km dalam ' + angkaRingkas(s.menit, 1) + ' menit (' + angka(s.kmh) + ' km/jam)</small>'
        + '<small class="text-gray-500">' + escapeHtml(s.dari.waktu.slice(11, 19)) + ' → ' + escapeHtml(s.ke.waktu.slice(11, 19))
        + (s.kembali ? ' · lalu kembali ke lokasi semula' : '') + '</small></div>';
}

function popupMenyimpang(t) {
    return '<div class="popup-kunjungan"><strong>Titik GPS menyimpang</strong>'
        + '<small>' + desimal(t.km) + ' km dari posisi sekitarnya</small>'
        + '<small class="text-gray-500">' + escapeHtml(t.waktu.slice(11, 19)) + ' · lalu langsung kembali ke lokasi semula</small></div>';
}

// Garis merah putus-putus untuk tiap lompatan posisi mustahil, dan lingkaran merah berpusat putih untuk tiap titik GPS
// menyimpang tunggal (indikasi GPS tidak wajar), ke layer group `lapisan` (dikosongkan dulu). Keduanya datang dari
// AnomaliGps di endpoint locations.
export function gambarLompatan(L, lapisan, segmen, titikMenyimpang = []) {
    lapisan.clearLayers();

    titikMenyimpang.forEach((t) => {
        L.circleMarker([t.lat, t.lng], { radius: 7, color: '#dc2626', weight: 3, fillColor: '#ffffff', fillOpacity: 1 })
            .bindPopup(popupMenyimpang(t))
            .addTo(lapisan);
    });

    segmen.forEach((s) => {
        L.polyline([[s.dari.lat, s.dari.lng], [s.ke.lat, s.ke.lng]], { color: '#dc2626', weight: 3, opacity: 0.9, dashArray: '6 6' })
            .bindPopup(popupLompatan(s))
            .addTo(lapisan);
    });
}
