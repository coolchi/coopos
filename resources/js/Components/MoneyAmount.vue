<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: [String, Number], required: true },
});

const parts = computed(() => {
    const text = String(props.value ?? '').trim();
    const match = text.match(/^([^0-9\-]*)(-?\d[\d,]*(?:\.\d+)?)(.*)$/);

    if (! match) {
        return { prefix: '', amount: text };
    }

    return {
        prefix: match[1],
        amount: `${match[2]}${match[3]}`,
    };
});
</script>

<template>
    <span class="inline-flex items-baseline">
        <span v-if="parts.prefix" class="mr-[0.18em] tracking-normal">{{ parts.prefix }}</span>
        <span class="tabular-nums tracking-normal">{{ parts.amount }}</span>
    </span>
</template>
