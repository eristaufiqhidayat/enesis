// Helper peta Leaflet yang dipakai bersama halaman tracking dan design area.

export const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// SVG Lucide ditulis manual karena isinya dirakit jadi string HTML untuk popup dan kontrol Leaflet.
export const ikonSvg = (isi) => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px">' + isi + '</svg>';

export const ikonSatelit = ikonSvg('<path d="m13.5 6.5-3.148-3.148a1.205 1.205 0 0 0-1.704 0L6.352 5.648a1.205 1.205 0 0 0 0 1.704L9.5 10.5"/><path d="M16.5 7.5 19 5"/><path d="m17.5 10.5 3.148 3.148a1.205 1.205 0 0 1 0 1.704l-2.296 2.296a1.205 1.205 0 0 1-1.704 0L13.5 14.5"/><path d="M9 21a6 6 0 0 0-6-6"/><path d="M9.352 10.648a1.205 1.205 0 0 0 0 1.704l2.296 2.296a1.205 1.205 0 0 0 1.704 0l4.296-4.296a1.205 1.205 0 0 0 0-1.704l-2.296-2.296a1.205 1.205 0 0 0-1.704 0z"/>');
export const ikonPeta = ikonSvg('<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/>');
export const ikonRute = ikonSvg('<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>');
export const ikonPin = ikonSvg('<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>');

// Ikon lingkaran berwarna untuk penanda (start/end, depo).
export function ikonLingkaran(warna, isi) {
    return window.L.divIcon({
        className: '',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="40" height="40"><circle cx="32" cy="32" r="30" fill="' + warna + '" opacity="0.9"/>' + isi + '</svg>',
    });
}

export const ikonMotoris = '<circle cx="16" cy="38" r="7" fill="none" stroke="#fff" stroke-width="3"/><circle cx="48" cy="38" r="7" fill="none" stroke="#fff" stroke-width="3"/>'
    + '<line x1="16" y1="38" x2="32" y2="28" stroke="#fff" stroke-width="2.5"/><line x1="32" y1="28" x2="48" y2="38" stroke="#fff" stroke-width="2.5"/><circle cx="38" cy="22" r="5" fill="#fff"/>';
export const ikonDepo = '<rect x="18" y="32" width="28" height="18" fill="#fff"/><polygon points="12,33 32,14 52,33" fill="#fff"/>';

export const tautanGoogleMap = (lat, lng) => '<a class="btn btn-sm btn-primary mt-2" style="font-size:11px" target="_blank" rel="noopener" href="https://maps.google.com/maps?q=loc:' + lat + ',' + lng + '&t=k">' + ikonPin + 'Lihat di Google Map</a>';

// Alamat dari koordinat lewat Nominatim; gagal = '-'.
export function alamatDari(lat, lng) {
    return fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
        .then((r) => r.json())
        .then((d) => d.display_name || '-')
        .catch(() => '-');
}
