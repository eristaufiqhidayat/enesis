<script setup>
import { Head, usePage } from '@inertiajs/vue3';

// Layout halaman tamu (login, ganti password): tanpa sidebar dan topbar.
defineProps({ title: { type: String, default: '' } });

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`${title} · ${appName}`" />
    <div class="auth-page">
        <div class="auth-box">
            <div class="auth-brand">{{ appName }}</div>
            <slot />
        </div>
    </div>
</template>
