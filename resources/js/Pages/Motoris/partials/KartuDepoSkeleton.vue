<script setup>
import Card from '@/Components/ui/molecules/Card.vue';

// Placeholder kartu depo saat data kartu masih diambil (lihat <Deferred> di Motoris/SummaryDepo.vue).
const JUMLAH_KARTU = 6;
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 animate-pulse" aria-busy="true" aria-live="polite">
        <Card v-for="kartu in JUMLAH_KARTU" :key="kartu" class="shadow-sm text-xs" header-class="card-header-primary" body-class="px-3 pb-1">
            <template #header>
                <div class="h-4 w-3/4 rounded bg-white/40"></div>
            </template>
            <div class="my-2 space-y-2">
                <div v-for="baris in 8" :key="baris" class="h-3 rounded bg-gray-200"></div>
            </div>
        </Card>
    </div>
</template>
