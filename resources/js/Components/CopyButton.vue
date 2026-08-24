<script setup>
import { onUnmounted, ref } from 'vue';

const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, default: 'Copy' },
    copiedLabel: { type: String, default: 'Copied' },
    wide: { type: Boolean, default: false },
});

const emit = defineEmits(['copied']);
const copied = ref(false);
const bump = ref(0);
let timer = 0;

const writeClipboard = async (text) => {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Fall through to the older copy path on http pages.
        }
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
    document.body.appendChild(field);
    field.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(field);

    return ok;
};

const copy = async () => {
    if (! props.value) {
        return;
    }

    const ok = await writeClipboard(props.value);
    if (! ok) {
        return;
    }

    window.clearTimeout(timer);
    copied.value = true;
    bump.value += 1;
    emit('copied', props.value);
    timer = window.setTimeout(() => {
        copied.value = false;
    }, 1800);
};

onUnmounted(() => window.clearTimeout(timer));
</script>

<template>
    <button
        type="button"
        class="copy-btn inline-flex items-center justify-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition"
        :class="[
            wide ? 'w-full' : 'shrink-0',
            copied ? 'copy-btn--done' : 'copy-btn--idle',
        ]"
        :aria-label="copied ? copiedLabel : label"
        @click="copy"
    >
        <span :key="`icon-${bump}`" class="copy-btn-icon relative h-4 w-4 shrink-0" aria-hidden="true">
            <svg v-if="!copied" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="11" height="13" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
            </svg>
            <svg v-else class="copy-btn-check h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
        <span :key="`label-${copied}`" class="copy-btn-label">{{ copied ? copiedLabel : label }}</span>
    </button>
</template>
