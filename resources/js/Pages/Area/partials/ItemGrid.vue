<script setup>
// Satu baris di Daftar Kotak (Auto Grid): kotak pilih, nama + kelurahan yang dicakup + luas, dan status.
import { computed } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import { warnaKecamatan } from '@/Pages/Area/partials/kelurahan.js';

const props = defineProps({
    unit: { type: Object, required: true },
    terpilih: { type: Boolean, default: false },
});
defineEmits(['pilih', 'fokus']);

const tumpang = computed(() => !!props.unit.area);
const angka = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const keterangan = computed(() => {
    const [utama, ...lain] = props.unit.kelurahan;
    return 'Kec. ' + utama.kecamatan + ' · ' + angka(props.unit.luas_km2) + ' km²'
        + (lain.length ? ' · +' + lain.length + ' kelurahan lain' : '');
});
</script>

<template>
    <div class="list-group-item list-group-item-action py-1.5 flex items-center gap-2 cursor-pointer" @click="$emit('fokus')">
        <Checkbox
            :model-value="terpilih"
            :disabled="tumpang"
            :aria-label="`Pilih ${unit.nama}`"
            @click.stop
            @update:model-value="$emit('pilih')"
        />
        <span
            class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
            :style="{ background: tumpang ? '#fde68a' : warnaKecamatan(unit.kelurahan[0].kecamatan) }"
        ></span>
        <div class="grow min-w-0">
            <div class="font-semibold truncate">{{ unit.nama }}</div>
            <div class="text-xs text-gray-500 truncate">{{ keterangan }}</div>
        </div>
        <Badge :variant="tumpang ? 'warning' : 'success'" class="shrink-0">{{ tumpang ? unit.area.code : 'Siap' }}</Badge>
    </div>
</template>
