<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// Modal yang sedang terbuka, paling atas di akhir. Esc hanya menutup modal paling atas (mis. Edit di atas drawer).
const tumpukan = [];

// Dialog konfirmasi (padanan molecule Blade modal). Judul & isi lewat props/slot, tombol aksi lewat slot footer.
const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    // 'md' (bawaan, lihat .modal-content) atau 'xl' (.modal-content-xl - tabel lebar, scroll sendiri, maks 92vh).
    size: { type: String, default: 'md' },
    // 'tengah' (bawaan) atau 'kanan': drawer setinggi layar yang geser dari kanan (.modal-drawer). Prop size diabaikan.
    placement: { type: String, default: 'tengah' },
});

const emit = defineEmits(['close']);

const kanan = computed(() => props.placement === 'kanan');

// Drawer keluar lewat animasi CSS (.modal-drawer-tutup); 'close' baru dikirim setelah animasi selesai.
const sedangTutup = ref(false);

function mintaTutup() {
    if (!kanan.value) {
        emit('close');

        return;
    }

    sedangTutup.value = true;
}

function selesaiTutup(e) {
    if (e.target !== e.currentTarget || !sedangTutup.value) return;

    sedangTutup.value = false;
    emit('close');
}

const ID = Symbol('modal');

function keluarDariTumpukan() {
    const indeks = tumpukan.indexOf(ID);

    if (indeks !== -1) tumpukan.splice(indeks, 1);
}

watch(() => props.show, (terbuka) => {
    if (terbuka) {
        tumpukan.push(ID);
    } else {
        keluarDariTumpukan();
    }
}, { immediate: true });

// Esc ditangkap di document: fokus biasanya tetap di tombol pemicu di luar dialog.
function tutupDenganEsc(e) {
    if (props.show && e.key === 'Escape' && tumpukan.at(-1) === ID) mintaTutup();
}

onMounted(() => document.addEventListener('keydown', tutupDenganEsc));
onUnmounted(() => {
    document.removeEventListener('keydown', tutupDenganEsc);
    keluarDariTumpukan();
});
</script>

<template>
    <Transition name="modal-backdrop">
        <div v-if="show" class="modal-container" :class="{ 'modal-container-kanan': kanan }" @click.self="mintaTutup">
            <!-- Drawer tidak memakai transisi Vue (:css="false"): masuk & keluarnya animasi CSS sendiri. -->
            <Transition :name="kanan ? 'modal-drawer' : 'modal-content'" :css="!kanan">
                <div
                    :class="kanan ? ['modal-drawer', { 'modal-drawer-tutup': sedangTutup }] : ['modal-content', { 'modal-content-xl': size === 'xl' }]"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    @animationend="kanan && selesaiTutup($event)"
                >
                    <div class="modal-header">
                        <h6 class="mb-0 font-semibold">{{ title }}</h6>
                        <button type="button" class="btn-close" aria-label="Tutup" @click="mintaTutup">&times;</button>
                    </div>
                    <div class="modal-body"><slot /></div>
                    <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
