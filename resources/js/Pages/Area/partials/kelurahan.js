// Status kelurahan di halaman Generate Polygon Kelurahan, dipakai daftar, legenda, dan peta.

export const STATUS = [
    { id: 'siap', label: 'Siap dibuat', badge: 'success', warnaPeta: '#4363d8', garisPeta: '#ffffff' },
    { id: 'ada', label: 'Sudah punya area', badge: 'warning', warnaPeta: '#fde68a', garisPeta: '#d97706' },
    { id: 'tanpa', label: 'Tanpa batas resmi', badge: 'secondary', warnaPeta: '#e5e7eb', garisPeta: '#9ca3af' },
];

export function statusKelurahan(k) {
    if (k.area?.length) return 'ada';
    return k.geometri ? 'siap' : 'tanpa';
}

// Warna tetap per nama kecamatan supaya kelurahan bertetangga dalam satu kecamatan sewarna.
const PALET = ['#4363d8', '#3cb44b', '#e6194b', '#f58231', '#911eb4', '#42d4f4', '#f032e6', '#469990', '#9A6324', '#800000', '#808000', '#000075'];

export function warnaKecamatan(nama) {
    let h = 0;
    for (const c of String(nama ?? '')) h = (h * 31 + c.charCodeAt(0)) >>> 0;
    return PALET[h % PALET.length];
}

// Jarak garis lurus (km) dari depo ke titik tengah kelurahan; null bila salah satu tanpa koordinat.
export function jarakKm(depo, k) {
    if (!depo?.lat || !depo?.lng || k.lat === null || k.lng === null) return null;
    const rad = (d) => (d * Math.PI) / 180;
    const dLat = rad(k.lat - depo.lat);
    const dLng = rad(k.lng - depo.lng);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(depo.lat)) * Math.cos(rad(k.lat)) * Math.sin(dLng / 2) ** 2;
    return 2 * 6371.0088 * Math.asin(Math.min(1, Math.sqrt(a)));
}
