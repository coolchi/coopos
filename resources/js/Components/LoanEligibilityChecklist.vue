<script setup>
defineProps({
    checks: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    ready: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: 'Select a member and amount to see whether they meet loan conditions.' },
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loan conditions</p>
            <p v-if="loading" class="text-xs text-slate-400">Checking…</p>
        </div>
        <p v-if="error" class="mt-2 text-sm text-rose-700">{{ error }}</p>
        <p v-else-if="!ready && !loading" class="mt-2 text-sm text-slate-500">{{ hint }}</p>
        <ul v-else class="mt-2 space-y-2">
            <li v-for="check in checks" :key="check.id" class="flex items-start gap-2.5 text-sm">
                <span
                    class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                    :class="check.skipped
                        ? 'bg-slate-200 text-slate-500'
                        : (check.pass ? 'bg-teal-100 text-teal-800' : 'bg-rose-100 text-rose-700')"
                >
                    {{ check.skipped ? '–' : (check.pass ? '✓' : '!') }}
                </span>
                <div class="min-w-0">
                    <p class="font-medium text-slate-900">{{ check.label }}</p>
                    <p class="text-xs text-slate-500">{{ check.detail }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
