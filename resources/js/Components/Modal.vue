<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    wide: { type: Boolean, default: false },
    zIndex: { type: Number, default: 60 },
});

const emit = defineEmits(['close']);
const root = ref(null);

const syncBodyLock = () => {
    document.body.classList.toggle('overflow-hidden', Boolean(document.querySelector('[data-modal-layer]')));
};

const onKey = (e) => {
    if (e.key !== 'Escape' || ! props.show) {
        return;
    }
    const layers = [...document.querySelectorAll('[data-modal-layer]')];
    if (layers.at(-1) !== root.value) {
        return;
    }
    emit('close');
};

watch(() => props.show, async () => {
    await nextTick();
    syncBodyLock();
});

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    nextTick(syncBodyLock);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            ref="root"
            data-modal-layer
            class="fixed inset-0 flex items-end justify-center p-0 sm:items-center sm:p-6"
            :style="{ zIndex }"
        >
            <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>
            <div
                class="relative z-10 flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
                :class="wide ? 'max-w-3xl' : 'max-w-lg'"
                role="dialog"
                aria-modal="true"
            >
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
                        <p v-if="subtitle" class="mt-1 text-sm text-slate-600">{{ subtitle }}</p>
                    </div>
                    <button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100" @click="emit('close')">
                        Close
                    </button>
                </div>
                <div class="overflow-y-auto bg-white px-5 py-5">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
