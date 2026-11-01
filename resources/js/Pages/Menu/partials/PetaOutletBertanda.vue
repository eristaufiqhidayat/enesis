<script setup>
import { computed, onMounted, ref } from 'vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Skeleton from '@/Components/ui/atoms/Skeleton.vue';
import { BATAS_DETEKSI, bacaDeteksi, simpanDeteksi } from '@/Support/deteksiKunjungan.js';
import { jarakSingkat, tanggalSingkat } from '@/Support/format.js';
import DetailDepoPeta from './DetailDepoPeta.vue';

// Peta satu outlet bertanda beserta outlet bertanda lain di sekitarnya (TandaMotorisController::outletTerdekat):
// konteks bagi koordinator, mis. beberapa outlet fiktif berdekatan. Titik utama oranye, outlet sekitarnya merah.
// Radius = ambang "jarak dekat" yang sama dengan modal Riwayat (localStorage browser ini); bisa diubah dari sini.
const props = defineProps({
    // Url endpoint terdekat dan outlet acuan: { id, nama, latitude, longitude }.
    url: { type: String, required: true },
    outlet: { type: Object, required: true },
});

const sekitar = ref([]);
const radius = ref(bacaDeteksi().jarak_dekat_meter);
const form = ref(radius.value);
const memuat = ref(true);
const pesan = ref(null);
const info = ref(null);
// true selama pengambilan ulang akibat Terapkan (spinner di tombol); muat awal tidak ikut.
const menerapkan = ref(false);

const { min, maks } = BATAS_DETEKSI.jarak_dekat_meter;
const sah = computed(() => Number.isInteger(form.value) && form.value >= min && form.value <= maks);
const berubah = computed(() => form.value !== radius.value);

async function muat() {
    memuat.value = true;
    pesan.value = null;

    try {
        const respon = await fetch(`${props.url}?${new URLSearchParams({ outlet_id: props.outlet.id, radius_m: radius.value })}`, { headers: { Accept: 'application/json' } });
        if (!respon.ok) throw new Error(`HTTP ${respon.status}`);
        const data = await respon.json();
        sekitar.value = data.sekitar ?? [];
    } catch {
        pesan.value = 'Gagal memuat outlet bertanda di sekitar.';
    } finally {
        memuat.value = false;
    }
}

async function terapkan() {
    if (!sah.value) return;

    const { nilai, tersimpan } = simpanDeteksi({ ...bacaDeteksi(), jarak_dekat_meter: form.value });
    radius.value = nilai.jarak_dekat_meter;
    info.value = tersimpan ? null : 'Browser ini menolak penyimpanan lokal, jadi radius hanya berlaku sampai halaman dimuat ulang.';
    menerapkan.value = true;
    await muat();
    menerapkan.value = false;
}

onMounted(muat);
</script>

<template>
    <div class="space-y-3">
        <Skeleton v-if="memuat" class="h-64 w-full" />
        <DetailDepoPeta v-else :key="radius" :depo="outlet" ikon="outlet" :sekitar="sekitar" google-map />

        <form class="tanda-motoris-form" aria-label="Radius outlet bertanda di sekitar" @submit.prevent="terapkan">
            <Label for="radius_sekitar">Radius pencarian (meter)</Label>
            <div class="flex flex-wrap items-center gap-2">
                <Input id="radius_sekitar" v-model.number="form" type="number" :min="min" :max="maks" class="w-28" />
                <Button type="submit" :loading="menerapkan" :disabled="!sah || !berubah || memuat">Terapkan</Button>
            </div>
            <FieldError v-if="!sah">Isi bilangan bulat {{ min }}–{{ maks }} meter.</FieldError>
            <p class="tanda-item-meta mb-0">Sama dengan ambang jarak dekat di modal Riwayat. Disimpan di browser ini saja.</p>
            <p v-if="info" class="mb-0 text-xs text-amber-700" role="status">{{ info }}</p>
        </form>

        <p v-if="pesan" class="mb-0 text-xs text-red-600">{{ pesan }}</p>

        <section v-if="!memuat && !pesan" aria-label="Outlet bertanda di sekitar">
            <div class="tanda-item-kepala">
                <h6 class="mb-0 font-semibold">Outlet bertanda di sekitar</h6>
                <Badge v-if="sekitar.length" variant="primary">{{ sekitar.length }}</Badge>
                <span class="tanda-item-meta">radius {{ jarakSingkat(radius) }}</span>
            </div>
            <ul v-if="sekitar.length" class="tanda-motoris-daftar mt-2">
                <li v-for="o in sekitar" :key="o.outlet_id" class="tanda-motoris-item">
                    <div class="tanda-item-kepala">
                        <Badge variant="danger">{{ o.kategori_label }}</Badge>
                        <span class="tanda-item-judul">{{ o.outlet }}</span>
                        <span class="tanda-item-meta">{{ jarakSingkat(o.jarak_m) }} dari outlet ini</span>
                    </div>
                    <p class="tanda-item-meta mb-0 mt-1">
                        <template v-if="o.jumlah_tanda > 1">{{ o.jumlah_tanda }} tanda · </template>Ditandai {{ tanggalSingkat(o.ditandai_pada) }}
                    </p>
                </li>
            </ul>
            <p v-else class="tanda-kosong mt-2">Tidak ada outlet bertanda lain di sekitar.</p>
        </section>
    </div>
</template>
