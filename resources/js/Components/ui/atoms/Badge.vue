<script setup>
defineProps({ variant: { type: String, default: 'secondary' } });
</script>

<template>
    <span class="badge" :class="`badge-${variant}`"><slot /></span>
</template>
