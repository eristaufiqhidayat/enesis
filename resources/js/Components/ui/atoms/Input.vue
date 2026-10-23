<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <input :type="type" :value="modelValue" class="form-control form-control-sm" @input="$emit('update:modelValue', $event.target.value)">
</template>
