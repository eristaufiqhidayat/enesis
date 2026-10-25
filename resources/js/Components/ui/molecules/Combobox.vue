<script setup>
// options: { id: label } atau [{ value, label }], sama seperti atoms/Select. Mendukung pencarian ketik,
// navigasi keyboard, dan mode `multiple` (modelValue array, dropdown tetap terbuka saat memilih berkali-kali).
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number, Array], default: null },
    options: { type: [Object, Array], default: () => [] },
    multiple: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Cari...' },
    emptyText: { type: String, default: 'Tidak ada hasil.' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const daftar = computed(() => {
    return Array.isArray(props.options)
        ? props.options
        : Object.entries(props.options).map(([value, label]) => ({ value, label }));
});

const open = ref(false);
const search = ref('');
const highlighted = ref(-1);
const wrapper = ref(null);
const inputEl = ref(null);

const selectedValues = computed(() => {
    if (props.multiple) return Array.isArray(props.modelValue) ? props.modelValue.map(String) : [];
    return props.modelValue !== null && props.modelValue !== '' && props.modelValue !== undefined ? [String(props.modelValue)] : [];
});

const selectedItems = computed(() => daftar.value.filter((item) => selectedValues.value.includes(String(item.value))));

const displayText = computed(() => selectedItems.value.map((item) => item.label).join(', '));

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return daftar.value;
    return daftar.value.filter((item) => String(item.label).toLowerCase().includes(q));
});

function diPilih(item) {
    return selectedValues.value.includes(String(item.value));
}

function bukaDropdown() {
    if (props.disabled) return;
    search.value = '';
    open.value = true;
    highlighted.value = filtered.value.findIndex((item) => diPilih(item));
}

function pilih(item) {
    if (props.multiple) {
        const nilai = String(item.value);
        const next = diPilih(item) ? selectedValues.value.filter((v) => v !== nilai) : [...selectedValues.value, nilai];
        emit('update:modelValue', next);
        search.value = '';
        nextTick(() => inputEl.value?.focus());
        return;
    }

    emit('update:modelValue', item.value);
    search.value = '';
    open.value = false;
}

function hapus() {
    emit('update:modelValue', props.multiple ? [] : '');
    search.value = '';
    inputEl.value?.focus();
}

function arahkan(delta) {
    if (!open.value) {
        bukaDropdown();
        return;
    }
    const total = filtered.value.length;
    if (!total) return;
    highlighted.value = (highlighted.value + delta + total) % total;
}

function pilihYangDisorot() {
    if (!open.value) return;
    const item = filtered.value[highlighted.value];
    if (item) pilih(item);
}

function tutupDiLuar(e) {
    if (wrapper.value && !wrapper.value.contains(e.target)) {
        open.value = false;
        search.value = '';
    }
}

watch(filtered, () => {
    if (highlighted.value >= filtered.value.length) highlighted.value = filtered.value.length - 1;
});

onMounted(() => document.addEventListener('click', tutupDiLuar));
onUnmounted(() => document.removeEventListener('click', tutupDiLuar));
</script>

<template>
    <div class="combobox" ref="wrapper">
        <input
            ref="inputEl"
            type="text"
            class="form-control form-control-sm truncate"
            :class="{ 'pr-6': clearable && selectedItems.length }"
            v-bind="$attrs"
            :placeholder="placeholder"
            :disabled="disabled"
            :value="open ? search : displayText"
            @focus="bukaDropdown"
            @click="bukaDropdown"
            @input="search = $event.target.value; open = true"
            @keydown.down.prevent="arahkan(1)"
            @keydown.up.prevent="arahkan(-1)"
            @keydown.enter.prevent="pilihYangDisorot"
            @keydown.esc="open = false; search = ''"
        >
        <button
            v-if="clearable && selectedItems.length && !disabled"
            type="button"
            class="combobox-clear"
            aria-label="Hapus pilihan"
            @click="hapus"
        >
            <Icon name="x" />
        </button>
        <ul v-if="open" class="combobox-menu">
            <li v-if="!filtered.length" class="combobox-empty">{{ emptyText }}</li>
            <li v-for="(item, index) in filtered" :key="item.value">
                <button
                    type="button"
                    class="combobox-option"
                    :class="{ highlighted: index === highlighted, selected: diPilih(item) }"
                    @mouseenter="highlighted = index"
                    @click="pilih(item)"
                >
                    <Icon v-if="multiple && diPilih(item)" name="check" class="me-1 inline-block" />
                    {{ item.label }}
                </button>
            </li>
        </ul>
    </div>
</template>
