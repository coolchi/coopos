<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
        <div v-if="title || description || $slots.header" class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                <h2 v-if="title" class="font-medium text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm leading-5 text-slate-500">{{ description }}</p>
            </div>
            <slot name="header" />
        </div>
        <div :class="[padded ? 'p-5' : '', 'overflow-x-auto']">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-slate-100">
            <slot name="footer" />
        </div>
    </div>
</template>
