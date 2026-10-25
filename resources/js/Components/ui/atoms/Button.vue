<script setup>
defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'sm' },
    // String tag HTML, atau komponen Inertia Link agar tombol navigasi tidak full reload.
    tag: { type: [String, Object], default: 'button' },
    type: { type: String, default: 'button' },
});
</script>

<template>
    <component :is="tag" :type="tag === 'button' ? type : undefined" class="btn" :class="[`btn-${size}`, `btn-${variant}`]">
        <slot />
    </component>
</template>
