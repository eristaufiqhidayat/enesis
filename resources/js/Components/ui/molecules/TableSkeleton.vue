<script setup>
// Placeholder abu-abu saat data tabel masih diambil (lihat <Deferred> di Menu/Show.vue).
// Kolom tabel belum diketahui selagi data ditunda, jadi jumlah kolom dan baris dibuat generik.
const JUMLAH_KOLOM = 4;
const JUMLAH_BARIS = 5;
</script>

<template>
    <div class="overflow-x-auto animate-pulse" aria-busy="true" aria-live="polite">
        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="kolom in JUMLAH_KOLOM" :key="kolom"><div class="h-3 w-24 rounded bg-gray-200"></div></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="baris in JUMLAH_BARIS" :key="baris">
                    <td v-for="kolom in JUMLAH_KOLOM" :key="kolom"><div class="h-3 rounded bg-gray-200"></div></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
