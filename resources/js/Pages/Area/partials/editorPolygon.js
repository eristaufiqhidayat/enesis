// Editor bentuk polygon untuk Design Area, tanpa plugin Leaflet tambahan.
// - Seret titik (kotak putih) untuk memindahkan.
// - Klik titik untuk menghapusnya (cincin minimal 3 titik).
// - Seret/klik titik tengah (bulat kecil) untuk menyisipkan titik baru.
// Polygon resmi bisa ribuan titik, jadi penanda hanya digambar untuk titik di dalam layar; bila terlalu banyak,
// user diminta memperbesar peta.

export const MAKS_PENANDA = 600;

const salin = (cincin) => cincin.map((c) => ({ ...c, titik: c.titik.map((p) => [...p]) }));

// Cincin GeoJSON tertutup [lng, lat] → titik terbuka [lat, lng].
function buka(ring) {
    const titik = ring.map(([lng, lat]) => [lat, lng]);
    const [a, b] = [titik[0], titik[titik.length - 1]];
    if (titik.length > 1 && a[0] === b[0] && a[1] === b[1]) titik.pop();
    return titik;
}

/**
 * @param {object} L        Leaflet
 * @param {object} peta     instance peta
 * @param {(info: {titik: number, tampil: boolean, bisaUrung: boolean}) => void} onUbah  dipanggil tiap perubahan / geser peta
 */
export function buatEditorPolygon(L, peta, onUbah) {
    let cincin = []; // [{ poly, ring, titik: [[lat, lng], ...] }]
    let riwayat = [];
    let lapisan = null;
    let bentuk = null;
    let penanda = null;

    const ikonTitik = L.divIcon({ className: 'penanda-titik', iconSize: [12, 12] });
    const ikonTengah = L.divIcon({ className: 'penanda-tengah', iconSize: [10, 10] });

    const jumlahTitik = () => cincin.reduce((n, c) => n + c.titik.length, 0);

    // Bentuk Leaflet: polygon → cincin → titik.
    function latlngs() {
        const per = [];
        cincin.forEach((c) => {
            per[c.poly] ??= [];
            per[c.poly][c.ring] = c.titik;
        });
        return per.filter(Boolean).map((p) => p.filter(Boolean));
    }

    function kabari(tampil) {
        onUbah({ titik: jumlahTitik(), tampil, bisaUrung: riwayat.length > 0 });
    }

    function simpanRiwayat() {
        riwayat.push(salin(cincin));
        if (riwayat.length > 50) riwayat.shift();
    }

    function gambarBentuk() {
        bentuk.setLatLngs(latlngs());
    }

    function gambarPenanda() {
        if (!penanda) return;
        penanda.clearLayers();
        const batas = peta.getBounds().pad(0.1);
        let terlihat = 0;
        cincin.forEach((c) => c.titik.forEach((p) => { if (batas.contains(p)) terlihat++; }));
        if (terlihat > MAKS_PENANDA) {
            kabari(false);
            return;
        }

        cincin.forEach((c) => {
            const n = c.titik.length;
            c.titik.forEach((p, i) => {
                const q = c.titik[(i + 1) % n];
                if (batas.contains(p)) {
                    const m = L.marker(p, { icon: ikonTitik, draggable: true, title: 'Seret untuk memindah, klik untuk menghapus' }).addTo(penanda);
                    m.on('dragstart', simpanRiwayat);
                    m.on('drag', (e) => {
                        const { lat, lng } = e.target.getLatLng();
                        c.titik[i] = [lat, lng];
                        gambarBentuk();
                    });
                    m.on('dragend', () => { gambarPenanda(); });
                    m.on('click', () => hapusTitik(c, i));
                }
                // Titik tengah untuk menyisipkan titik baru di antara p dan q.
                const tengah = [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2];
                if (batas.contains(tengah)) {
                    const t = L.marker(tengah, { icon: ikonTengah, draggable: true, title: 'Seret atau klik untuk menambah titik' }).addTo(penanda);
                    let disisip = false;
                    const sisip = () => {
                        if (disisip) return;
                        disisip = true;
                        simpanRiwayat();
                        c.titik.splice(i + 1, 0, tengah);
                    };
                    t.on('dragstart', sisip);
                    t.on('drag', (e) => {
                        const { lat, lng } = e.target.getLatLng();
                        c.titik[i + 1] = [lat, lng];
                        gambarBentuk();
                    });
                    t.on('dragend', () => gambarPenanda());
                    t.on('click', () => { sisip(); gambarBentuk(); gambarPenanda(); });
                }
            });
        });
        kabari(true);
    }

    function hapusTitik(c, i) {
        if (c.titik.length <= 3) {
            alert('Cincin polygon minimal 3 titik.');
            return;
        }
        simpanRiwayat();
        c.titik.splice(i, 1);
        gambarBentuk();
        gambarPenanda();
    }

    return {
        /** Mulai mengedit. multi = koordinat MultiPolygon GeoJSON [lng, lat]. */
        mulai(multi) {
            this.batal();
            cincin = [];
            multi.forEach((poly, pi) => poly.forEach((ring, ri) => cincin.push({ poly: pi, ring: ri, titik: buka(ring) })));
            riwayat = [];
            lapisan = L.layerGroup().addTo(peta);
            bentuk = L.polygon(latlngs(), { color: '#dc3545', weight: 2, dashArray: '6 4', fillOpacity: 0.15 }).addTo(lapisan);
            penanda = L.layerGroup().addTo(lapisan);
            peta.fitBounds(bentuk.getBounds(), { maxZoom: 17 });
            peta.on('moveend', gambarPenanda);
            gambarPenanda();
        },

        urungkan() {
            if (!riwayat.length) return;
            cincin = riwayat.pop();
            gambarBentuk();
            gambarPenanda();
        },

        /** Bentuk hasil edit sebagai MultiPolygon GeoJSON [lng, lat], cincin tertutup. */
        hasil() {
            return latlngs().map((poly) => poly.map((ring) => {
                const titik = ring.map(([lat, lng]) => [lng, lat]);
                return [...titik, titik[0]];
            }));
        },

        batal() {
            peta.off('moveend', gambarPenanda);
            lapisan?.remove();
            lapisan = bentuk = penanda = null;
            cincin = [];
            riwayat = [];
        },
    };
}
