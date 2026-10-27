<script setup>
// Generate Polygon Kelurahan: pilih depo + kota (opsional kecamatan), server mengirim batas resmi tiap kelurahan,
// user memilih kelurahan di daftar/peta, lalu server membuat 1 area per kelurahan.
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatRow from '@/Components/ui/molecules/StatRow.vue';
import FormWilayah from '@/Pages/Area/partials/FormWilayah.vue';
import ItemKelurahan from '@/Pages/Area/partials/ItemKelurahan.vue';
import { useFormWilayah } from '@/Pages/Area/partials/formWilayah.js';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { bersihkanQuery } from '@/Support/query.js';
import { escapeHtml, ikonDepo, ikonLingkaran, multiKeLatLng } from '@/Support/peta.js';
import { STATUS, jarakKm, statusKelurahan, warnaKecamatan } from '@/Pages/Area/partials/kelurahan.js';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    provinsi: { type: Array, required: true },
    depo: { type: Array, required: true },
    bisaTambah: { type: Boolean, default: false },
    kembaliUrl: { type: String, required: true },
    autoGridUrl: { type: String, default: null },
    opsiUrl: { type: String, required: true },
    pratinjauUrl: { type: String, required: true },
    simpanUrl: { type: String, required: true },
});

const appName = usePage().props.appName;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

// ---------- Pengaturan ----------
const { form, opsi, opsiDepo, opsiProvinsi, depoTerpilih } = useFormWilayah(props);

watch(depoTerpilih, () => {
    gambarDepo();
    // Jarak di tooltip ikut depo yang baru.
    kelurahan.value.forEach((k) => overlay.get(k.id)?.setTooltipContent(tooltipKelurahan(k)));
});

// ---------- Hasil pratinjau ----------
const kelurahan = ref([]);
const dipilih = ref(new Set());
const memuat = ref(false);
const menyimpan = ref(false);
const peringatan = ref(null);
const hasilSimpan = ref(null);
const judulHasil = ref('');
const cari = ref('');
const saring = ref('');

const ringkasan = computed(() => {
    const hitung = { total: kelurahan.value.length, siap: 0, ada: 0, tanpa: 0 };
    kelurahan.value.forEach((k) => { hitung[statusKelurahan(k)]++; });
    return hitung;
});

const tampil = computed(() => {
    const q = cari.value.trim().toLowerCase();
    return kelurahan.value.filter((k) => (!saring.value || statusKelurahan(k) === saring.value)
        && (!q || (k.nama + ' ' + k.kecamatan + ' ' + (k.kode ?? '')).toLowerCase().includes(q)));
});

// Pilih Semua hanya untuk kelurahan yang siap dan sedang tampil (ikut pencarian/saringan).
const bisaDipilihTampil = computed(() => tampil.value.filter((k) => statusKelurahan(k) === 'siap'));
const semuaTerpilih = computed(() => bisaDipilihTampil.value.length > 0 && bisaDipilihTampil.value.every((k) => dipilih.value.has(k.id)));
const sebagianTerpilih = computed(() => !semuaTerpilih.value && bisaDipilihTampil.value.some((k) => dipilih.value.has(k.id)));
const totalLuas = computed(() => kelurahan.value.filter((k) => dipilih.value.has(k.id)).reduce((n, k) => n + (k.luas_km2 ?? 0), 0));

function pilihSemua(on) {
    const baru = new Set(dipilih.value);
    bisaDipilihTampil.value.forEach((k) => (on ? baru.add(k.id) : baru.delete(k.id)));
    dipilih.value = baru;
}

function togglePilih(k) {
    if (statusKelurahan(k) !== 'siap') return;
    const baru = new Set(dipilih.value);
    baru.has(k.id) ? baru.delete(k.id) : baru.add(k.id);
    dipilih.value = baru;
}

async function tampilkan() {
    if (!form.kota_id) { alert('Pilih Kota / Kabupaten terlebih dahulu.'); return; }
    memuat.value = true;
    peringatan.value = null;
    hasilSimpan.value = null;
    try {
        const params = bersihkanQuery({ kota_id: form.kota_id, kecamatan_id: form.kecamatan_id });
        const res = await fetch(props.pratinjauUrl + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
        if (!res.ok) { alert('Gagal memuat daftar kelurahan.'); return; }
        const data = await res.json();
        kelurahan.value = data.kelurahan;
        peringatan.value = data.peringatan;
        judulHasil.value = data.kecamatan ? `Kec. ${data.kecamatan}, ${data.kota}` : data.kota;
        // Default: semua yang siap dibuat langsung terpilih.
        dipilih.value = new Set(data.kelurahan.filter((k) => statusKelurahan(k) === 'siap').map((k) => k.id));
        gambarKelurahan(true);
    } catch {
        alert('Gagal memuat daftar kelurahan.');
    } finally {
        memuat.value = false;
    }
}

async function simpan() {
    if (!form.warehouse_id) { alert('Pilih Depo yang akan memiliki area baru.'); return; }
    const ids = [...dipilih.value];
    if (!ids.length) return;
    if (!confirm(`Buat ${ids.length} area baru untuk depo ${depoTerpilih.value?.name ?? ''}?`)) return;

    menyimpan.value = true;
    try {
        const res = await fetch(props.simpanUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ warehouse_id: form.warehouse_id, kelurahan_ids: ids }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Gagal menyimpan area.');
            return;
        }
        await tampilkan();
        hasilSimpan.value = data;
    } finally {
        menyimpan.value = false;
    }
}

// ---------- Peta ----------
// Instance Leaflet bukan state reaktif Vue: disimpan di luar ref supaya tidak di-proxy.
let L = null;
let peta = null;
let lapisanKelurahan = null;
let lapisanDepo = null;
const overlay = new Map();

const wadah = ref(null);
useLeafletMap(wadah, {
    pusat: [-6.2, 106.8],
    zoom: 10,
    onSiap(map, Lib) {
        L = Lib;
        peta = map;
        lapisanKelurahan = L.featureGroup().addTo(map);
        lapisanDepo = L.layerGroup().addTo(map);
        gambarDepo();
    },
});

function gayaKelurahan(k) {
    const status = statusKelurahan(k);
    if (status === 'ada') return { color: '#d97706', weight: 1.5, dashArray: '4 3', fillColor: '#f59e0b', fillOpacity: 0.12 };
    const terpilih = dipilih.value.has(k.id);
    const warna = warnaKecamatan(k.kecamatan);
    return { color: '#ffffff', weight: 1.5, fillColor: terpilih ? warna : '#6c757d', fillOpacity: terpilih ? 0.45 : 0.12 };
}

function tooltipKelurahan(k) {
    const area = k.area[0];
    const jarak = jarakKm(depoTerpilih.value, k);
    return '<b>' + escapeHtml(k.nama) + '</b><br><small>Kec. ' + escapeHtml(k.kecamatan) + ' · ' + (k.luas_km2 ?? 0).toLocaleString('id-ID') + ' km²'
        + (jarak !== null ? ' · ' + jarak.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' km dari depo' : '') + '</small>'
        + (area ? '<br><small>Sudah ada: ' + escapeHtml(area.code) + (area.warehouse ? ' (' + escapeHtml(area.warehouse) + ')' : '') + '</small>' : '');
}

function gambarKelurahan(fokus = false) {
    if (!lapisanKelurahan) return;
    lapisanKelurahan.clearLayers();
    overlay.clear();
    kelurahan.value.forEach((k) => {
        if (!k.geometri) return;
        const lapis = L.polygon(multiKeLatLng(k.geometri), gayaKelurahan(k))
            .bindTooltip(tooltipKelurahan(k), { sticky: true })
            .on('click', () => togglePilih(k));
        lapis.addTo(lapisanKelurahan);
        overlay.set(k.id, lapis);
    });
    if (fokus && lapisanKelurahan.getLayers().length) peta.fitBounds(lapisanKelurahan.getBounds(), { padding: [20, 20] });
}

function gambarDepo() {
    if (!lapisanDepo) return;
    lapisanDepo.clearLayers();
    const depo = depoTerpilih.value;
    if (!depo?.lat || !depo?.lng) return;
    L.marker([depo.lat, depo.lng], { icon: ikonLingkaran('#6f42c1', ikonDepo), zIndexOffset: 500 })
        .bindTooltip('<b>' + escapeHtml(depo.name) + '</b><br><small>Depo</small>')
        .addTo(lapisanDepo);
}

// Warna polygon mengikuti pilihan tanpa menggambar ulang semua layer.
watch(dipilih, () => {
    kelurahan.value.forEach((k) => overlay.get(k.id)?.setStyle(gayaKelurahan(k)));
});

function fokus(k) {
    const lapis = overlay.get(k.id);
    if (!lapis) return;
    peta.fitBounds(lapis.getBounds(), { maxZoom: 16 });
    lapis.openTooltip(lapis.getBounds().getCenter());
}
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <AppLayout :breadcrumb-slug="slug">
        <PageHeader :title="title" subtitle="Buat area per kelurahan/desa dari batas wilayah resmi Kemendagri 2025">
            <template #actions>
                <Link v-if="autoGridUrl" :href="autoGridUrl" class="text-gray-500 hover:underline">Auto Grid</Link>
                <Link :href="kembaliUrl" class="text-gray-500 hover:underline">Kembali ke Design Area</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
            <div class="lg:col-span-4 flex flex-col gap-2">
                <Card body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Pengaturan</h6></template>

                    <div class="flex flex-col gap-2">
                        <FormWilayah :form="form" :opsi="opsi" :opsi-depo="opsiDepo" :opsi-provinsi="opsiProvinsi" id-prefix="gen" />
                        <Button id="btn_tampilkan" variant="primary" :loading="memuat" :disabled="!form.kota_id" @click="tampilkan">
                            <Icon name="search" class="me-1" />Tampilkan Kelurahan
                        </Button>
                        <small class="text-gray-500">Batas satu kota diambil sekali dari dataset lalu disimpan, jadi pemakaian berikutnya langsung tampil.</small>
                    </div>
                </Card>

                <Alert v-if="peringatan" variant="warning"><Icon name="info" class="me-1" />{{ peringatan }}</Alert>
                <Alert v-if="hasilSimpan" :variant="hasilSimpan.dibuat ? 'success' : 'warning'">
                    <div v-if="hasilSimpan.dibuat"><b>{{ hasilSimpan.dibuat }} area dibuat</b> ({{ hasilSimpan.kode[0] }} s.d. {{ hasilSimpan.kode[1] }}).</div>
                    <div v-else>Tidak ada area baru yang dibuat.</div>
                    <div v-if="hasilSimpan.dilewati.length" class="mt-1 text-xs">
                        {{ hasilSimpan.dilewati.length }} dilewati:
                        <span v-for="(d, i) in hasilSimpan.dilewati.slice(0, 10)" :key="d.id">{{ i ? '; ' : '' }}{{ d.nama }} – {{ d.alasan }}</span>
                        <span v-if="hasilSimpan.dilewati.length > 10">; dan {{ hasilSimpan.dilewati.length - 10 }} lainnya</span>
                    </div>
                </Alert>

                <Card v-if="kelurahan.length" body-class="card-body card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Ringkasan · {{ judulHasil }}</h6></template>
                    <StatRow label="Total kelurahan/desa">{{ ringkasan.total }}</StatRow>
                    <StatRow label="Siap dibuat" value-class="text-green-700">{{ ringkasan.siap }}</StatRow>
                    <StatRow label="Sudah punya area" value-class="text-amber-700">{{ ringkasan.ada }}</StatRow>
                    <StatRow label="Tanpa batas resmi" value-class="text-gray-500">{{ ringkasan.tanpa }}</StatRow>
                    <StatRow label="Dipilih">{{ dipilih.size }} · {{ totalLuas.toLocaleString('id-ID', { maximumFractionDigits: 1 }) }} km²</StatRow>
                    <small v-if="ringkasan.tanpa" class="block mt-2 text-gray-500">
                        Kelurahan tanpa batas resmi (tidak berkode Kemendagri atau belum ada di dataset) bisa digambar manual di
                        <Link :href="kembaliUrl" class="text-blue-600 hover:underline">Design Area</Link>.
                    </small>
                </Card>
            </div>

            <div class="lg:col-span-8 flex flex-col gap-2">
                <Card body-class="card-body p-2">
                    <div ref="wadah" class="h-130 rounded-md"></div>
                    <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-600">
                        <span v-for="s in STATUS" :key="s.id" class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-sm border" :style="{ background: s.warnaPeta, borderColor: s.garisPeta }"></span>{{ s.label }}
                        </span>
                        <span>Klik polygon untuk memilih / membatalkan.</span>
                    </div>
                </Card>

                <Card body-class="p-0">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h6 class="mb-0 font-semibold">Daftar Kelurahan ({{ tampil.length }})</h6>
                            <Button
                                v-if="bisaTambah"
                                id="btn_simpan"
                                variant="success"
                                :loading="menyimpan"
                                :disabled="!dipilih.size || !form.warehouse_id"
                                :title="!form.warehouse_id ? 'Pilih depo dulu' : ''"
                                @click="simpan"
                            >
                                <Icon name="check" class="me-1" />Buat {{ dipilih.size }} Area
                            </Button>
                        </div>
                    </template>

                    <div class="card-body card-body-compact pb-2 grid grid-cols-1 sm:grid-cols-3 gap-2 items-center">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :model-value="semuaTerpilih" :indeterminate="sebagianTerpilih" :disabled="!bisaDipilihTampil.length" @update:model-value="pilihSemua" />
                            Pilih semua yang siap
                        </label>
                        <Input v-model="cari" type="search" placeholder="Cari kelurahan / kecamatan..." />
                        <Select v-model="saring">
                            <option value="">Semua status</option>
                            <option v-for="s in STATUS" :key="s.id" :value="s.id">{{ s.label }}</option>
                        </Select>
                    </div>

                    <div class="h-90 overflow-y-auto border-t border-gray-100">
                        <div class="list-group list-group-flush">
                            <div v-if="memuat" class="list-group-item text-center py-4"><span class="spinner me-2"></span>Memuat batas kelurahan...</div>
                            <div v-else-if="!kelurahan.length" class="list-group-item text-center text-gray-500 py-4">Pilih kota lalu klik "Tampilkan Kelurahan".</div>
                            <div v-else-if="!tampil.length" class="list-group-item text-center text-gray-500 py-4">Tidak ada kelurahan.</div>
                            <ItemKelurahan
                                v-for="k in tampil"
                                v-else
                                :key="k.id"
                                :kelurahan="k"
                                :terpilih="dipilih.has(k.id)"
                                :jarak="jarakKm(depoTerpilih, k)"
                                @pilih="togglePilih(k)"
                                @fokus="fokus(k)"
                            />
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
