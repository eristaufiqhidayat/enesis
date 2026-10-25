<script setup>
import { onMounted, onUnmounted } from 'vue';

// Dialog konfirmasi (padanan molecule Blade modal). Judul & isi lewat props/slot, tombol aksi lewat slot footer.
const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
});

const emit = defineEmits(['close']);

// Esc ditangkap di document: fokus biasanya tetap di tombol pemicu di luar dialog.
function tutupDenganEsc(e) {
    if (props.show && e.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', tutupDenganEsc));
onUnmounted(() => document.removeEventListener('keydown', tutupDenganEsc));
</script>

<template>
    <Transition name="modal-backdrop">
        <div v-if="show" class="modal-container" @click.self="emit('close')">
            <Transition name="modal-content">
                <div class="modal-content" role="dialog" aria-modal="true" :aria-label="title">
                    <div class="modal-header">
                        <h6 class="mb-0 font-semibold">{{ title }}</h6>
                        <button type="button" class="btn-close" aria-label="Tutup" @click="emit('close')">&times;</button>
                    </div>
                    <div class="modal-body"><slot /></div>
                    <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
