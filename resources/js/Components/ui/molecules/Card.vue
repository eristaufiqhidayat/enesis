<script setup>
defineProps({
    bodyClass: { type: String, default: 'card-body' },
    headerClass: { type: String, default: 'card-header-compact bg-gray-50' },
});
</script>

<template>
    <div class="card">
        <div v-if="$slots.header" class="card-header" :class="headerClass"><slot name="header" /></div>
        <div :class="bodyClass"><slot /></div>
        <div v-if="$slots.footer" class="card-footer"><slot name="footer" /></div>
    </div>
</template>
