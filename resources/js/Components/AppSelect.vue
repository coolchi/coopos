<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean], default: '' },
    options: { type: Array, default: () => [] },
    optionValue: { type: String, default: 'value' },
    optionLabel: { type: String, default: 'label' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    searchable: { type: Boolean, default: null },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'default' },
    name: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open = ref(false);
const query = ref('');
const active = ref(-1);
const root = ref(null);
const panel = ref(null);
const searchInput = ref(null);
const panelStyle = ref({});

const normalized = computed(() => props.options.map((item) => {
    if (item !== null && typeof item === 'object' && !Array.isArray(item)) {
        const value = item[props.optionValue] ?? item.value ?? item.uuid ?? item.id ?? item.slug;
        const composed = [item.first_name, item.last_name].filter(Boolean).join(' ');
        const extra = item.membership_number
            ? ` · ${item.membership_number}`
            : (item.email && (item.name || composed) ? ` · ${item.email}` : '');
        const baseLabel = item[props.optionLabel] ?? item.label ?? item.name ?? composed ?? String(value ?? '');

        return { value, label: String(baseLabel) + extra, disabled: Boolean(item.disabled) };
    }

    return { value: item, label: String(item), disabled: false };
}));

const showSearch = computed(() => props.searchable ?? normalized.value.length > 10);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (! q) {
        return normalized.value;
    }

    return normalized.value.filter((item) => item.label.toLowerCase().includes(q));
});

const selected = computed(() => normalized.value.find((item) => String(item.value) === String(props.modelValue ?? '')));

const display = computed(() => selected.value?.label || props.placeholder || 'Select');

const isPlaceholder = computed(() => ! selected.value);

const triggerClass = computed(() => {
    const sizes = {
        sm: 'min-h-9 rounded-xl px-3 py-2 text-sm',
        md: 'min-h-11 rounded-2xl px-4 py-2.5 text-sm',
    };
    const variants = {
        default: 'border border-slate-300 bg-white text-slate-900 shadow-sm',
        outline: 'border border-slate-300 bg-white text-slate-900 shadow-sm',
        white: 'border border-slate-300 bg-white text-slate-900 shadow-sm',
        dark: 'border-0 bg-white/10 text-white shadow-none',
    };

    return [
        'flex w-full items-center justify-between gap-3 text-left font-medium transition focus:outline-none',
        sizes[props.size] || sizes.md,
        variants[props.variant] || variants.default,
        props.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
        open.value && props.variant === 'dark' ? 'ring-2 ring-white/30' : '',
        open.value && props.variant !== 'dark' ? 'ring-2 ring-teal-800/20' : '',
        isPlaceholder.value && props.variant === 'dark' ? 'font-normal text-white/60' : '',
        isPlaceholder.value && props.variant !== 'dark' ? 'font-normal text-slate-400' : '',
    ];
});

const positionPanel = () => {
    if (! root.value) {
        return;
    }
    const rect = root.value.getBoundingClientRect();
    const width = Math.max(rect.width, 180);
    const maxHeight = 280;
    const gap = 6;
    const spaceBelow = window.innerHeight - rect.bottom - 12;
    const openUp = spaceBelow < 180 && rect.top > spaceBelow;

    panelStyle.value = {
        position: 'fixed',
        left: `${Math.min(rect.left, window.innerWidth - width - 8)}px`,
        width: `${width}px`,
        zIndex: 80,
        maxHeight: `${maxHeight}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + gap}px` }
            : { top: `${rect.bottom + gap}px` }),
    };
};

const close = () => {
    open.value = false;
    query.value = '';
    active.value = -1;
};

const choose = (item) => {
    if (item.disabled) {
        return;
    }
    emit('update:modelValue', item.value);
    close();
    nextTick(() => emit('change', item.value));
};

const toggle = () => {
    if (props.disabled) {
        return;
    }
    open.value = ! open.value;
};

let ignoreOutsideUntil = 0;
let ignoreScrollUntil = 0;

watch(open, async (isOpen) => {
    if (! isOpen) {
        return;
    }
    ignoreOutsideUntil = Date.now() + 50;
    ignoreScrollUntil = Date.now() + 400;
    await nextTick();
    positionPanel();
    const index = filtered.value.findIndex((item) => String(item.value) === String(props.modelValue ?? ''));
    active.value = index;
    if (showSearch.value) {
        searchInput.value?.focus({ preventScroll: true });
    }
});

const onDocument = (event) => {
    if (! open.value || Date.now() < ignoreOutsideUntil) {
        return;
    }
    const target = event.target;
    if (root.value?.contains(target) || panel.value?.contains(target)) {
        return;
    }
    close();
};

const onKey = (event) => {
    if (! open.value) {
        if ((event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') && root.value?.contains(event.target)) {
            event.preventDefault();
            open.value = true;
        }
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(filtered.value.length - 1, active.value + 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(0, active.value - 1);
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const item = filtered.value[active.value];
        if (item) {
            choose(item);
        }
    }
};

const onScroll = (event) => {
    if (! open.value || Date.now() < ignoreScrollUntil) {
        return;
    }
    if (panel.value?.contains(event.target)) {
        return;
    }
    close();
};

const onTriggerPointerDown = (event) => {
    if (props.disabled) {
        return;
    }
    // Open/close on pointerdown so the document "outside" listener cannot
    // swallow the same gesture that is meant to open this select.
    event.preventDefault();
    event.stopPropagation();
    toggle();
};

onMounted(() => {
    document.addEventListener('pointerdown', onDocument);
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', close);
    window.addEventListener('scroll', onScroll, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocument);
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('resize', close);
    window.removeEventListener('scroll', onScroll, true);
});
</script>

<template>
    <div ref="root" class="relative w-full min-w-0">
        <button
            type="button"
            :class="triggerClass"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @pointerdown="onTriggerPointerDown"
        >
            <span class="min-w-0 flex-1 truncate">{{ display }}</span>
            <svg class="h-4 w-4 shrink-0 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>
        <select
            class="pointer-events-none absolute h-0 w-0 overflow-hidden opacity-0"
            tabindex="-1"
            :name="name"
            :required="required"
            :disabled="disabled"
            :value="modelValue ?? ''"
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option v-for="item in normalized" :key="String(item.value)" :value="item.value">{{ item.label }}</option>
        </select>
        <Teleport to="body">
            <div
                v-if="open"
                ref="panel"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10"
                :class="variant === 'dark' ? 'border-white/10 bg-[#0d2c2a] text-white' : ''"
                :style="panelStyle"
                role="listbox"
            >
                <div v-if="showSearch" class="border-b border-slate-100 p-2" :class="variant === 'dark' ? 'border-white/10' : ''">
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="search"
                        placeholder="Search"
                        class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none"
                        :class="variant === 'dark' ? 'bg-white/10 text-white placeholder:text-white/50' : ''"
                        @keydown.stop
                    >
                </div>
                <div class="max-h-64 overflow-y-auto py-1">
                    <button
                        v-if="placeholder && !query && !required"
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm"
                        :class="String(modelValue ?? '') === '' ? 'bg-teal-50 font-medium text-teal-900' : 'text-slate-400 hover:bg-slate-50'"
                        role="option"
                        @click="choose({ value: '', label: placeholder, disabled: false })"
                    >
                        {{ placeholder }}
                    </button>
                    <p v-if="!filtered.length" class="px-3 py-3 text-sm text-slate-400">No matches</p>
                    <button
                        v-for="(item, index) in filtered"
                        :key="String(item.value)"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                        :class="[
                            item.disabled ? 'cursor-not-allowed opacity-40' : '',
                            String(item.value) === String(modelValue ?? '')
                                ? (variant === 'dark' ? 'bg-white/10 font-medium text-white' : 'bg-teal-50 font-medium text-teal-950')
                                : (variant === 'dark' ? 'text-teal-50 hover:bg-white/5' : 'text-slate-800 hover:bg-slate-50'),
                            index === active ? (variant === 'dark' ? 'bg-white/10' : 'bg-slate-50') : '',
                        ]"
                        role="option"
                        :aria-selected="String(item.value) === String(modelValue ?? '')"
                        @click="choose(item)"
                        @mouseenter="active = index"
                    >
                        <span class="min-w-0 truncate">{{ item.label }}</span>
                        <svg
                            v-if="String(item.value) === String(modelValue ?? '')"
                            class="h-4 w-4 shrink-0 text-teal-800"
                            :class="variant === 'dark' ? 'text-[#d4b06a]' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
