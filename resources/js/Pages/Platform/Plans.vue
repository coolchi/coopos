<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AmountInput from '../../Components/AmountInput.vue';

const props = defineProps({ plans: Array });
const forms = reactive({});
props.plans.forEach((plan) => {
    forms[plan.uuid] = useForm({
        monthly_price_naira: plan.monthly_price_naira,
        yearly_price_naira: plan.yearly_price_naira,
        member_limit: plan.member_limit,
        is_active: plan.is_active,
        description: plan.description,
    });
});

const formatNumber = (value) => {
    if (value === '' || value === null || value === undefined) {
        return '';
    }

    const digits = String(value).replace(/[^\d]/g, '');
    if (! digits) {
        return '';
    }

    return Number(digits).toLocaleString('en-NG');
};

const parseNumber = (value) => {
    const digits = String(value).replace(/[^\d]/g, '');

    return digits === '' ? '' : Number(digits);
};

const input = 'mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm tabular-nums';
</script>

<template>
            <Head title="Plans" />
        <h1 class="text-3xl font-semibold tracking-tight">Plans</h1>
        <p class="mt-2 max-w-2xl text-slate-500">SaaS catalogue for cooperatives. Prices are billed in naira; the ledger still stores kobo.</p>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <form
                v-for="plan in plans"
                :key="plan.uuid"
                class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5"
                @submit.prevent="forms[plan.uuid].transform((data) => ({ ...data, is_active: Boolean(data.is_active), member_limit: data.member_limit || null })).post(`/platform/plans/${plan.uuid}`)"
            >
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase tracking-[0.2em] text-teal-800">{{ plan.slug }}</div>
                    <span class="text-xs tabular-nums text-slate-500">{{ plan.subscribers.toLocaleString('en-NG') }} subscribers</span>
                </div>
                <h2 class="mt-2 text-2xl font-semibold">{{ plan.name }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ plan.monthly_price }} / month</p>
                <label class="mt-5 block text-xs text-slate-500">Monthly ₦</label>
                <AmountInput
                    v-model="forms[plan.uuid].monthly_price_naira"
                    :class="input"
                />
                <label class="mt-3 block text-xs text-slate-500">Yearly ₦</label>
                <AmountInput
                    v-model="forms[plan.uuid].yearly_price_naira"
                    :class="input"
                />
                <label class="mt-3 block text-xs text-slate-500">Member cap</label>
                <input
                    :value="formatNumber(forms[plan.uuid].member_limit)"
                    inputmode="numeric"
                    autocomplete="off"
                    :class="input"
                    placeholder="Unlimited"
                    @input="forms[plan.uuid].member_limit = parseNumber($event.target.value)"
                >
                <label class="mt-3 block text-xs text-slate-500">Description</label>
                <textarea v-model="forms[plan.uuid].description" rows="2" class="mt-1 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm"></textarea>
                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input v-model="forms[plan.uuid].is_active" type="checkbox" class="rounded"> Active for new sign-ups
                </label>
                <p v-if="forms[plan.uuid].errors.monthly_price_naira" class="mt-2 text-xs text-rose-700">{{ forms[plan.uuid].errors.monthly_price_naira }}</p>
                <p v-if="forms[plan.uuid].recentlySuccessful" class="mt-3 text-xs text-teal-800">Saved</p>
                <button class="mt-5 w-full rounded-2xl bg-[#071c1b] py-2.5 text-sm text-white" :disabled="forms[plan.uuid].processing">Save plan</button>
            </form>
        </div>
</template>
