<script setup>
import Card from '@/Components/ui/molecules/Card.vue';

// Placeholder abu-abu saat data form produk masih diambil (modal edit). Tata letaknya meniru FormProduk:
// kartu data di kiri, kartu harga dan foto di kanan.
const JUMLAH_FIELD = 6;
const JUMLAH_HARGA = 5;
</script>

<template>
    <div class="animate-pulse" aria-busy="true" aria-live="polite" role="status" aria-label="Memuat">
        <div class="grid grid-cols-1 gap-3 mb-3 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <template #header><div class="h-3 w-24 rounded bg-gray-200"></div></template>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div v-for="field in JUMLAH_FIELD" :key="field" class="space-y-1">
                        <div class="h-3 w-28 rounded bg-gray-200"></div>
                        <div class="h-8 rounded bg-gray-200"></div>
                    </div>
                </div>
            </Card>

            <div class="flex flex-col gap-3 min-w-0">
                <Card>
                    <template #header><div class="h-3 w-20 rounded bg-gray-200"></div></template>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="harga in JUMLAH_HARGA" :key="harga" class="space-y-1">
                            <div class="h-3 w-20 rounded bg-gray-200"></div>
                            <div class="h-8 rounded bg-gray-200"></div>
                        </div>
                    </div>
                </Card>

                <Card>
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
