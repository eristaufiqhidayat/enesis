import { ref } from 'vue';

// Status buka/tutup modal "Atur Muatan Motoris" (organisms/ModalTambahMuatan.vue, dipasang di AppLayout). Dibagi
// lewat state tingkat modul supaya tombol di mana pun (Topbar, header halaman) bisa membukanya tanpa pindah halaman.
const terbuka = ref(false);

export function useTambahMuatan() {
    return {
        terbuka,
        buka: () => { terbuka.value = true; },
        tutup: () => { terbuka.value = false; },
    };
}
