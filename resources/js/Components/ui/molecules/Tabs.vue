<script setup>
// Navigasi tab generik. Pemilihan tab dikirim lewat v-model (update:modelValue); pindah halaman diatur pemanggil.
defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="mb-3 overflow-x-auto overflow-y-hidden">
        <nav class="flex gap-2 min-w-max" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="tab.key === modelValue"
                class="tab-pill"
                :class="{ active: tab.key === modelValue }"
                @click="$emit('update:modelValue', tab.key)"
            >
                {{ tab.label }}
            </button>
        </nav>
    </div>
</template>
