<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PasswordInput from '@/Components/ui/molecules/PasswordInput.vue';

// demoPeran: kode peran => label, hanya terisi bila fitur demo masuk cepat aktif.
defineProps({
    demoPeran: { type: [Object, Array], default: null },
});

const form = useForm({ username: '', password: '', ingat: false });

// Error dari redirect GET (mis. akun tanpa hak web yang dikeluarkan middleware) tidak lewat useForm.
const errorUsername = computed(() => form.errors.username ?? usePage().props.errors?.username);

function masuk() {
    form.post('/login', { onFinish: () => form.reset('password') });
}

function masukDemo(peran) {
    router.post(`/login/demo/${peran}`);
}
</script>

<template>
    <AuthLayout title="Masuk">
        <Card>
            <template #header>Masuk</template>

            <form class="space-y-3" @submit.prevent="masuk">
                <div>
                    <Label for="username">Username</Label>
                    <Input id="username" v-model="form.username" autocomplete="username" autofocus required />
                    <FieldError v-if="errorUsername">{{ errorUsername }}</FieldError>
                </div>

                <div>
                    <Label for="password">Password</Label>
                    <PasswordInput id="password" v-model="form.password" autocomplete="current-password" required />
                    <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
                </div>

                <label class="form-check">
                    <Checkbox v-model="form.ingat" />
                    <span>Ingat saya</span>
                </label>

                <Button type="submit" class="w-full" :disabled="form.processing">Masuk</Button>

                <p class="text-center text-xs text-gray-500">Lupa password? Hubungi admin.</p>
            </form>

            <div v-if="demoPeran" class="demo-login">
                <div class="demo-login-title"><strong>Demo masuk cepat</strong> · mode uji coba, tanpa password</div>
                <div class="demo-login-buttons">
                    <Button v-for="(label, peran) in demoPeran" :key="peran" variant="outline-secondary" @click="masukDemo(peran)">{{ label }}</Button>
                </div>
            </div>
        </Card>
    </AuthLayout>
</template>
