<script setup>
import { computed, ref } from 'vue';
import { Link, usePage, useForm, router } from '@inertiajs/vue3';
import NotificationBell from '../Components/NotificationBell.vue';

const page = usePage();
const current = computed(() => page.url.split('?')[0]);
const form = useForm({});
const query = ref('');
const mobileOpen = ref(false);
const counts = computed(() => page.props.navCounts || {});

const groups = computed(() => [
    {
        label: 'Overview',
        items: [
            { href: '/platform', label: 'Dashboard' },
            { href: '/platform/cooperatives', label: 'Cooperatives', badge: counts.value.pending },
        ],
    },
    {
        label: 'Revenue',
        items: [
            { href: '/platform/subscriptions', label: 'Subscriptions', badge: counts.value.past_due },
            { href: '/platform/invoices', label: 'Invoices' },
            { href: '/platform/plans', label: 'Plans' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { href: '/platform/tickets', label: 'Support', badge: counts.value.tickets },
            { href: '/platform/announcements', label: 'Announcements' },
            { href: '/platform/users', label: 'Operators' },
            { href: '/platform/audit', label: 'Audit log' },
        ],
    },
    {
        label: 'System',
        items: [
            { href: '/platform/settings', label: 'Settings' },
        ],
    },
]);

const isActive = (href) => {
    if (href === '/platform') {
        return current.value === '/platform' || current.value === '/platform/';
    }

    return current.value.startsWith(href);
};

const search = () => {
    router.get('/platform/cooperatives', { q: query.value }, { preserveState: true });
    mobileOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-[#f4f1ea] text-slate-900">
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="mobileOpen = false"></div>
        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 flex-col bg-[#071c1b] text-white transition-transform lg:flex"
            :class="mobileOpen ? 'flex' : 'hidden lg:flex'"
        >
            <div class="flex items-center gap-3 px-6 py-6">
                <img src="/images/coopos-logo.png" alt="CoopOS" class="h-9 w-9 rounded-xl">
                <div>
                    <div class="text-[11px] uppercase tracking-[0.28em] text-[#d4b06a]">SAAS ADMIN</div>
                    <div class="text-lg font-semibold">{{ page.props.platform?.name || 'CoopOS' }}</div>
                </div>
            </div>
            <nav class="flex-1 space-y-6 overflow-y-auto px-4 pb-8">
                <div v-for="group in groups" :key="group.label">
                    <div class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-teal-200/50">{{ group.label }}</div>
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="mb-1 flex items-center justify-between rounded-xl px-3 py-2 text-sm transition"
                        :class="isActive(item.href) ? 'bg-white/10 text-white' : 'text-teal-50/70 hover:bg-white/5 hover:text-white'"
                        @click="mobileOpen = false"
                    >
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.badge"
                            class="rounded-full bg-[#d4b06a] px-2 py-0.5 text-[11px] font-semibold text-[#071c1b]"
                        >{{ item.badge }}</span>
                    </Link>
                </div>
            </nav>
            <div class="border-t border-white/10 px-6 py-4 text-xs text-teal-100/50">
                Super admin workspace
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-black/5 bg-[#f4f1ea]/90 px-4 py-3 backdrop-blur sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="rounded-lg p-2 hover:bg-white lg:hidden" @click="mobileOpen = true">Menu</button>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-slate-800">{{ page.props.auth.user?.name }}</div>
                        <div class="truncate text-xs text-slate-500">{{ page.props.auth.user?.email }}</div>
                    </div>
                </div>
                <form class="hidden max-w-sm flex-1 md:block" @submit.prevent="search">
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Find a cooperative"
                        class="w-full rounded-full border-0 bg-white px-4 py-2 text-sm shadow-sm ring-1 ring-black/5"
                    >
                </form>
                <div class="flex items-center gap-2">
                    <NotificationBell />
                    <Link href="/" class="hidden rounded-full px-3 py-1.5 text-sm text-slate-500 hover:bg-white sm:inline">View site</Link>
                    <button
                        type="button"
                        class="rounded-full bg-[#071c1b] px-4 py-2 text-sm font-medium text-white"
                        @click="form.post('/logout')"
                    >
                        Sign out
                    </button>
                </div>
            </header>

            <main class="px-4 py-8 sm:px-6">
                <Transition name="page" mode="out-in">
                    <div :key="current" class="page-shell">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>
    </div>
</template>
