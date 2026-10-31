<script setup>
import { computed, ref } from 'vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import { angkaRingkas, tanggalPendek } from '@/Support/format.js';

// Grafik garis tren harian (SVG, tanpa pustaka, sama teknik dengan BarChart) untuk Beranda: Total Sales (Rp)
// atau Total EC per tanggal dalam rentang yang sedang difilter. Metrik dipilih lewat Select, seperti grafik
// per depo di Motoris/SummaryDepo.vue.
const props = defineProps({
    // [{ tanggal, sales, ec }]
    data: { type: Array, required: true },
});

const METRIK = { sales: 'Total Sales (Rp)', ec: 'Total EC' };
const opsiMetrik = Object.entries(METRIK).map(([value, label]) => ({ value, label }));
const metrik = ref('sales');

const LEBAR = 720, TINGGI = 220, ATAS = 20, BAWAH = 36, KIRI = 48, KANAN = 12;

const titik = computed(() => {
    const nilai = props.data.map((d) => Number(d[metrik.value]) || 0);
    const maks = Math.max(1, ...nilai);
    const n = Math.max(props.data.length - 1, 1);
    const lebarGrafik = LEBAR - KIRI - KANAN;

    return props.data.map((d, i) => {
        const v = Number(d[metrik.value]) || 0;

        return {
            tanggal: d.tanggal, v,
            x: KIRI + (i / n) * lebarGrafik,
            y: ATAS + TINGGI - (v / maks) * TINGGI,
        };
    });
});

const garis = computed(() => titik.value.map((t) => `${t.x},${t.y}`).join(' '));
const area = computed(() => `${KIRI},${ATAS + TINGGI} ${garis.value} ${titik.value.at(-1)?.x ?? KIRI},${ATAS + TINGGI}`);
const maksLabel = computed(() => Math.max(1, ...props.data.map((d) => Number(d[metrik.value]) || 0)));
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <Label for="metrik-tren" class="mb-0">Metrik</Label>
            <Select id="metrik-tren" v-model="metrik" :options="opsiMetrik" aria-label="Metrik tren harian" class="w-48" />
        </div>
        <div class="overflow-x-auto">
            <svg v-if="titik.length" :width="LEBAR" :height="ATAS + TINGGI + BAWAH" role="img" :aria-label="`${METRIK[metrik]} per hari`">
                <line :x1="KIRI" :x2="LEBAR - KANAN" :y1="ATAS + TINGGI" :y2="ATAS + TINGGI" class="stroke-gray-300" />
                <line :x1="KIRI" :x2="LEBAR - KANAN" :y1="ATAS" :y2="ATAS" class="stroke-gray-200" stroke-dasharray="4" />
                <text :x="KIRI - 6" :y="ATAS + 4" text-anchor="end" font-size="10" class="fill-gray-500">{{ angkaRingkas(maksLabel) }}</text>
                <text :x="KIRI - 6" :y="ATAS + TINGGI + 4" text-anchor="end" font-size="10" class="fill-gray-500">0</text>
                <polygon :points="area" class="fill-sky-400/15" />
                <polyline :points="garis" fill="none" class="stroke-sky-500" stroke-width="2" />
                <g v-for="t in titik" :key="t.tanggal">
                    <circle :cx="t.x" :cy="t.y" r="3" class="fill-sky-500">
                        <title>{{ tanggalPendek(t.tanggal) }}: {{ angkaRingkas(t.v) }}</title>
                    </circle>
                    <text :x="t.x" :y="ATAS + TINGGI + 16" text-anchor="middle" font-size="9" class="fill-gray-500">{{ tanggalPendek(t.tanggal).slice(0, 5) }}</text>
                </g>
            </svg>
            <p v-else class="text-gray-500 text-sm">Tidak ada data pada rentang ini.</p>
        </div>
    </div>
</template>
