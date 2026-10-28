<script setup>
import { computed } from 'vue';
import Card from '@/Components/ui/molecules/Card.vue';

// Placeholder abu-abu saat form master (modal tambah/edit: master produk, karyawan) masih diambil:
// kartu data di kiri; kartu harga dan/atau foto di kanan hanya bila menu punya field itu.
const props = defineProps({
    harga: { type: Boolean, default: false },
    foto: { type: Boolean, default: false },
    jumlahField: { type: Number, default: 4 },
});

const JUMLAH_HARGA = 5;
const adaSisiKanan = computed(() => props.harga || props.foto);
</script>

<template>
    <div class="animate-pulse" aria-busy="true" aria-live="polite" role="status" aria-label="Memuat">
        <div class="grid grid-cols-1 gap-3 mb-3" :class="{ 'lg:grid-cols-3': adaSisiKanan }">
            <Card :class="{ 'lg:col-span-2': adaSisiKanan }">
                <template #header><div class="h-3 w-24 rounded bg-gray-200"></div></template>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div v-for="field in jumlahField" :key="field" class="space-y-1">
                        <div class="h-3 w-28 rounded bg-gray-200"></div>
                        <div class="h-8 rounded bg-gray-200"></div>
                    </div>
                </div>
            </Card>

            <div v-if="adaSisiKanan" class="flex flex-col gap-3 min-w-0">
                <Card v-if="harga">
                    <template #header><div class="h-3 w-20 rounded bg-gray-200"></div></template>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="item in JUMLAH_HARGA" :key="item" class="space-y-1">
                            <div class="h-3 w-20 rounded bg-gray-200"></div>
                            <div class="h-8 rounded bg-gray-200"></div>
                        </div>
                    </div>
                </Card>

                <Card v-if="foto">
                    <template #header><div class="h-3 w-12 rounded bg-gray-200"></div></template>
                    <div class="flex items-center gap-3">
                        <div class="h-24 w-24 rounded bg-gray-200"></div>
                        <div class="h-8 flex-1 rounded bg-gray-200"></div>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>
