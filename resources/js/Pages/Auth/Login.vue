<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    cooperative: Object,
    demo_logins: { type: Array, default: () => [] },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const tenantName = computed(() => props.cooperative?.name || 'CoopOS');

const submit = () => form.post('/login');

const useDemo = (demo) => {
    form.email = demo.email;
    form.password = demo.password;
    form.remember = true;
    form.post('/login');
};
</script>

<template>
    <div class="min-h-screen bg-[#f4f1ea] text-slate-900 lg:grid lg:grid-cols-2">
        <Head :title="cooperative ? `Sign in to ${cooperative.name}` : 'Sign in'" />

        <aside class="relative hidden overflow-hidden bg-ink text-white lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-12 xl:px-16">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(212,176,106,0.18),transparent_42%),radial-gradient(circle_at_bottom_right,rgba(20,184,166,0.16),transparent_46%)]"></div>
            <div class="relative">
                <Link href="/" class="inline-flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl bg-gold">
                        <img
                            :src="cooperative?.logo_url || '/images/coopos-logo.png'"
                            :alt="tenantName"
                            class="h-full w-full object-contain"
                        >
                    </span>
                    <span>
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.28em] text-gold">CoopOS</span>
                        <span class="block text-lg font-semibold tracking-tight">{{ tenantName }}</span>
                    </span>
                </Link>
            </div>
            <div class="relative max-w-md">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold">
                    {{ cooperative ? 'Cooperative workspace' : 'Platform operators' }}
                </p>
                <h1 class="mt-4 text-4xl font-semibold leading-tight tracking-tight">
                    {{ cooperative ? 'Sign in to your society' : 'Sign in to CoopOS' }}
                </h1>
                <p class="mt-4 text-sm leading-6 text-teal-100/75">
                    {{ cooperative
                        ? 'Officers and members use the same sign-in. You will land in the staff desk or member portal automatically.'
                        : 'Central operators, society officers, and members sign in here. Your role decides where you go next.' }}
                </p>
                <div v-if="cooperative?.type" class="mt-8 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-white/10 px-3 py-1.5 text-teal-50">{{ cooperative.type }}</span>
                </div>
            </div>
            <p class="relative text-xs text-teal-200/40">{{ cooperative?.host || 'coopos.test' }}</p>
        </aside>

        <main class="flex min-h-screen flex-col px-5 py-8 sm:px-8 lg:px-16 lg:py-12">
            <div class="mb-10 flex items-center justify-between lg:hidden">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <img
                        :src="cooperative?.logo_url || '/images/coopos-logo.png'"
                        :alt="tenantName"
                        class="h-9 w-9 rounded-xl"
                    >
                    <span class="text-lg font-semibold tracking-tight text-ink">{{ tenantName }}</span>
                </Link>
            </div>

            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                <div class="lg:hidden">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">{{ cooperative ? 'Workspace' : 'CoopOS' }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink">Sign in</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ cooperative ? 'Staff and members use the same sign-in.' : 'Platform operators and cooperative officers.' }}</p>
                </div>
                <div class="hidden lg:block">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink">Welcome back</h2>
                    <p class="mt-2 text-sm text-slate-500">Enter your email and password to continue.</p>
                </div>

                <form class="mt-8 space-y-4" @submit.prevent="submit">
                    <label class="block text-sm">
                        <span class="mb-1.5 block font-semibold text-slate-800">Email</span>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="username"
                            placeholder="you@cooperative.ng"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm outline-none placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/15"
                        >
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1.5 block font-semibold text-slate-800">Password</span>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-sm font-medium text-slate-900 shadow-sm outline-none placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/15"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 px-4 text-xs font-medium text-slate-500 hover:text-slate-800"
                                @click="showPassword = !showPassword"
                            >{{ showPassword ? 'Hide' : 'Show' }}</button>
                        </div>
                    </label>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-teal-800 focus:ring-teal-800/20">
                            Remember me
                        </label>
                    </div>
                    <p v-if="form.errors.email" class="text-sm font-medium text-rose-700">{{ form.errors.email }}</p>
                    <p v-else-if="form.errors.password" class="text-sm font-medium text-rose-700">{{ form.errors.password }}</p>
                    <button
                        class="w-full rounded-2xl bg-gold py-3.5 text-sm font-semibold text-ink shadow-sm transition hover:opacity-90 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Signing in…' : 'Continue' }}
                    </button>
                </form>

                <div v-if="demo_logins.length" class="mt-10">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Demo accounts</p>
                    <div class="mt-3 grid gap-2">
                        <button
                            v-for="demo in demo_logins"
                            :key="demo.email"
                            type="button"
                            class="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-teal-800/30 hover:bg-teal-50/40"
                            :disabled="form.processing"
                            @click="useDemo(demo)"
                        >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">{{ demo.label }}</span>
                                <span class="block text-xs text-slate-500">{{ demo.hint }}</span>
                            </span>
                            <span class="text-xs font-semibold text-teal-800">Use</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
