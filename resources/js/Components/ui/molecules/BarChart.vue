<script setup>
import { computed } from 'vue';
import { angkaRingkas } from '@/Support/format.js';

// Grafik batang vertikal sederhana (SVG, tanpa pustaka), sama dengan teknik yang dipakai modal "Grafik Per
// Depo" di Motoris/SummaryDepo.vue — diekstrak jadi komponen supaya dipakai ulang (Beranda: Depo Teratas &
// Funnel Outlet). Setiap batang boleh punya warna sendiri (barClass) untuk funnel yang warnanya bertahap.
const props = defineProps({
    // { label, value, barClass? }
    items: { type: Array, required: true },
    // Tampilkan nilai sebagai persen (mis. achievement) atau angka biasa.
    suffix: { type: String, default: '' },
    desimal: { type: Number, default: 1 },
    barClass: { type: String, default: 'fill-green-400 stroke-green-500' },
    height: { type: Number, default: 220 },
});

const LEBAR_BATANG = 44;
const JARAK = 18;
const ATAS = 20;
const BAWAH = 70;
const KIRI = 8;

const tinggiGrafik = computed(() => props.height + ATAS + BAWAH);
const lebarGrafik = computed(() => KIRI + props.items.length * (LEBAR_BATANG + JARAK));

const batang = computed(() => {
    const maks = Math.max(1, ...props.items.map((d) => Number(d.value) || 0));

    return props.items.map((d, i) => {
        const v = Number(d.value) || 0;
        const h = Math.round((v / maks) * props.height);

        return {
            label: d.label, v, barClass: d.barClass ?? props.barClass,
            x: KIRI + i * (LEBAR_BATANG + JARAK), y: ATAS + props.height - h, h,
        };
    });
});
</script>

<template>
    <div class="overflow-x-auto">
        <svg v-if="batang.length" :width="lebarGrafik" :height="tinggiGrafik" role="img" aria-label="Grafik batang">
            <line :x1="0" :x2="lebarGrafik" :y1="ATAS + height" :y2="ATAS + height" class="stroke-gray-300" />
            <g v-for="b in batang" :key="b.label">
                <rect :x="b.x" :y="b.y" :width="LEBAR_BATANG" :height="Math.max(b.h, 1)" rx="3" :class="b.barClass">
                    <title>{{ b.label }}: {{ angkaRingkas(b.v, desimal) }}{{ suffix }}</title>
                </rect>
                <text :x="b.x + LEBAR_BATANG / 2" :y="b.y - 6" text-anchor="middle" font-size="10" font-weight="600" class="fill-gray-700">
                    {{ angkaRingkas(b.v, desimal) }}{{ suffix }}
                </text>
                <text
                    :x="b.x + LEBAR_BATANG / 2" :y="ATAS + height + 12" text-anchor="end" font-size="10" class="fill-gray-600"
                    :transform="`rotate(-35 ${b.x + LEBAR_BATANG / 2} ${ATAS + height + 12})`"
                >{{ b.label }}</text>
            </g>
        </svg>
        <p v-else class="text-gray-500 text-sm">Tidak ada data.</p>
    </div>
</template>
