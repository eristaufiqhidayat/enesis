import { router } from '@inertiajs/vue3';

// Handler sort & filter per kolom untuk halaman dengan satu <DataTable> langsung (tanpa namespace per tabel,
// beda dengan Menu/Show.vue yang bisa menampilkan beberapa tabel sekaligus di satu halaman, lihat
// ubahQueryTabel() di sana). Query string lain yang sudah ada di URL (filter tanggal/depo/dst.) tetap dipertahankan.
// Mengubah sort atau filter mengembalikan tabel ke halaman 1.
function ubahQuery(ubah) {
    const query = Object.fromEntries(new URLSearchParams(window.location.search));
    delete query.page;

    for (const kunci of Object.keys(query)) {
        if (kunci === 'sort' || kunci === 'arah' || kunci.startsWith('filter[')) {
            delete query[kunci];
        }
    }

    ubah(query);
    router.get(window.location.pathname, query, { preserveScroll: true, preserveState: true });
}

export function urutkanTabel({ kolom, arah }) {
    ubahQuery((query) => {
        if (arah) {
            query.sort = kolom;
            query.arah = arah;
        }
    });
}

export function filterTabelTeks({ nilai }) {
    ubahQuery((query) => {
        for (const [kolom, teks] of Object.entries(nilai)) {
            if (teks) {
                query[`filter[${kolom}]`] = teks;
            }
        }
    });
}
