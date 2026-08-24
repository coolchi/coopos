<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">{{ eyebrow }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-2 text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex w-full flex-col gap-2 print:hidden sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end [&>a]:w-full [&>button]:w-full sm:[&>a]:w-auto sm:[&>button]:w-auto"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
