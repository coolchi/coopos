<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import NotificationBell from '../Components/NotificationBell.vue';

const page = usePage();
const base = computed(() => page.props.tenantBase ?? (page.props.tenant?.slug ? `/${page.props.tenant.slug}` : ''));
const current = computed(() => page.url.split('?')[0]);
const logout = useForm({});

defineProps({
    title: { type: String, default: '' },
});

const items = computed(() => [
    { href: `${base.value}/portal`, label: 'Home', icon: 'home' },
    { href: `${base.value}/portal/wallet`, label: 'Wallet', icon: 'wallet' },
    { href: `${base.value}/portal/loans`, label: 'Loan', icon: 'cash' },
    { href: `${base.value}/portal/statement`, label: 'Statement', icon: 'statement' },
]);

const isActive = (href) => {
    const path = current.value.replace(/\/$/, '');
    const target = href.replace(/\/$/, '');
    if (target.endsWith('/portal')) {
        return path === target || path === `${target}/`;
    }
    return path === target || path.startsWith(`${target}/`);
};

const iconSrc = (item, active) => `/images/portal/${item.icon}${active ? '-active' : ''}.png`;
</script>

<template>
    <div class="portal-app min-h-[100dvh] bg-slate-50 text-slate-900">
        <Head v-if="title" :title="title" />
        <header class="sticky top-0 z-20 border-b border-black/5 bg-ink pt-[env(safe-area-inset-top)] text-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <img :src="page.props.tenant?.logo_url || '/images/coopos-logo.png'" :alt="page.props.tenant?.name || 'CoopOS'" class="h-8 w-8 rounded-lg bg-white/10 object-contain sm:h-9 sm:w-9 sm:rounded-xl">
                    <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-[0.28em] text-gold sm:text-[11px]">Member portal</div>
                    <div class="truncate font-semibold">{{ page.props.tenant?.name }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <NotificationBell variant="dark" />
                    <button type="button" class="rounded-full bg-white/10 px-3 py-1.5 text-sm" @click="logout.post('/logout')">
                        Sign out
                    </button>
                </div>
            </div>
            <nav class="mx-auto hidden max-w-3xl gap-1 overflow-x-auto px-4 pb-3 text-sm sm:flex">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="shrink-0 whitespace-nowrap rounded-full px-3 py-1.5"
                    :class="isActive(item.href) ? 'bg-gold text-ink' : 'text-teal-100 hover:bg-white/10'"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </header>
        <main class="mx-auto max-w-3xl p-4 pb-[calc(5.5rem+env(safe-area-inset-bottom))] sm:p-6 sm:pb-16">
            <Transition name="page" mode="out-in">
                <div :key="current" class="page-shell">
                    <slot />
                </div>
            </Transition>
        </main>
        <nav
            class="fixed inset-x-0 bottom-0 z-30 border-t border-black/5 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_rgba(15,23,42,0.06)] backdrop-blur sm:hidden"
            aria-label="Member menu"
        >
            <div class="mx-auto grid max-w-3xl grid-cols-4">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="flex flex-col items-center gap-1 px-1 pb-2 pt-2.5 text-center"
                >
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-2xl"
                        :class="isActive(item.href) ? 'bg-ink/5' : ''"
                    >
                        <img
                            :src="iconSrc(item, isActive(item.href))"
                            :alt="item.label"
                            class="h-6 w-6 object-contain"
                            width="24"
                            height="24"
                        >
                    </span>
                    <span
                        class="text-[10px] leading-none"
                        :class="isActive(item.href) ? 'font-semibold text-ink' : 'font-medium text-slate-400'"
                    >{{ item.label }}</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
