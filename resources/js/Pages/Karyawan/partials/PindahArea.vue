<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Radio from '@/Components/ui/atoms/Radio.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';

// Pindahkan semua area sales ini ke sales lain di depo yang sama (padanan "Pindah Area ke Sales Lain" Marketama).
const props = defineProps({
    slug: { type: String, required: true },
    item: { type: Object, required: true },
    sales: { type: Array, required: true },
    jumlahArea: { type: Number, default: 0 },
});

const emit = defineEmits(['tersimpan']);

const form = useForm({ tujuan_id: '', mode: 'gabung' });

function pindahkan() {
    form.post(`/${props.slug}/${props.item.id}/pindah-area`, { preserveScroll: true, onSuccess: () => emit('tersimpan') });
}
</script>

<template>
    <Card>
        <template #header>Pindah area ke sales lain</template>
        <form class="flex flex-col gap-3" @submit.prevent="pindahkan">
            <p class="mb-0 text-gray-600">{{ jumlahArea }} area milik {{ item.nama }} dipindahkan ke sales tujuan di depo yang sama; {{ item.nama }} tidak lagi punya area.</p>
            <div>
                <Label for="tujuan_id">Sales tujuan</Label>
                <Combobox id="tujuan_id" v-model="form.tujuan_id" :options="sales" placeholder="Cari sales di depo ini..." />
                <FieldError v-if="form.errors.tujuan_id">{{ form.errors.tujuan_id }}</FieldError>
            </div>
            <div class="flex flex-col gap-1" role="radiogroup" aria-label="Cara memindahkan">
                <label class="flex items-center gap-2"><Radio v-model="form.mode" name="mode" value="gabung" /> Gabungkan dengan area sales tujuan</label>
                <label class="flex items-center gap-2"><Radio v-model="form.mode" name="mode" value="ganti" /> Ganti area sales tujuan</label>
            </div>
            <div>
                <Button type="submit" variant="outline-primary" :disabled="!form.tujuan_id || !jumlahArea" :loading="form.processing">Pindahkan area</Button>
            </div>
        </form>
    </Card>
</template>
