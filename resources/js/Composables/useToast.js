import { ref } from 'vue';

// Antrean toast bersama (state tingkat modul), ditampilkan oleh molecules/Flash.vue yang dipasang di AppLayout.
// Flash mengisinya dari pesan flash server; komponen lain memakai toast.sukses()/toast.galat() untuk aksi
// yang tidak berakhir dengan redirect (mis. simpan lewat fetch).
const DURASI_SUKSES = 5000;

const daftar = ref([]);
const timer = new Map();
let nomor = 0;

function tutup(id) {
    clearTimeout(timer.get(id));
    timer.delete(id);
    daftar.value = daftar.value.filter((toast) => toast.id !== id);
}

// Sukses hilang sendiri setelah beberapa detik; galat tetap sampai ditutup.
function tampilkan(variant, pesan) {
    if (!pesan) return;

    const id = ++nomor;
    daftar.value.push({ id, variant, pesan });
    if (variant === 'success') timer.set(id, setTimeout(() => tutup(id), DURASI_SUKSES));
}

export function useToast() {
    return {
        daftar,
        tutup,
        sukses: (pesan) => tampilkan('success', pesan),
        galat: (pesan) => tampilkan('danger', pesan),
    };
}
