<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

defineProps({ products: Array, frequencies: Array });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const form = useForm({
    name: '', finance_mode: page.props.tenant?.offers_islamic && !page.props.tenant?.offers_conventional ? 'islamic' : 'conventional',
    frequency: 'monthly', minimum_amount: 5000, maximum_amount: 100000, default_amount: 20000,
});
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
</script>

<template>
    <Head title="Savings products" />
        <PageHeader eyebrow="Savings" title="Contribution products" subtitle="Plans members can save into." />
        <AppCard class="mt-8" title="New product">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="form.post(`${base}/savings/products`)">
                <FormField label="Name"><input v-model="form.name" :class="input" required></FormField>
                <FormField label="Mode">
                    <AppSelect v-model="form.finance_mode" :options="[{ value: 'conventional', label: 'Conventional' }, { value: 'islamic', label: 'Non-interest' }]" />
                </FormField>
                <FormField label="Frequency">
                    <AppSelect v-model="form.frequency" :options="[{ value: 'monthly', label: 'Monthly' }, { value: 'weekly', label: 'Weekly' }, { value: 'daily', label: 'Daily' }, { value: 'yearly', label: 'Yearly' }, { value: 'one_time', label: 'One-time' }]" />
                </FormField>
                <FormField label="Default amount (₦)"><AmountInput v-model="form.default_amount" :class="input" /></FormField>
                <FormField label="Minimum (₦)"><AmountInput v-model="form.minimum_amount" :class="input" /></FormField>
                <FormField label="Maximum (₦)"><AmountInput v-model="form.maximum_amount" :class="input" /></FormField>
                <div class="sm:col-span-2"><AppButton type="submit" :disabled="form.processing">Save product</AppButton></div>
            </form>
        </AppCard>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <EmptyState v-if="!products.length" icon="products" title="No savings products yet" body="Create a plan — monthly thrift, target savings, or a locked product — then add member mandates." />
            <AppCard v-for="p in products" :key="p.uuid" :title="p.name">
                <p class="text-sm capitalize text-slate-500">{{ p.frequency }} · {{ p.finance_mode === 'islamic' ? 'Non-interest' : (p.finance_mode_label || p.finance_mode) }}</p>
            </AppCard>
        </div>
</template>
