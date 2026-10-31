<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import DatePicker from '@/Components/ui/atoms/DatePicker.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angkaRingkas, tanggalSingkat } from '@/Support/format.js';

// Form tambah muatan canvassing dari web, di dalam modal tabel barang_canvassing (lihat Menu/Show.vue).
// Muatan berstatus Disiapkan; motoris menerimanya di aplikasi HP. Depo, motoris, dan produk dimuat dari
// GET /barang_canvassing/opsi; simpan ke POST /barang_canvassing. Wizard dua langkah: 1) tanggal, depo, motoris;
// 2) item muatan. Tombol navigasi ada di footer Modal (Show.vue): ia membaca 'langkah' dan 'sibuk' dari emit,
// memanggil lanjut()/kembali() lewat ref, dan mengirim form ini lewat atribut form="form-tambah-muatan".
const emit = defineEmits(['tersimpan', 'sibuk', 'langkah']);

const NAMA_LANGKAH = ['Data muatan', 'Item muatan', 'Konfirmasi'];
const langkah = ref(1);

const form = useForm({
    tanggal: '',
    depo_id: '',
    karyawan_id: '',
    items: [],
});

const depo = ref([]);
const sales = ref([]);
const produk = ref([]);
const memuat = ref(true);
const galatOpsi = ref('');

async function ambilOpsi(idDepo = '') {
    const respon = await fetch(`/barang_canvassing/opsi${idDepo ? `?depo=${encodeURIComponent(idDepo)}` : ''}`, { headers: { Accept: 'application/json' } });
    if (!respon.ok) throw new Error(String(respon.status));

    return respon.json();
}

onMounted(async () => {
    try {
        const data = await ambilOpsi();
        form.tanggal = data.tanggal;
        depo.value = data.depo;
    } catch {
        galatOpsi.value = 'Form belum bisa dimuat. Tutup form lalu coba lagi.';
    } finally {
        memuat.value = false;
    }
});

async function muatSalesProduk(idDepo) {
    sales.value = [];
    produk.value = [];
    form.karyawan_id = '';
    form.items = [];
    form.clearErrors('items');
    galatOpsi.value = '';
    if (!idDepo) return;

    memuat.value = true;
    let dimuat = false;
    try {
        const data = await ambilOpsi(idDepo);
        if (String(form.depo_id) !== String(idDepo)) return; // depo sudah berganti saat menunggu
        sales.value = data.sales.map((s) => ({ value: s.id, label: s.nama }));
        produk.value = data.produk.map((p) => ({ value: p.id, label: p.kode ? `${p.kode} · ${p.nama}` : p.nama }));
        dimuat = true;
    } catch {
        galatOpsi.value = 'Daftar motoris dan produk gagal dimuat. Pilih depo lagi atau muat ulang halaman.';
    } finally {
        memuat.value = false;
    }

    // Depo sudah dipilih dan motoris siap: fokus pindah ke pilihan motoris (aktif lagi setelah render).
    if (dimuat && langkah.value === 1) {
        await nextTick();
        inputSales.value?.fokus();
    }
}

watch(() => form.depo_id, muatSalesProduk);
watch(() => form.processing || memuat.value, (sibuk) => emit('sibuk', sibuk), { immediate: true });
watch(langkah, (nilai) => emit('langkah', nilai), { immediate: true });

const labelDepo = computed(() => depo.value.find((d) => String(d.value) === String(form.depo_id))?.label ?? '');
const labelSales = computed(() => sales.value.find((x) => String(x.value) === String(form.karyawan_id))?.label ?? '');

// Satu baris isian produk + qty; "Tambah" memindahkannya ke tabel produk di bawahnya (form.items).
const produkDipilih = ref('');
const qtyDipilih = ref('');
const galatTambah = ref('');
const inputProduk = ref(null);
const inputSales = ref(null);

// Maju satu langkah. 1 -> 2: tanggal, depo, dan motoris wajib terisi. 2 -> 3 (konfirmasi): minimal satu produk.
function lanjut() {
    if (langkah.value === 1) {
        form.clearErrors('tanggal', 'depo_id', 'karyawan_id');

        if (!form.tanggal) form.setError('tanggal', 'Tanggal wajib diisi.');
        if (!form.depo_id) form.setError('depo_id', 'Pilih depo.');
        if (form.depo_id && !form.karyawan_id) form.setError('karyawan_id', 'Pilih motoris.');
        if (form.hasErrors) return;

        langkah.value = 2;
        nextTick(() => inputProduk.value?.fokus());

        return;
    }

    if (langkah.value === 2) {
        if (!form.items.length) {
            form.setError('items', 'Tambahkan minimal satu produk.');

            return;
        }

        langkah.value = 3;
    }
}

function kembali() {
    langkah.value = Math.max(1, langkah.value - 1);

    if (langkah.value === 2) nextTick(() => inputProduk.value?.fokus());
}

defineExpose({ lanjut, kembali });

// Produk yang sudah ada di tabel tidak ditawarkan lagi.
const opsiProduk = computed(() => {
    const dipakai = new Set(form.items.map((i) => String(i.produk_id)));

    return produk.value.filter((p) => !dipakai.has(String(p.value)));
});

const labelProduk = (id) => produk.value.find((p) => String(p.value) === String(id))?.label ?? `Produk ${id}`;

function tambahProduk() {
    galatTambah.value = '';

    if (!produkDipilih.value) {
        galatTambah.value = 'Pilih produk dulu.';

        return;
    }

    if (!(Number(qtyDipilih.value) > 0)) {
        galatTambah.value = 'Qty bawa harus lebih dari 0.';

        return;
    }

    form.items.push({ produk_id: produkDipilih.value, qty_bawa: qtyDipilih.value });
    form.clearErrors('items');
    produkDipilih.value = '';
    qtyDipilih.value = '';
    // Fokus kembali ke pencarian produk supaya produk berikutnya bisa langsung dicari.
    nextTick(() => inputProduk.value?.fokus());
}

function hapusProduk(index) {
    form.items.splice(index, 1);
}

const KOLOM_TABEL = { no: 'No', produk: 'Produk', qty_bawa: 'Qty Bawa', aksi: '' };
const KOLOM_KONFIRMASI = { no: 'No', produk: 'Produk', qty_bawa: 'Qty Bawa' };

const totalQty = computed(() => angkaRingkas(form.items.reduce((jumlah, item) => jumlah + Number(item.qty_bawa), 0), 2));

// Galat server per baris tabel (items.N.produk_id / items.N.qty_bawa).
const barisTabel = computed(() => form.items.map((item, index) => ({
    no: index + 1,
    produk: labelProduk(item.produk_id),
    qty_bawa: item.qty_bawa,
    indeks: index,
    galat: form.errors[`items.${index}.produk_id`] ?? form.errors[`items.${index}.qty_bawa`] ?? null,
})));

function simpan() {
    // Enter sebelum langkah konfirmasi berarti lanjut, bukan simpan.
    if (langkah.value < 3) {
        lanjut();

        return;
    }

    form.post('/barang_canvassing', {
        preserveScroll: true,
        onSuccess: () => emit('tersimpan'),
        // Galat data muatan (mis. motoris sudah punya muatan di tanggal itu) dikoreksi di langkah 1,
        // galat item di langkah 2.
        onError: (galat) => {
            if (['tanggal', 'depo_id', 'karyawan_id'].some((k) => k in galat)) langkah.value = 1;
            else langkah.value = 2;
        },
    });
}
</script>

<template>
    <form id="form-tambah-muatan" class="flex h-full min-h-0 flex-col gap-3" @submit.prevent="simpan">
        <ol class="wizard-langkah" aria-label="Langkah">
            <li
                v-for="(nama, i) in NAMA_LANGKAH"
                :key="nama"
                :class="{ 'wizard-langkah-aktif': langkah === i + 1, 'wizard-langkah-selesai': langkah > i + 1 }"
                :aria-current="langkah === i + 1 ? 'step' : null"
            >
                <span class="wizard-langkah-nomor">{{ i + 1 }}</span>{{ nama }}
            </li>
        </ol>
        <Alert v-if="galatOpsi" variant="danger" role="alert">{{ galatOpsi }}</Alert>

        <div v-if="langkah === 1" class="flex min-h-0 flex-1 items-center justify-center">
            <div class="flex w-full max-w-md flex-col gap-3">
                <p class="mb-0 text-xs text-gray-600">Pilih tanggal, depo, dan motoris penerima muatan. Motoris menerimanya di aplikasi HP.</p>
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <Label for="tanggal">Tanggal <span class="text-red-600">*</span></Label>
                        <DatePicker id="tanggal" v-model="form.tanggal" />
                        <FieldError v-if="form.errors.tanggal">{{ form.errors.tanggal }}</FieldError>
                    </div>
                    <div>
                        <Label for="depo_id">Depo <span class="text-red-600">*</span></Label>
                        <Combobox id="depo_id" v-model="form.depo_id" :options="depo" placeholder="Cari depo..." :disabled="memuat && !depo.length" />
                        <FieldError v-if="form.errors.depo_id">{{ form.errors.depo_id }}</FieldError>
                    </div>
                    <div>
                        <Label for="karyawan_id">Motoris <span class="text-red-600">*</span></Label>
                        <Combobox
                            id="karyawan_id"
                            ref="inputSales"
                            v-model="form.karyawan_id"
                            :options="sales"
                            :placeholder="memuat ? 'Memuat...' : (form.depo_id ? 'Cari motoris...' : 'Pilih depo dulu')"
                            :disabled="!form.depo_id || memuat"
                            empty-text="Tidak ada motoris di depo ini."
                        />
                        <FieldError v-if="form.errors.karyawan_id">{{ form.errors.karyawan_id }}</FieldError>
                    </div>
                </div>
            </div>
        </div>

        <template v-else-if="langkah === 2">
            <p class="mb-0 text-xs text-gray-600">
                Muatan untuk <b class="text-gray-900">{{ labelSales }}</b> · {{ labelDepo }} · {{ tanggalSingkat(form.tanggal) }}
            </p>
            <div>
                <div class="flex flex-col sm:flex-row sm:items-end gap-2">
                    <div class="flex-1 min-w-0">
                        <Label for="produk-baru">Produk</Label>
                        <Combobox
                            id="produk-baru"
                            ref="inputProduk"
                            v-model="produkDipilih"
                            :options="opsiProduk"
                            :disabled="!produk.length"
                            :placeholder="produk.length ? 'Cari produk...' : 'Memuat produk...'"
                            :clearable="false"
                        />
                    </div>
                    <div class="sm:w-40">
                        <Label for="qty-baru">Qty bawa</Label>
                        <Input id="qty-baru" v-model="qtyDipilih" type="number" step="any" min="0" :disabled="!produk.length" @keydown.enter.prevent="tambahProduk" />
                    </div>
                    <div>
                        <Button variant="light" :disabled="!produk.length" @click="tambahProduk">+ Tambah</Button>
                    </div>
                </div>
                <FieldError v-if="galatTambah">{{ galatTambah }}</FieldError>
            </div>

            <div class="flex min-h-0 flex-1 flex-col">
                <FieldError v-if="form.errors.items" class="mb-2">{{ form.errors.items }}</FieldError>
                <DataTable isi-wadah class="min-h-0 flex-1" :columns="KOLOM_TABEL" :rows="barisTabel" :total="barisTabel.length" empty-text="Belum ada produk ditambahkan.">
                    <template #cell-qty_bawa="{ row }">
                        {{ row.qty_bawa }}
                        <FieldError v-if="row.galat">{{ row.galat }}</FieldError>
                    </template>
                    <template #cell-aksi="{ row }">
                        <Button variant="outline-danger" aria-label="Hapus produk dari daftar" @click="hapusProduk(row.indeks)">Hapus</Button>
                    </template>
                </DataTable>
            </div>
        </template>

        <template v-else>
            <p class="mb-0 text-xs text-gray-600">Periksa kembali muatan di bawah. Setelah disimpan, muatan berstatus Disiapkan dan motoris menerimanya di aplikasi HP.</p>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-5 mb-0 text-xs">
                <div><dt class="text-gray-500">Tanggal</dt><dd class="mb-0 font-semibold">{{ tanggalSingkat(form.tanggal) }}</dd></div>
                <div><dt class="text-gray-500">Depo</dt><dd class="mb-0 font-semibold">{{ labelDepo }}</dd></div>
                <div><dt class="text-gray-500">Motoris</dt><dd class="mb-0 font-semibold">{{ labelSales }}</dd></div>
                <div><dt class="text-gray-500">Jumlah SKU</dt><dd class="mb-0 font-semibold">{{ form.items.length }}</dd></div>
                <div><dt class="text-gray-500">Total Qty Bawa</dt><dd class="mb-0 font-semibold">{{ totalQty }}</dd></div>
            </dl>
            <div class="flex min-h-0 flex-1 flex-col">
                <DataTable isi-wadah class="min-h-0 flex-1" :columns="KOLOM_KONFIRMASI" :rows="barisTabel" :total="barisTabel.length" />
            </div>
        </template>
    </form>
</template>
