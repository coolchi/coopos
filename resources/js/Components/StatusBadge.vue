<script setup>
const props = defineProps({
    value: { type: String, default: '' },
    map: { type: Object, default: null },
});

const classes = {
    active: 'bg-teal-100 text-teal-800',
    pending: 'bg-amber-100 text-amber-800',
    pending_approval: 'bg-amber-100 text-amber-800',
    pending_guarantor: 'bg-amber-100 text-amber-800',
    under_review: 'bg-sky-100 text-sky-800',
    submitted: 'bg-sky-100 text-sky-800',
    trialing: 'bg-sky-100 text-sky-800',
    approved: 'bg-teal-100 text-teal-800',
    disbursed: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    suspended: 'bg-rose-100 text-rose-800',
    past_due: 'bg-orange-100 text-orange-900',
    defaulted: 'bg-rose-100 text-rose-800',
    cancelled: 'bg-slate-100 text-slate-600',
    expired: 'bg-slate-100 text-slate-600',
    inactive: 'bg-slate-100 text-slate-600',
    headquarters: 'bg-[#d4b06a]/25 text-[#071c1b]',
    'central branch': 'bg-[#d4b06a]/25 text-[#071c1b]',
    completed: 'bg-slate-100 text-slate-700',
    complete: 'bg-teal-100 text-teal-800',
    in_progress: 'bg-sky-100 text-sky-800',
    not_started: 'bg-slate-100 text-slate-600',
    paid: 'bg-teal-100 text-teal-800',
    open: 'bg-amber-100 text-amber-800',
    resolved: 'bg-teal-100 text-teal-800',
    closed: 'bg-slate-100 text-slate-600',
    high: 'bg-rose-100 text-rose-800',
    normal: 'bg-slate-100 text-slate-700',
    low: 'bg-sky-100 text-sky-800',
    void: 'bg-slate-100 text-slate-500',
    draft: 'bg-slate-100 text-slate-600',
    pinned: 'bg-[#d4b06a]/25 text-[#071c1b]',
    written_off: 'bg-slate-100 text-slate-600',
    present: 'bg-teal-100 text-teal-800',
    absent: 'bg-rose-50 text-rose-700',
    permitted: 'bg-sky-100 text-sky-800',
};
</script>

<template>
    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="(map || classes)[value] || 'bg-slate-100 text-slate-600'">
        {{ (value || '—').replace('_', ' ') }}
    </span>
</template>
