<script setup>
// Navigasi tab generik. Pemilihan tab dikirim lewat v-model (update:modelValue); pindah halaman diatur pemanggil.
defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="border-b border-gray-200 mb-3 overflow-x-auto overflow-y-hidden">
        <nav class="flex gap-1 min-w-max" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="tab.key === modelValue"
                class="px-3 py-2 text-sm border-b-2 -mb-px cursor-pointer"
                :class="tab.key === modelValue
                    ? 'border-blue-600 text-blue-600 font-semibold'
                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="$emit('update:modelValue', tab.key)"
            >
                {{ tab.label }}
            </button>
        </nav>
    </div>
</template>
