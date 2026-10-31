<script setup>
import { computed } from 'vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import BarChart from '@/Components/ui/molecules/BarChart.vue';
import { angka } from '@/Support/format.js';

// Funnel outlet Beranda: NOO → AC → EC → RO (definisi sama dengan App\Support\Menu\Motoris\SumberOutletStatus,
// lihat Dashboard::funnelOutlet). OSS bukan tahap funnel (bukan subset berurutan dari NOO/AC/EC/RO), jadi
// ditampilkan terpisah sebagai peringatan kualitas kunjungan, bukan sebagai batang funnel.
const props = defineProps({
    // [{ key, label, jumlah }], urutan: noo, ac, ec, ro, oss
    funnel: { type: Array, required: true },
});

const WARNA = { noo: 'fill-sky-400 stroke-sky-500', ac: 'fill-amber-400 stroke-amber-500', ec: 'fill-green-400 stroke-green-500', ro: 'fill-violet-400 stroke-violet-500' };

const tahapFunnel = computed(() => props.funnel.filter((f) => f.key !== 'oss')
    .map((f) => ({ label: f.label.replace(/ \(.+\)$/, ''), value: f.jumlah, barClass: WARNA[f.key] })));

const oss = computed(() => props.funnel.find((f) => f.key === 'oss') ?? null);
</script>

<template>
    <div>
        <BarChart :items="tahapFunnel" :height="200" :desimal="0" />
        <Alert v-if="oss" variant="warning" class="mt-3 flex items-start gap-2">
            <Icon name="flag" class="w-4 h-4 shrink-0 mt-0.5" />
            <span>
                <b>{{ angka(oss.jumlah) }} outlet</b> berstatus <b>OSS</b> — kunjungan terakhirnya tidak tercatat punya stok produk sendiri.
                Perlu ditindaklanjuti supaya tidak kehilangan repeat order.
            </span>
        </Alert>
    </div>
</template>
