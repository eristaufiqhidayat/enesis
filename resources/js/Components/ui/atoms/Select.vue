<script setup>
defineProps({ modelValue: { type: [String, Number], default: '' } });

defineEmits(['update:modelValue']);
</script>

<template>
    <select class="form-select form-select-sm" :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><slot /></select>
</template>
