<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object, plans: Array });
const form = useForm({ ...props.settings });

const save = () => {
    form.transform((data) => ({
        ...data,
        trial_days: Number(data.trial_days),
        allow_registration: Boolean(data.allow_registration),
        require_cooperative_approval: Boolean(data.require_cooperative_approval),
        subdomains_enabled: Boolean(data.subdomains_enabled),
        maintenance_mode: Boolean(data.maintenance_mode),
        allow_impersonation: Boolean(data.allow_impersonation),
        sms_enabled: Boolean(data.sms_enabled),
        islamic_finance_enabled: Boolean(data.islamic_finance_enabled),
        hybrid_mode_enabled: Boolean(data.hybrid_mode_enabled),
        api_enabled: Boolean(data.api_enabled),
    })).post('/platform/settings');
};
</script>

<template>
            <Head title="Settings" />
        <h1 class="text-3xl font-semibold tracking-tight">SAAS ADMIN settings</h1>
        <p class="mt-2 max-w-2xl text-slate-500">These controls apply to the whole CoopOS platform — tenancy, billing defaults, and product switches.</p>
        <p v-if="Object.keys(form.errors).length" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-900">Please fix the highlighted fields.</p>
        <p v-if="form.recentlySuccessful" class="mt-4 rounded-2xl bg-teal-50 px-4 py-3 text-sm text-teal-900">Settings saved.</p>

        <form class="mt-8 space-y-6" @submit.prevent="save">
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-lg font-semibold">Brand</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm">Platform name
                        <input v-model="form.platform_name" required class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                        <span v-if="form.errors.platform_name" class="mt-1 block text-xs text-rose-700">{{ form.errors.platform_name }}</span>
                    </label>
                    <label class="text-sm">Tagline
                        <input v-model="form.tagline" class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                    </label>
                    <label class="text-sm">Support email
                        <input v-model="form.support_email" type="email" required class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                        <span v-if="form.errors.support_email" class="mt-1 block text-xs text-rose-700">{{ form.errors.support_email }}</span>
                    </label>
                    <label class="text-sm">Support phone
                        <input v-model="form.support_phone" class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                    </label>
                    <label class="text-sm sm:col-span-2">From email
                        <input v-model="form.from_email" type="email" required class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                        <span v-if="form.errors.from_email" class="mt-1 block text-xs text-rose-700">{{ form.errors.from_email }}</span>
                    </label>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-lg font-semibold">Tenancy &amp; domains</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm">Central domain
                        <input v-model="form.central_domain" required class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                        <span v-if="form.errors.central_domain" class="mt-1 block text-xs text-rose-700">{{ form.errors.central_domain }}</span>
                    </label>
                    <label class="mt-6 flex items-center gap-2 text-sm"><input v-model="form.subdomains_enabled" type="checkbox" class="rounded"> Enable {slug}.coopos.test workspaces</label>
                </div>
                <p class="mt-3 text-xs text-slate-500">Example: Al-Barakah is served at barakah.coopos.test. Path URLs still work on 127.0.0.1.</p>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-lg font-semibold">Onboarding &amp; billing</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm">Trial days
                        <input v-model="form.trial_days" type="number" min="0" max="90" required class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3">
                        <span v-if="form.errors.trial_days" class="mt-1 block text-xs text-rose-700">{{ form.errors.trial_days }}</span>
                    </label>
                    <label class="text-sm">Default plan
                        <AppSelect v-model="form.default_plan" class="mt-1" :options="plans" option-value="slug" option-label="name" />
                    </label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.allow_registration" type="checkbox" class="rounded"> Allow self-serve registration</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.require_cooperative_approval" type="checkbox" class="rounded"> Require platform approval before go-live</label>
                    <label class="text-sm sm:col-span-2">Paystack public key
                        <input v-model="form.paystack_public_key" class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3" placeholder="pk_test_...">
                    </label>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="text-lg font-semibold">Product switches</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.islamic_finance_enabled" type="checkbox" class="rounded"> Islamic savings &amp; financing</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.hybrid_mode_enabled" type="checkbox" class="rounded"> Hybrid cooperatives</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.api_enabled" type="checkbox" class="rounded"> Public API</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.sms_enabled" type="checkbox" class="rounded"> SMS notifications</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.allow_impersonation" type="checkbox" class="rounded"> Allow impersonation</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.maintenance_mode" type="checkbox" class="rounded"> Maintenance banner</label>
                </div>
            </section>

            <button class="rounded-full bg-[#d4b06a] px-6 py-3 font-semibold text-[#071c1b]" :disabled="form.processing">Save settings</button>
        </form>
</template>
