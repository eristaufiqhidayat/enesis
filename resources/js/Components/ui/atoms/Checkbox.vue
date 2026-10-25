<script setup>
import { computed, ref, watchEffect } from 'vue';

// modelValue boolean untuk satu kotak; array nilai untuk kotak dalam grup (padanan checkbox[] di form).
const props = defineProps({
    modelValue: { type: [Boolean, Array], default: false },
    value: { type: [String, Number], default: null },
    indeterminate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const kotak = ref(null);
const checked = computed(() => Array.isArray(props.modelValue) ? props.modelValue.includes(props.value) : props.modelValue);

function ubah(e) {
    if (!Array.isArray(props.modelValue)) {
        emit('update:modelValue', e.target.checked);
        return;
    }

    emit('update:modelValue', e.target.checked
        ? [...props.modelValue, props.value]
        : props.modelValue.filter((nilai) => nilai !== props.value));
}

watchEffect(() => {
    if (kotak.value) kotak.value.indeterminate = props.indeterminate;
});
</script>

<template>
    <input ref="kotak" type="checkbox" class="form-check-input" :checked="checked" @change="ubah">
</template>
