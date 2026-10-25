<script setup>
const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'sm' },
    // String tag HTML, atau komponen Inertia Link agar tombol navigasi tidak full reload.
    tag: { type: [String, Object], default: 'button' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    // Tombol pengambil data: teks diganti spinner dan tombol dinonaktifkan selama proses berjalan.
    loading: { type: Boolean, default: false },
});
</script>

<template>
    <component
        :is="tag"
        :type="tag === 'button' ? type : undefined"
        :disabled="tag === 'button' && (disabled || loading) ? true : undefined"
        :aria-busy="loading ? 'true' : undefined"
        class="btn"
        :class="[`btn-${size}`, `btn-${variant}`, { 'btn-loading': loading }]"
    >
        <span class="btn-label" :class="{ 'btn-label-hidden': loading }"><slot /></span>
        <span v-if="loading" class="btn-spinner" role="status" aria-label="Memuat"></span>
    </component>
</template>
