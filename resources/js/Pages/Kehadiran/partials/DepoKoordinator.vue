<script setup>
defineProps({
    depo: { type: String, required: true },
    koordinator: { type: String, default: null },
});
</script>

<template>
    <div>
        {{ depo }}
        <div v-if="koordinator" class="text-gray-500">Koor: {{ koordinator }}</div>
    </div>
</template>
