<script setup>
import { ref } from 'vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Input from '@/Components/ui/atoms/Input.vue';

// Input password dengan tombol mata untuk menampilkan/menyembunyikan isinya.
// Atribut lain (id, name, autocomplete, required, dst.) diteruskan ke input.
defineOptions({ inheritAttrs: false });

const model = defineModel({ type: String, default: '' });

const tampil = ref(false);
</script>

<template>
    <div class="password-field">
        <Input v-bind="$attrs" v-model="model" :type="tampil ? 'text' : 'password'" />
        <button type="button" class="password-toggle" :aria-label="tampil ? 'Sembunyikan password' : 'Tampilkan password'" @click="tampil = !tampil">
            <Icon :name="tampil ? 'eye-off' : 'eye'" />
        </button>
    </div>
</template>
