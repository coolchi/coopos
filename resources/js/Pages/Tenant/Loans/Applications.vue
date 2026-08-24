<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import LoanEligibilityChecklist from '../../../Components/LoanEligibilityChecklist.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useLoanEligibility } from '../../../composables/useLoanEligibility';
import { useCan } from '../../../composables/useCan';

defineProps({ applications: Object, members: Array, products: Array });
const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const form = useForm({ member_uuid: '', product_uuid: '', amount: '', tenure_months: 6, purpose: '' });
const {
    checks: eligibilityChecks,
    loading: eligibilityLoading,
    passed: eligible,
    ready: eligibilityReady,
} = useLoanEligibility(() => form.member_uuid, () => form.amount);
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
const disburse = (uuid) => useForm({}).post(`${base}/loans/applications/${uuid}/disburse`);
</script>

<template>
    <Head title="Loan applications" />
        <PageHeader eyebrow="Financing" title="Loan applications" subtitle="Originate a request, then disburse once approved." />
        <AppCard v-if="can('loans.manage')" class="mt-8" title="New application">
            <form class="space-y-4" @submit.prevent="form.post(`${base}/loans/applications`)">
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                    <FormField label="Member">
                        <AppSelect v-model="form.member_uuid" placeholder="Select" :options="members" option-value="uuid" required />
                    </FormField>
                    <FormField label="Product">
                        <AppSelect v-model="form.product_uuid" placeholder="Select" :options="products" option-value="uuid" option-label="name" required />
                    </FormField>
                    <FormField label="Amount (₦)"><AmountInput v-model="form.amount" :class="input" required /></FormField>
                    <FormField label="Months"><input v-model="form.tenure_months" type="number" :class="input"></FormField>
                    <div class="flex items-end md:col-span-2 lg:col-span-1">
                        <AppButton type="submit" class="w-full" :disabled="form.processing || (eligibilityReady && !eligible)">Apply</AppButton>
                    </div>
                </div>
                <LoanEligibilityChecklist
                    :checks="eligibilityChecks"
                    :loading="eligibilityLoading"
                    :ready="eligibilityReady"
                    :error="form.errors.eligibility"
                />
            </form>
        </AppCard>
        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!applications.data.length" icon="loans" title="No applications yet" body="Loan requests from members wait here until they are reviewed and disbursed." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Member</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Tenure</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in applications.data" :key="a.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">{{ a.member }}</td>
                        <td class="px-5 py-4">{{ a.product }}</td>
                        <td class="px-5 py-4">{{ a.amount }}</td>
                        <td class="px-5 py-4">{{ a.tenure }} mo</td>
                        <td class="px-5 py-4"><StatusBadge :value="a.status" /></td>
                        <td class="px-5 py-4 text-right">
                            <button v-if="a.status === 'approved' && can('loans.disburse')" class="text-sm text-teal-800" @click="disburse(a.uuid)">Disburse</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer>
                <Pagination :links="applications.links" :from="applications.from" :to="applications.to" :total="applications.total" />
            </template>
        </AppCard>
</template>
