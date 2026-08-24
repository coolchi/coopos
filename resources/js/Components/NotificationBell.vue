<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'light' },
});

const page = usePage();
const open = ref(false);
const root = ref(null);

const inbox = computed(() => page.props.notifications || { unread: 0, recent: [] });
const unread = computed(() => Number(inbox.value.unread || 0));
const items = computed(() => inbox.value.recent || []);
const dark = computed(() => props.variant === 'dark');
const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));

const toggle = () => {
    open.value = ! open.value;
};

const close = () => {
    open.value = false;
};

const markAll = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
};

const openItem = (item) => {
    close();
    const visit = () => {
        if (item.url) {
            router.visit(item.url);
        }
    };

    if (item.read) {
        visit();
        return;
    }

    router.post(`/notifications/${item.id}/read`, {}, {
        preserveScroll: true,
        preserveState: ! item.url,
        onSuccess: visit,
    });
};

const onDocumentClick = (event) => {
    if (root.value && ! root.value.contains(event.target)) {
        close();
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative shrink-0">
        <button
            type="button"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-full transition"
            :class="dark
                ? 'bg-white/10 text-white hover:bg-white/15'
                : 'bg-slate-50 text-slate-700 ring-1 ring-slate-200/80 hover:bg-white'"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Notifications"
            @click.stop="toggle"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span
                v-if="unread > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white"
            >{{ badge }}</span>
        </button>
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-slate-900/40 sm:hidden"
            @click="close"
        />
        <div
            v-if="open"
            class="fixed inset-x-3 top-[calc(env(safe-area-inset-top)+4.5rem)] z-50 flex max-h-[min(28rem,calc(100dvh-9rem))] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-[22rem] sm:max-h-96"
            role="menu"
        >
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-slate-900">Notifications</div>
                    <div class="text-xs text-slate-500">{{ unread ? `${unread} unread` : 'You are up to date' }}</div>
                </div>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="shrink-0 text-xs font-medium text-teal-800 hover:underline"
                    @click="markAll"
                >
                    Mark all read
                </button>
            </div>
            <div v-if="!items.length" class="px-4 py-8 text-center text-sm text-slate-500">
                No notifications yet.
            </div>
            <div v-else class="min-h-0 flex-1 overflow-y-auto py-1">
                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50"
                    role="menuitem"
                    @click="openItem(item)"
                >
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="item.read ? 'bg-slate-200' : 'bg-teal-700'"
                    ></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium" :class="item.read ? 'text-slate-700' : 'text-slate-900'">{{ item.title }}</span>
                        <span v-if="item.body" class="mt-0.5 line-clamp-2 block text-xs text-slate-500">{{ item.body }}</span>
                        <span class="mt-1 block text-[11px] text-slate-400">{{ item.created_at }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
