<script setup>
// options: { id: label } atau [{ value, label }]; kalau tidak diisi, dropdown dirender dari slot <option> manual.
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: [Object, Array], default: null },
    placeholder: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const daftar = computed(() => {
    if (!props.options) return null;
    return Array.isArray(props.options)
        ? props.options
        : Object.entries(props.options).map(([value, label]) => ({ value, label }));
});
</script>

<template>
    <select :value="modelValue ?? ''" class="form-control form-control-sm" @change="$emit('update:modelValue', $event.target.value)">
        <template v-if="daftar">
            <option v-if="placeholder !== null" value="">{{ placeholder }}</option>
            <option v-for="item in daftar" :key="item.value" :value="String(item.value)">{{ item.label }}</option>
        </template>
        <slot v-else />
    </select>
</template>
