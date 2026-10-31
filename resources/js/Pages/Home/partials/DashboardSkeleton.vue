<script setup>
import Card from '@/Components/ui/molecules/Card.vue';

// Placeholder Beranda saat kpi/funnel/tren/topDepo masih diambil (lihat <Deferred> di Home/Dashboard.vue).
</script>

<template>
    <div class="animate-pulse" aria-busy="true" aria-live="polite">
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 mb-4">
            <Card v-for="kartu in 6" :key="kartu" class="shadow-sm" body-class="py-4 text-center">
                <div class="h-3 w-2/3 mx-auto rounded bg-gray-200 mb-2"></div>
                <div class="h-5 w-1/2 mx-auto rounded bg-gray-300"></div>
            </Card>
        </div>
        <div class="grid gap-3 lg:grid-cols-2">
            <Card body-class="p-4"><div class="h-48 rounded bg-gray-200"></div></Card>
            <Card body-class="p-4"><div class="h-48 rounded bg-gray-200"></div></Card>
        </div>
    </div>
</template>
