<script setup>
import EmptyState from './EmptyState.vue';

defineProps({
    columns: { type: Array, required: true },
    emptyTitle: { type: String, default: 'Nothing here yet' },
    emptyBody: { type: String, default: '' },
    emptyIcon: { type: String, default: 'spark' },
    rows: { type: Number, default: 0 },
});
</script>

<template>
    <div class="overflow-x-auto">
        <EmptyState v-if="!rows" :title="emptyTitle" :body="emptyBody" :icon="emptyIcon">
            <slot name="empty" />
        </EmptyState>
        <table v-else class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th v-for="col in columns" :key="col" class="px-5 py-3 font-medium">{{ col }}</th>
                </tr>
            </thead>
            <tbody>
                <slot />
            </tbody>
        </table>
    </div>
</template>
