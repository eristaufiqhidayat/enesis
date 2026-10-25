<script setup>
defineProps({ modelValue: { type: String, default: '' } });

defineEmits(['update:modelValue']);
</script>

<template>
    <textarea class="form-control form-control-sm" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"></textarea>
</template>
