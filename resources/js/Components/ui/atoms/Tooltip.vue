<script setup>
defineProps({
    label: { type: String, required: true },
    position: { type: String, default: 'bottom' },
    align: { type: String, default: 'center' },
});
</script>

<template>
    <span class="tooltip-wrapper" :class="[`tooltip-${position}`, `tooltip-align-${align}`]" :data-tooltip="label">
        <slot />
    </span>
</template>
