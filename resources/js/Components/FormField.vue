<script setup>
defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    forId: { type: String, default: '' },
});
</script>

<template>
    <label class="block text-sm">
        <span v-if="label" class="mb-1.5 block text-sm font-semibold text-slate-900">{{ label }}</span>
        <slot />
        <p v-if="error" class="mt-1 text-xs font-medium text-rose-700">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-slate-600">{{ hint }}</p>
    </label>
</template>
