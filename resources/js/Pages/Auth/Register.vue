<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    modes: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    trial_days: { type: Number, default: 14 },
    default_plan: { type: String, default: 'starter' },
});

const form = useForm({
    admin_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    name: '',
    type: props.types?.[0]?.value || 'staff',
    mode: 'general',
    state: 'Lagos',
    plan: props.default_plan || props.plans?.[0]?.slug || 'starter',
});

const showPassword = ref(false);
const input = 'w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm outline-none placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/15';

const submit = () => form.post('/register');
</script>

<template>
    <div class="min-h-screen bg-[#f4f1ea] text-slate-900 lg:grid lg:grid-cols-[minmax(20rem,38%)_1fr]">
        <Head title="Create a workspace" />

        <aside class="relative hidden overflow-hidden bg-[#071c1b] text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:justify-between lg:px-12 lg:py-12 xl:px-16">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(212,176,106,0.18),transparent_42%),radial-gradient(circle_at_bottom_right,rgba(20,184,166,0.16),transparent_46%)]"></div>
            <div class="relative">
                <Link href="/" class="inline-flex items-center gap-3">
                    <img src="/images/coopos-logo.png" alt="CoopOS" class="h-11 w-11 rounded-2xl">
                    <span>
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.28em] text-[#d4b06a]">CoopOS</span>
                        <span class="block text-lg font-semibold tracking-tight">Nigerian cooperatives</span>
                    </span>
                </Link>
            </div>
            <div class="relative max-w-md">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#d4b06a]">New workspace</p>
                <h1 class="mt-4 text-4xl font-semibold leading-tight tracking-tight">Register your cooperative</h1>
                <p class="mt-4 text-sm leading-6 text-teal-100/75">
                    Create a sealed workspace for members, savings, and financing. You can invite officers after you sign in.
                </p>
                <ul class="mt-8 space-y-3 text-sm text-teal-50/80">
                    <li class="flex gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#d4b06a]"></span>{{ trial_days }}-day trial on every plan</li>
                    <li class="flex gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#d4b06a]"></span>General, Islamic, or hybrid books</li>
                    <li class="flex gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#d4b06a]"></span>You become the first administrator</li>
                </ul>
            </div>
            <p class="relative text-xs text-teal-200/40">Already onboarded? Sign in from the form.</p>
        </aside>

        <main class="flex min-h-screen flex-col px-5 py-8 sm:px-8 lg:px-14 lg:py-12">
            <div class="mb-8 flex items-center justify-between lg:hidden">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <img src="/images/coopos-logo.png" alt="CoopOS" class="h-9 w-9 rounded-xl">
                    <span class="text-lg font-semibold tracking-tight text-[#071c1b]">CoopOS</span>
                </Link>
                <Link href="/login" class="text-sm font-medium text-teal-800">Sign in</Link>
            </div>

            <div class="mx-auto w-full max-w-xl flex-1">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">Start a cooperative</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-[#071c1b] sm:text-3xl">Create your workspace</h2>
                    <p class="mt-2 text-sm text-slate-500">Takes a few minutes. You can change products and officers later.</p>
                </div>

                <form class="mt-8 space-y-8" @submit.prevent="submit">
                    <section>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm sm:col-span-2">
                                <span class="mb-1.5 block font-semibold text-slate-800">Your name</span>
                                <input v-model="form.admin_name" required autocomplete="name" placeholder="Adewale Treasurer" :class="input">
                                <span v-if="form.errors.admin_name" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.admin_name }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-semibold text-slate-800">Work email</span>
                                <input v-model="form.email" type="email" required autocomplete="email" placeholder="you@cooperative.ng" :class="input">
                                <span v-if="form.errors.email" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.email }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-semibold text-slate-800">Phone <span class="font-normal text-slate-400">(optional)</span></span>
                                <input v-model="form.phone" type="tel" autocomplete="tel" placeholder="0803 000 0000" :class="input">
                                <span v-if="form.errors.phone" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.phone }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-semibold text-slate-800">Password</span>
                                <div class="relative">
                                    <input
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        minlength="8"
                                        autocomplete="new-password"
                                        placeholder="At least 8 characters"
                                        :class="input + ' pr-12'"
                                    >
                                    <button type="button" class="absolute inset-y-0 right-0 px-4 text-xs font-medium text-slate-500 hover:text-slate-800" @click="showPassword = !showPassword">
                                        {{ showPassword ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <span v-if="form.errors.password" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.password }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-semibold text-slate-800">Confirm password</span>
                                <input
                                    v-model="form.password_confirmation"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Repeat password"
                                    :class="input"
                                >
                            </label>
                        </div>
                    </section>

                    <section>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm sm:col-span-2">
                                <span class="mb-1.5 block font-semibold text-slate-800">Society name</span>
                                <input v-model="form.name" required placeholder="Al-Barakah Cooperative" :class="input">
                                <span v-if="form.errors.name" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.name }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-semibold text-slate-800">Type</span>
                                <AppSelect v-model="form.type" variant="outline" :options="types" option-value="value" option-label="label" required />
                                <span v-if="form.errors.type" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.type }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-semibold text-slate-800">State</span>
                                <AppSelect v-model="form.state" variant="outline" :options="states" required />
                                <span v-if="form.errors.state" class="mt-1 block text-xs font-medium text-rose-700">{{ form.errors.state }}</span>
                            </label>
                        </div>

                        <p class="mt-5 mb-2 text-sm font-semibold text-slate-800">Finance mode</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <button
                                v-for="mode in modes"
                                :key="mode.value"
                                type="button"
                                class="rounded-2xl border px-4 py-3 text-left transition"
                                :class="form.mode === mode.value ? 'border-teal-800 bg-teal-50/70 ring-2 ring-teal-800/15' : 'border-slate-200 bg-white hover:border-slate-300'"
                                @click="form.mode = mode.value"
                            >
                                <span class="block text-sm font-semibold text-slate-900">{{ mode.label }}</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ mode.hint }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.mode" class="mt-1 text-xs font-medium text-rose-700">{{ form.errors.mode }}</p>
                    </section>

                    <div>
                        <button
                            class="w-full rounded-2xl bg-[#d4b06a] py-3.5 text-sm font-semibold text-[#071c1b] shadow-sm transition hover:bg-[#c4a35a] disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Creating workspace…' : 'Create workspace' }}
                        </button>
                        <p class="mt-4 text-center text-sm text-slate-500">
                            Already have a workspace?
                            <Link href="/login" class="font-semibold text-teal-800 hover:underline">Sign in</Link>
                        </p>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>
