<script>
const cache = new Map();
</script>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({ name: { type: String, required: true } });

const innerHtml = ref('');

async function load(name) {
    if (!cache.has(name)) {
        try {
            const response = await fetch(`/vendor/lucide/${name}.svg`);
            const text = await response.text();
            const match = text.match(/<svg[^>]*>([\s\S]*)<\/svg>/);
            cache.set(name, match ? match[1].trim() : '');
        } catch {
            cache.set(name, '');
        }
    }

    innerHtml.value = cache.get(name);
}

watch(() => props.name, load, { immediate: true });
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        v-html="innerHtml"
    ></svg>
</template>
