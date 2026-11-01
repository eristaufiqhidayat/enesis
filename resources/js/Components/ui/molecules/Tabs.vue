<script setup>
// Navigasi tab generik. Pemilihan tab dikirim lewat v-model (update:modelValue); pindah halaman diatur pemanggil.
defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, default: null },
    // true = jarak bawah kecil (mb-1), untuk tab di dalam panel padat seperti daftar kunjungan.
    rapat: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="overflow-x-auto overflow-y-hidden" :class="rapat ? 'mb-1' : 'mb-3'">
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
