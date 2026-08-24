<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const tenant = computed(() => page.props.tenant);
const pageKey = computed(() => page.url.split('?')[0]);
</script>

<template>
    <div class="min-h-screen bg-coop-950 text-white">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <Link href="/" class="flex items-center gap-3">
                <img src="/images/coopos-logo.png" alt="CoopOS" class="h-9 w-9 rounded-xl">
                <span class="text-lg font-semibold tracking-tight">{{ tenant?.name || 'CoopOS' }}</span>
            </Link>
            <div class="flex gap-3 text-sm">
                <Link href="/login" class="rounded-lg px-3 py-2 text-teal-100 hover:bg-white/10">Sign in</Link>
                <Link
                    v-if="!tenant"
                    href="/register"
                    class="rounded-lg bg-gold-500 px-4 py-2 font-medium text-coop-950"
                >
                    Start your cooperative
                </Link>
            </div>
        </header>
        <main>
            <Transition name="page" mode="out-in">
                <div :key="pageKey" class="page-shell">
                    <slot />
                </div>
            </Transition>
        </main>
    </div>
</template>
