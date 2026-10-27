<script setup>
// Peta wilayah kerja sales: polygon tiap area penugasan (= baris menu Master Area), titik outlet rute, dan depo.
// Area aktif (rute HP) biru, area tidak aktif abu-abu putus-putus. Klik area untuk melihat kode Master Area-nya.
import { ref } from 'vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { escapeHtml, ikonDepo, ikonLingkaran, multiKeLatLng } from '@/Support/peta.js';
import { angka } from '@/Support/format.js';

const props = defineProps({
    peta: { type: Object, required: true },
    jumlahOutlet: { type: Object, default: () => ({}) }, // areaId → jumlah outlet
    masterAreaUrl: { type: String, required: true },
    designUrl: { type: String, required: true },
});

const tanpaPolygon = props.peta.area.filter((a) => !a.geometri);

let L = null;
// Bukan `peta`: nama itu dipakai prop data peta, dan template akan membaca variabel setup yang null.
let petaLeaflet = null;
const lapisArea = new Map();

const wadah = ref(null);
useLeafletMap(wadah, {
    onSiap(map, Lib) {
        L = Lib;
        petaLeaflet = map;
        gambar();
    },
});

function gaya(a) {
    return a.rute
        ? { color: '#1d4ed8', weight: 2, fillColor: '#3b82f6', fillOpacity: 0.25 }
        : { color: '#6b7280', weight: 1.5, dashArray: '5 4', fillColor: '#9ca3af', fillOpacity: 0.12 };
}

function popup(a) {
    const wilayah = [a.kelurahan, a.kecamatan, a.kota].filter(Boolean).map(escapeHtml).join(', ');
    return '<div style="min-width:220px;font-size:12px">'
        + '<strong style="font-size:13px">' + escapeHtml(a.nama ?? 'Area #' + a.areaId) + '</strong>'
        + '<br><span class="text-gray-500">Master Area: ' + escapeHtml(a.kode ?? '-') + '</span><hr class="my-1">'
        + '<div><b>Status:</b> ' + (a.rute ? 'Aktif (rute HP)' : 'Tidak aktif') + '</div>'
        + '<div><b>Depo:</b> ' + escapeHtml(a.depo ?? '-') + '</div>'
        + (wilayah ? '<div><b>Wilayah:</b> ' + wilayah + '</div>' : '')
        + '<div><b>Outlet:</b> ' + angka(props.jumlahOutlet[a.areaId] ?? 0) + '</div>'
        + '<div class="flex flex-wrap gap-1 mt-2">'
        + (a.kode ? '<a class="btn btn-sm btn-outline-primary" href="' + props.masterAreaUrl + '?q=' + encodeURIComponent(a.kode) + '">Buka di Master Area</a>' : '')
        + (a.areaPolygonId ? '<a class="btn btn-sm btn-outline-primary" href="' + props.designUrl + '?area=' + a.areaPolygonId + '">Lihat di Design Area</a>' : '')
        + '</div></div>';
}

function gambar() {
    const semua = L.featureGroup().addTo(petaLeaflet);

    // Area tidak aktif digambar dulu supaya area aktif berada di atasnya.
    [...props.peta.area].sort((a, b) => Number(a.rute) - Number(b.rute)).forEach((a) => {
        if (!a.geometri) return;
        const lapis = L.polygon(multiKeLatLng(a.geometri), gaya(a))
            .bindTooltip(escapeHtml((a.kode ? a.kode + ' · ' : '') + (a.nama ?? '')), { sticky: true })
            .on('click', (e) => L.popup().setLatLng(e.latlng).setContent(popup(a)).openOn(petaLeaflet));
        lapis.addTo(semua);
        lapisArea.set(a.areaId, lapis);
    });

    // Titik outlet di canvas: bisa ribuan.
    const kanvas = L.canvas({ padding: 0.5 });
    props.peta.outlet.forEach((o) => {
        L.circleMarker([o.lat, o.lng], { renderer: kanvas, radius: 3, color: '#ffffff', weight: 1, fillColor: '#f59e0b', fillOpacity: 0.95 })
            .bindTooltip('<b>' + escapeHtml(o.nama ?? '-') + '</b><br><small>' + escapeHtml(o.kode ?? '') + '</small>')
            .addTo(semua);
    });

    if (props.peta.depo) {
        L.marker([props.peta.depo.lat, props.peta.depo.lng], { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: 500 })
            .bindTooltip('<b>' + escapeHtml(props.peta.depo.nama) + '</b><br><small>Depo</small>')
            .addTo(semua);
    }

    if (semua.getLayers().length) petaLeaflet.fitBounds(semua.getBounds(), { padding: [20, 20], maxZoom: 16 });
}

// Dipanggil dari badge area di kartu outlet: zoom ke area dan buka popupnya.
function fokus(areaId) {
    const lapis = lapisArea.get(areaId);
    const a = props.peta.area.find((x) => x.areaId === areaId);
    if (!lapis || !a) return;
    petaLeaflet.fitBounds(lapis.getBounds(), { maxZoom: 17 });
    L.popup().setLatLng(lapis.getBounds().getCenter()).setContent(popup(a)).openOn(petaLeaflet);
}

defineExpose({ fokus });
</script>

<template>
    <div class="mb-3">
        <div ref="wadah" class="h-100 rounded-md" aria-label="Peta wilayah kerja sales"></div>
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-gray-500">
            <span><span class="legenda-peta legenda-peta-aktif"></span>Area aktif (rute HP)</span>
            <span><span class="legenda-peta legenda-peta-nonaktif"></span>Area tidak aktif</span>
            <span><span class="legenda-peta legenda-peta-outlet"></span>Outlet area rute ({{ angka(props.peta.outlet.length) }}{{ props.peta.outletTerpotong ? '+, sebagian' : '' }})</span>
            <span>Klik area untuk melihat kode Master Area-nya.</span>
        </div>
        <p v-if="tanpaPolygon.length" class="text-xs text-amber-700 mt-1">
            {{ tanpaPolygon.length }} area belum punya polygon di Master Area, jadi tidak tampil di peta:
            {{ tanpaPolygon.map((a) => a.nama ?? `#${a.areaId}`).join(', ') }}.
        </p>
    </div>
</template>
