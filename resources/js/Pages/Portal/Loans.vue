<script setup>
import AppCard from '../../Components/AppCard.vue';
import AppButton from '../../Components/AppButton.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PortalApplyLoanModal from '../../Components/PortalApplyLoanModal.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    wallet: String,
    applications: { type: Array, default: () => [] },
    loans: { type: Array, default: () => [] },
    islamic: { type: Array, default: () => [] },
    loan_products: { type: Array, default: () => [] },
    islamic_products: { type: Array, default: () => [] },
});

const applyOpen = ref(false);
const canApply = computed(() => Boolean(props.loan_products?.length) || (props.islamic_products ?? []).some((p) => p.structure === 'qard_hasan'));
</script>

<template>
    <Head title="Loan" />
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Loan</h1>
            <p class="mt-2 text-sm text-slate-500">Apply for a facility and track applications and balances.</p>
        </div>
        <AppButton v-if="canApply" type="button" variant="gold" @click="applyOpen = true">Apply for a loan</AppButton>
    </div>

    <AppCard class="mt-8" title="Applications" :padded="false">
        <EmptyState v-if="!applications.length" icon="loans" title="No applications yet" body="Submit a request when you meet the loan conditions." />
        <ul v-else class="divide-y divide-slate-100">
            <li v-for="row in applications" :key="row.uuid" class="flex items-center justify-between gap-4 px-5 py-4">
                <div>
                    <div class="font-semibold text-slate-900">{{ row.product }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ row.amount }} · {{ row.tenure }} months{{ row.submitted_at ? ` · ${row.submitted_at}` : '' }}</div>
                </div>
                <StatusBadge :value="row.status" />
            </li>
        </ul>
    </AppCard>

    <AppCard class="mt-6" title="Active loans" :padded="false">
        <EmptyState v-if="!loans.length && !islamic.length" icon="loans" title="No loans yet" body="Disbursed facilities will show here with outstanding balances." />
        <ul v-else class="divide-y divide-slate-100">
            <li v-for="row in loans" :key="row.uuid" class="flex items-center justify-between gap-4 px-5 py-4">
                <div>
                    <div class="font-semibold text-slate-900">{{ row.product }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ row.account_number }} · Principal {{ row.principal }}</div>
                </div>
                <div class="text-right">
                    <div class="font-semibold tabular-nums text-slate-900">{{ row.outstanding }}</div>
                    <StatusBadge class="mt-1" :value="row.status" />
                </div>
            </li>
            <li v-for="row in islamic" :key="row.uuid" class="flex items-center justify-between gap-4 px-5 py-4">
                <div>
                    <div class="font-semibold text-slate-900">{{ row.product }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ row.account_number }}</div>
                </div>
                <div class="text-right">
                    <div class="font-semibold tabular-nums text-slate-900">{{ row.outstanding }}</div>
                    <StatusBadge class="mt-1" :value="row.status" />
                </div>
            </li>
        </ul>
    </AppCard>

    <PortalApplyLoanModal
        :show="applyOpen"
        :loan-products="loan_products"
        :islamic-products="islamic_products"
        @close="applyOpen = false"
    />
</template>
