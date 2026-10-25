<script setup>
defineProps({
    modelValue: { type: [String, Number], default: null },
    value: { type: [String, Number], required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <input type="radio" class="form-check-input" :checked="modelValue === value" @change="$emit('update:modelValue', value)">
</template>
