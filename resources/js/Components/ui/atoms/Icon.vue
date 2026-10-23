<script setup>
defineProps({ name: { type: String, required: true } });
</script>

<template>
    <i :class="`fas fa-${name}`"></i>
</template>
