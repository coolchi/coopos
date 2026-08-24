<script setup>
import { onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { showFlashToasts, toast } from '../toast';

const page = usePage();

const dismiss = (id) => toast.dismiss(id);

const startTimer = (item) => {
    window.setTimeout(() => dismiss(item.id), item.duration);
};

onMounted(() => {
    showFlashToasts(page.props.flash);
});

const stopSuccess = router.on('success', (event) => {
    showFlashToasts(event.detail.page.props.flash);
});

onUnmounted(() => {
    stopSuccess();
});
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 top-0 z-[90] flex justify-end p-4 sm:p-6">
            <TransitionGroup
                tag="div"
                class="flex w-full max-w-sm flex-col items-stretch gap-3"
                enter-active-class="toast-enter-active"
                enter-from-class="toast-enter-from"
                enter-to-class="toast-enter-to"
                leave-active-class="toast-leave-active"
                leave-from-class="toast-leave-from"
                leave-to-class="toast-leave-to"
                move-class="toast-move"
            >
                <article
                    v-for="item in toast.state.items"
                    :key="item.id"
                    class="pointer-events-auto overflow-hidden rounded-2xl shadow-2xl ring-1 ring-black/10 backdrop-blur"
                    :class="item.type === 'error'
                        ? 'bg-rose-700 text-white'
                        : item.type === 'info'
                            ? 'bg-[#071c1b] text-white'
                            : 'bg-[#042f2e] text-white'"
                    @vue:mounted="startTimer(item)"
                >
                    <div class="flex items-start gap-3 px-4 py-3.5">
                        <span
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                            :class="item.type === 'error' ? 'bg-white/15' : 'bg-[#d4b06a] text-[#071c1b]'"
                        >
                            <svg v-if="item.type === 'error'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :class="item.type === 'error' ? 'text-rose-100' : 'text-[#d4b06a]'">
                                {{ item.type === 'error' ? 'Something went wrong' : 'Success' }}
                            </p>
                            <p class="mt-0.5 text-sm font-medium leading-snug text-white/95">{{ item.message }}</p>
                        </div>
                        <button type="button" class="rounded-full p-1 text-white/70 hover:bg-white/10 hover:text-white" aria-label="Dismiss" @click="dismiss(item.id)">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="h-1 overflow-hidden" :class="item.type === 'error' ? 'bg-black/20' : 'bg-white/10'">
                        <div
                            class="toast-progress h-full origin-left"
                            :class="item.type === 'error' ? 'bg-white/80' : 'bg-[#d4b06a]'"
                            :style="{ animationDuration: `${item.duration}ms` }"
                        />
                    </div>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
