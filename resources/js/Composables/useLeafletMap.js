import { onMounted, onUnmounted } from 'vue';
import { ikonPeta, ikonRute, ikonSatelit } from '@/Support/peta.js';

const LEAFLET_CSS = '/vendor/leaflet/leaflet.css';
const LEAFLET_JS = '/vendor/leaflet/leaflet.js';

// Leaflet dimuat dari public/vendor/leaflet sekali saja. Kunjungan Inertia berikutnya memakai window.L yang sama.
let muat = null;
function muatLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    if (!muat) {
        muat = new Promise((resolve, reject) => {
            if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = LEAFLET_CSS;
                document.head.appendChild(css);
            }
            const js = document.createElement('script');
            js.src = LEAFLET_JS;
            js.onload = () => resolve(window.L);
            js.onerror = () => {
                js.remove();
                muat = null;
                reject(new Error('Gagal memuat Leaflet'));
            };
            document.head.appendChild(js);
        });
    }
    return muat;
}

// Peta dengan layer dasar (Satelit / 2D Map / Garis Jalan) seperti di Marketama.
function buatPeta(L, elemen, pusat, zoom) {
    const map = L.map(elemen, { center: pusat, zoom });

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

    const LayerToggle = L.Control.extend({
        options: { position: 'topright' },
        onAdd() {
            const el = L.DomUtil.create('div', 'flex flex-col items-end gap-1');
            el.innerHTML = '<div class="flex gap-1 shadow-sm rounded-md overflow-hidden"><button type="button" class="btn btn-sm btn-primary" data-layer="sat">' + ikonSatelit + 'Satelit</button>'
                + '<button type="button" class="btn btn-sm btn-light" data-layer="2d">' + ikonPeta + '2D Map</button></div>'
                + '<button type="button" class="btn btn-sm btn-light shadow-sm" data-layer="roads">' + ikonRute + 'Garis Jalan</button>';
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

    return map;
}

// Membuat peta Leaflet saat komponen dipasang dan melepasnya saat komponen dilepas, supaya
// kunjungan Inertia ke halaman lain tidak meninggalkan instance peta yang masih menempel.
// wadah: ref ke elemen peta. Pemakai memanggil peta() untuk mengambil instance (null setelah dilepas).
export function useLeafletMap(wadah, { pusat = [-6.177031, 106.839651], zoom = 11, onSiap } = {}) {
    let map = null;
    let dilepas = false;

    onMounted(async () => {
        try {
            const L = await muatLeaflet();
            if (dilepas || !wadah.value) return;
            map = buatPeta(L, wadah.value, pusat, zoom);
            onSiap?.(map, L);
        } catch (e) {
            console.error(e);
        }
    });

    onUnmounted(() => {
        dilepas = true;
        map?.remove();
        map = null;
    });

    return { peta: () => map };
}
