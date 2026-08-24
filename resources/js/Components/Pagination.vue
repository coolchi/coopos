<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, default: () => [] },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
});
</script>

<template>
    <div v-if="total > 0" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm text-slate-500">
        <p>Showing {{ from }}–{{ to }} of {{ total }}</p>
        <nav v-if="links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="(link, i) in links"
                :key="i"
                :href="link.url || ''"
                :class="[
                    'rounded-lg px-3 py-1.5',
                    link.active ? 'bg-[#071c1b] text-white' : 'hover:bg-slate-100',
                    !link.url ? 'pointer-events-none opacity-40' : '',
                ]"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
