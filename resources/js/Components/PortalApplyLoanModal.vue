<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import FormField from './FormField.vue';
import AppButton from './AppButton.vue';
import AmountInput from './AmountInput.vue';
import Modal from './Modal.vue';
import EmptyState from './EmptyState.vue';
import LoanEligibilityChecklist from './LoanEligibilityChecklist.vue';
import { useLoanEligibility } from '../composables/useLoanEligibility';

function productRangeHint(product) {
    if (! product) {
        return '';
    }
    const amount = product.maximum ? `${product.minimum} – ${product.maximum}` : `From ${product.minimum}`;
    if (product.min_tenure && product.max_tenure) {
        return `${amount} · ${product.min_tenure}–${product.max_tenure} months`;
    }

    return amount;
}

const props = defineProps({
    show: { type: Boolean, default: false },
    loanProducts: { type: Array, default: () => [] },
    islamicProducts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const page = usePage();
const base = computed(() => page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`);
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const usesLoans = computed(() => Boolean(props.loanProducts?.length));
const qardProducts = computed(() => (props.islamicProducts ?? []).filter((p) => p.structure === 'qard_hasan'));

const loan = useForm({ product_uuid: '', amount: '', tenure_months: 6, purpose: '' });
const qard = useForm({ product_uuid: '', amount: '', tenure_months: 6, purpose: '' });

const {
    checks: loanChecks,
    loading: loanChecking,
    passed: loanEligible,
    ready: loanReady,
} = useLoanEligibility(null, () => loan.amount, { portal: true });
const {
    checks: qardChecks,
    loading: qardChecking,
    passed: qardEligible,
    ready: qardReady,
} = useLoanEligibility(null, () => qard.amount, { portal: true });

const selectedLoan = computed(() => props.loanProducts.find((p) => p.uuid === loan.product_uuid));
const selectedQard = computed(() => qardProducts.value.find((p) => p.uuid === qard.product_uuid));

watch(() => loan.product_uuid, (uuid) => {
    const product = props.loanProducts.find((p) => p.uuid === uuid);
    if (product?.min_naira) {
        loan.amount = product.min_naira;
        loan.tenure_months = product.min_tenure || 6;
    }
});

watch(() => qard.product_uuid, (uuid) => {
    const product = qardProducts.value.find((p) => p.uuid === uuid);
    if (product?.min_naira) {
        qard.amount = product.min_naira;
        qard.tenure_months = product.min_tenure || 6;
    }
});

const submit = () => {
    if (usesLoans.value) {
        loan.post(`${base.value}/portal/apply/loan`, {
            onSuccess: () => {
                emit('close');
                loan.reset();
                loan.tenure_months = 6;
            },
        });
        return;
    }
    qard.post(`${base.value}/portal/apply/qard`, {
        onSuccess: () => {
            emit('close');
            qard.reset();
            qard.tenure_months = 6;
        },
    });
};

const subtitle = computed(() => (usesLoans.value
    ? 'Submit an application for a conventional facility.'
    : 'Qard Hasan is a benevolent facility. No interest is charged.'));
</script>

<template>
    <Modal
        :show="show"
        title="Apply for a loan"
        :subtitle="subtitle"
        wide
        @close="emit('close')"
    >
        <form v-if="usesLoans" class="space-y-4" @submit.prevent="submit">
            <FormField label="Product" :error="loan.errors.product_uuid">
                <AppSelect v-model="loan.product_uuid" placeholder="Select a product" :options="loanProducts" option-value="uuid" option-label="name" variant="outline" required />
            </FormField>
            <FormField label="Amount (₦)" :error="loan.errors.amount" :hint="productRangeHint(selectedLoan)">
                <AmountInput v-model="loan.amount" :class="input" required />
            </FormField>
            <FormField label="Tenure (months)" :error="loan.errors.tenure_months">
                <input v-model="loan.tenure_months" type="number" min="1" :class="input" required>
            </FormField>
            <FormField label="Purpose">
                <input v-model="loan.purpose" :class="input">
            </FormField>
            <LoanEligibilityChecklist
                :checks="loanChecks"
                :loading="loanChecking"
                :ready="loanReady"
                :error="loan.errors.eligibility"
                hint="Enter an amount to see whether you meet loan conditions."
            />
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" @click="emit('close')">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="loan.processing || (loanReady && !loanEligible)">Submit application</AppButton>
            </div>
        </form>
        <form v-else-if="qardProducts.length" class="space-y-4" @submit.prevent="submit">
            <FormField label="Product" :error="qard.errors.product_uuid">
                <AppSelect v-model="qard.product_uuid" placeholder="Select a product" :options="qardProducts" option-value="uuid" option-label="name" variant="outline" required />
            </FormField>
            <FormField label="Amount (₦)" :error="qard.errors.amount" :hint="productRangeHint(selectedQard)">
                <AmountInput v-model="qard.amount" :class="input" required />
            </FormField>
            <FormField label="Tenure (months)" :error="qard.errors.tenure_months">
                <input v-model="qard.tenure_months" type="number" min="1" :class="input" required>
            </FormField>
            <FormField label="Purpose">
                <input v-model="qard.purpose" :class="input">
            </FormField>
            <LoanEligibilityChecklist
                :checks="qardChecks"
                :loading="qardChecking"
                :ready="qardReady"
                :error="qard.errors.eligibility"
                hint="Enter an amount to see whether you meet loan conditions."
            />
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" @click="emit('close')">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="qard.processing || (qardReady && !qardEligible)">Submit request</AppButton>
            </div>
        </form>
        <EmptyState v-else icon="loans" title="No loan products yet" body="Ask an officer to publish a financing product." />
    </Modal>
</template>
