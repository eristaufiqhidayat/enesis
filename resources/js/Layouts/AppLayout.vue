<script setup>
import Sidebar from '@/Components/ui/organisms/Sidebar.vue';
import Topbar from '@/Components/ui/organisms/Topbar.vue';

defineProps({ title: { type: String, default: '' } });
</script>

<template>
    <Sidebar />
    <main>
        <Topbar />
        <slot />
    </main>
</template>
