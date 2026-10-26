<script setup>
// Baris label–nilai di dalam kartu ringkasan (mis. kartu depo). Label di kiri, nilai tebal di kanan.
defineProps({
    label: { type: String, default: null },
    valueClass: { type: String, default: 'text-gray-900' },
});
</script>

<template>
    <div class="stat-row">
        <span class="text-gray-600"><slot name="label">{{ label }}</slot></span>
        <span class="font-semibold" :class="valueClass"><slot /></span>
    </div>
</template>
