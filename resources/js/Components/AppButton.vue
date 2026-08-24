<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    disabled: { type: Boolean, default: false },
    method: { type: String, default: 'get' },
});

const variants = {
    primary: 'bg-ink text-white hover:opacity-90',
    gold: 'bg-gold text-ink hover:opacity-90',
    secondary: 'bg-white text-slate-800 ring-1 ring-black/10 hover:bg-slate-50',
    danger: 'bg-rose-100 text-rose-600 ring-1 ring-rose-200 hover:bg-rose-200',
    ghost: 'text-slate-600 hover:bg-white',
};
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :method="method"
        :as="method === 'get' ? 'a' : 'button'"
        class="inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-medium transition"
        :class="variants[variant] || variants.primary"
    >
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-medium transition disabled:opacity-50"
        :class="variants[variant] || variants.primary"
    >
        <slot />
    </button>
</template>

