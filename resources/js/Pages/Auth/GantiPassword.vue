<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PasswordInput from '@/Components/ui/molecules/PasswordInput.vue';

// wajib: akun baru masuk dengan password awal dari admin, jadi password lama tidak diminta.
const props = defineProps({
    wajib: { type: Boolean, default: false },
});

const form = useForm({ password_lama: '', password: '', password_confirmation: '' });

function simpan() {
    form.put('/ganti-password', { onFinish: () => form.reset('password_lama', 'password', 'password_confirmation') });
}

function keluar() {
    router.post('/logout');
}
</script>

<template>
    <AuthLayout title="Ganti Password">
        <Card>
            <template #header>Ganti Password</template>

            <form class="space-y-3" @submit.prevent="simpan">
                <Alert v-if="wajib" variant="info">
                    Anda masuk dengan password awal dari admin. Buat password baru sebelum melanjutkan.
                </Alert>

                <div v-if="!wajib">
                    <Label for="password_lama">Password lama</Label>
                    <PasswordInput id="password_lama" v-model="form.password_lama" autocomplete="current-password" required />
                    <FieldError v-if="form.errors.password_lama">{{ form.errors.password_lama }}</FieldError>
                </div>

                <div>
                    <Label for="password">Password baru (minimal 8 karakter)</Label>
                    <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required />
                    <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
                </div>

                <div>
                    <Label for="password_confirmation">Ulangi password baru</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
                </div>

                <div class="flex items-center justify-between gap-2">
                    <Button type="submit" :disabled="form.processing">Simpan password</Button>
                    <Button v-if="!wajib" :tag="Link" variant="light" href="/home">Batal</Button>
                </div>
            </form>

            <template #footer>
                <Button variant="outline-secondary" @click="keluar">Keluar</Button>
            </template>
        </Card>
    </AuthLayout>
</template>
