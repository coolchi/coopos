<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import MoneyAmount from './MoneyAmount.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    duration: { type: Number, default: 900 },
    spread: { type: Boolean, default: false },
});

const display = ref(String(props.value ?? ''));
let frame = 0;

const prefersReducedMotion = () =>
    typeof window !== 'undefined'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const parseValue = (raw) => {
    const text = String(raw ?? '').trim();
    if (text === '') {
        return { kind: 'plain', text: '' };
    }

    const match = text.match(/^([^0-9\-]*)(-?\d[\d,]*(?:\.\d+)?)(.*)$/);
    if (! match) {
        return { kind: 'plain', text };
    }

    const numeric = Number(match[2].replace(/,/g, ''));
    if (Number.isNaN(numeric)) {
        return { kind: 'plain', text };
    }

    const decimals = match[2].includes('.') ? (match[2].split('.')[1]?.length || 0) : 0;

    return {
        kind: 'number',
        prefix: match[1],
        suffix: match[3],
        target: numeric,
        decimals,
        useGrouping: match[2].includes(','),
    };
};

const formatNumber = (value, meta) => {
    const absolute = Math.abs(value);
    const formatted = meta.useGrouping
        ? absolute.toLocaleString('en-NG', {
            minimumFractionDigits: meta.decimals,
            maximumFractionDigits: meta.decimals,
        })
        : absolute.toFixed(meta.decimals);

    const signed = value < 0 ? `-${formatted}` : formatted;

    return `${meta.prefix}${signed}${meta.suffix}`;
};

const easeOutCubic = (t) => 1 - ((1 - t) ** 3);

const animateTo = (raw) => {
    cancelAnimationFrame(frame);
    const meta = parseValue(raw);

    if (meta.kind === 'plain' || prefersReducedMotion() || meta.target === 0) {
        display.value = meta.kind === 'plain' ? meta.text : formatNumber(meta.target, meta);
        return;
    }

    const start = performance.now();
    const from = 0;
    const run = (now) => {
        const progress = Math.min(1, (now - start) / props.duration);
        const current = from + ((meta.target - from) * easeOutCubic(progress));
        display.value = formatNumber(current, meta);
        if (progress < 1) {
            frame = requestAnimationFrame(run);
        } else {
            display.value = formatNumber(meta.target, meta);
        }
    };

    display.value = formatNumber(0, meta);
    frame = requestAnimationFrame(run);
};

watch(() => props.value, (next) => animateTo(next));
onMounted(() => animateTo(props.value));
onUnmounted(() => cancelAnimationFrame(frame));

const shown = computed(() => display.value);
</script>

<template>
    <div
        class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5 sm:p-6"
        :class="spread ? 'flex items-end justify-between gap-4 sm:block' : ''"
    >
        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ label }}</div>
        <div
            class="text-[1.75rem] font-semibold leading-none text-slate-900 sm:text-3xl"
            :class="spread ? 'sm:mt-2' : 'mt-2'"
        >
            <MoneyAmount :value="shown" />
        </div>
        <div v-if="hint || $slots.hint" class="mt-3 text-xs text-slate-500">
            <slot name="hint">{{ hint }}</slot>
        </div>
    </div>
</template>
