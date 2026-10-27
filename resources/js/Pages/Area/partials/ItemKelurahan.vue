<script setup>
// Satu baris di Daftar Kelurahan: kotak pilih, nama + kecamatan + luas/jarak, dan status.
import { computed } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import { STATUS, statusKelurahan, warnaKecamatan } from '@/Pages/Area/partials/kelurahan.js';

const props = defineProps({
    kelurahan: { type: Object, required: true },
    terpilih: { type: Boolean, default: false },
    jarak: { type: Number, default: null },
});
defineEmits(['pilih', 'fokus']);

const status = computed(() => STATUS.find((s) => s.id === statusKelurahan(props.kelurahan)));
const angka = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 1 });
const keterangan = computed(() => {
    const k = props.kelurahan;
    return [
        'Kec. ' + k.kecamatan,
        k.luas_km2 !== null ? angka(k.luas_km2) + ' km²' : null,
        props.jarak !== null ? angka(props.jarak) + ' km dari depo' : null,
    ].filter(Boolean).join(' · ');
});
</script>

<template>
    <div class="list-group-item list-group-item-action py-1.5 flex items-center gap-2 cursor-pointer" @click="$emit('fokus')">
        <Checkbox
            :model-value="terpilih"
            :disabled="status.id !== 'siap'"
            :aria-label="`Pilih ${kelurahan.nama}`"
            @click.stop
            @update:model-value="$emit('pilih')"
        />
        <span
            class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
            :style="{ background: status.id === 'siap' ? warnaKecamatan(kelurahan.kecamatan) : status.warnaPeta }"
        ></span>
        <div class="grow min-w-0">
            <div class="font-semibold truncate">{{ kelurahan.nama }}</div>
            <div class="text-xs text-gray-500 truncate">{{ keterangan }}</div>
        </div>
        <Badge :variant="status.badge" class="shrink-0">
            {{ status.id === 'ada' ? kelurahan.area[0].code : status.label }}
        </Badge>
    </div>
</template>
