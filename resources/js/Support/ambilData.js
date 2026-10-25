import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { bersihkanQuery } from '@/Support/query.js';

// Status tombol pengambil data (Tampilkan / Cari): spinner selama request berjalan, lalu nonaktif sampai
// isian filter berbeda dari filter yang sedang tampil, supaya user tahu kapan perlu klik lagi.
// `form` = isian filter saat ini, `diterapkan` = filter yang sedang tampil (dari props server); keduanya fungsi
// yang mengembalikan objek dengan bentuk yang sama.
export function useAmbilData({ form, diterapkan }) {
    const memuat = ref(false);
    // Pilihan ganda dibandingkan tanpa memperhatikan urutan, dan semua nilai dibaca sebagai string.
    const normal = (nilai) => (Array.isArray(nilai) ? nilai.map(String).sort() : String(nilai));
    const kunci = (ambil) => JSON.stringify(Object.entries(bersihkanQuery(ambil())).map(([k, v]) => [k, normal(v)]).sort());
    const berubah = computed(() => kunci(form) !== kunci(diterapkan));

    // Bungkus router.get: hanya kunjungan ini yang menyalakan spinner (pindah tab dan pagination tidak).
    function ambil(url, data, opsi = {}) {
        router.get(url, data, {
            ...opsi,
            onStart: (kunjungan) => { memuat.value = true; opsi.onStart?.(kunjungan); },
            onFinish: (kunjungan) => { memuat.value = false; opsi.onFinish?.(kunjungan); },
        });
    }

    return { memuat, berubah, ambil };
}
