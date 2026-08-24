<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

defineProps({ products: Array });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const form = useForm({
    name: 'Personal loan', minimum_amount: 50000, maximum_amount: 1000000,
    minimum_tenure_months: 1, maximum_tenure_months: 12, interest_method: 'reducing_balance',
    interest_rate: 12, processing_fee: 1000, guarantors_required: 1,
});
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
</script>

<template>
    <Head title="Loan products" />
        <PageHeader eyebrow="Financing" title="Loan products" />
        <AppCard class="mt-8" title="New product">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="form.post(`${base}/loans/products`)">
                <FormField label="Name"><input v-model="form.name" :class="input"></FormField>
                <FormField label="Interest method">
                    <AppSelect v-model="form.interest_method" :options="[{ value: 'flat', label: 'Flat' }, { value: 'reducing_balance', label: 'Reducing balance' }]" />
                </FormField>
                <FormField label="Minimum (₦)"><AmountInput v-model="form.minimum_amount" :class="input" /></FormField>
                <FormField label="Maximum (₦)"><AmountInput v-model="form.maximum_amount" :class="input" /></FormField>
                <FormField label="Rate % p.a."><input v-model="form.interest_rate" type="number" :class="input"></FormField>
                <FormField label="Guarantors"><input v-model="form.guarantors_required" type="number" :class="input"></FormField>
                <div class="sm:col-span-2"><AppButton type="submit">Save</AppButton></div>
            </form>
        </AppCard>
        <div class="mt-6 space-y-3">
            <EmptyState v-if="!products.length" icon="products" title="No loan products yet" body="Define tenure, rates, and limits so members can apply for a conventional loan." />
            <AppCard v-for="p in products" :key="p.uuid" :title="p.name">
                <p class="text-sm capitalize text-slate-500">{{ p.interest_method?.replace('_', ' ') }}</p>
            </AppCard>
        </div>
</template>
