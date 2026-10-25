<script setup>
// Dropdown pilihan, pasangan Vue dari atom Blade x-ui.atoms.select.
// options: { id: label } atau [{ value, label }]; placeholder = pilihan kosong ("semua").
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: [Object, Array], default: () => ({}) },
    placeholder: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const daftar = computed(() => Array.isArray(props.options)
    ? props.options
    : Object.entries(props.options).map(([value, label]) => ({ value, label })));
</script>

<template>
    <select :value="modelValue ?? ''" class="form-control form-control-sm" @change="$emit('update:modelValue', $event.target.value)">
        <option v-if="placeholder !== null" value="">{{ placeholder }}</option>
        <option v-for="item in daftar" :key="item.value" :value="String(item.value)">{{ item.label }}</option>
    </select>
</template>
