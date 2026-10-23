<script setup>
defineProps({ variant: { type: String, default: 'info' } });
</script>

<template>
    <div class="alert" :class="`alert-${variant}`"><slot /></div>
</template>
