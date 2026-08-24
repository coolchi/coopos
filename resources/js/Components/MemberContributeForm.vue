<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import FormField from './FormField.vue';
import AppButton from './AppButton.vue';
import AppSelect from './AppSelect.vue';
import AmountInput from './AmountInput.vue';
import { parseAmount } from '../money';

const props = defineProps({
    products: { type: Array, default: () => [] },
    mandates: { type: Array, default: () => [] },
    submitLabel: { type: String, default: '' },
    showCancel: { type: Boolean, default: true },
    wallet: { type: String, default: '' },
    walletNaira: { type: [Number, String], default: null },
});

const emit = defineEmits(['success', 'cancel']);

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium tabular-nums text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20 text-left';

const productByUuid = (uuid) => (props.products || []).find((product) => product.uuid === uuid);

const blankLine = (product = null) => ({
    product_uuid: product?.uuid || product?.product_uuid || '',
    amount: product?.amount_naira ?? product?.default_amount ?? '',
});

const seedLines = () => {
    const fromMandates = (props.mandates || [])
        .filter((mandate) => productByUuid(mandate.product_uuid))
        .map((mandate) => blankLine({
            uuid: mandate.product_uuid,
            default_amount: mandate.amount_naira ?? productByUuid(mandate.product_uuid)?.default_amount,
        }));

    if (fromMandates.length) {
        return fromMandates;
    }

    const first = (props.products || [])[0];

    return [blankLine(first)];
};

const form = useForm({
    payments: seedLines(),
});

watch(() => props.products, () => {
    if (! form.payments.length) {
        form.payments = seedLines();
    }
}, { deep: true });

const productOptionsFor = (index) => {
    const selected = new Set(
        form.payments
            .map((line, i) => (i === index ? null : line.product_uuid))
            .filter(Boolean),
    );

    return (props.products || [])
        .filter((product) => ! selected.has(product.uuid))
        .map((product) => ({
            value: product.uuid,
            label: product.is_required ? `${product.name} *` : product.name,
        }));
};

const canAddProduct = computed(() => {
    const used = new Set(form.payments.map((line) => line.product_uuid).filter(Boolean));

    return (props.products || []).some((product) => ! used.has(product.uuid));
});

const addLine = () => {
    const used = new Set(form.payments.map((line) => line.product_uuid).filter(Boolean));
    const next = (props.products || []).find((product) => ! used.has(product.uuid));
    form.payments.push(blankLine(next));
};

const removeLine = (index) => {
    if (form.payments.length === 1) {
        form.payments = [blankLine()];
        return;
    }
    form.payments.splice(index, 1);
};

const onProductChange = (line) => {
    const product = productByUuid(line.product_uuid);
    if (! product) {
        return;
    }
    if (product.amount_is_fixed && product.default_amount != null) {
        line.amount = product.default_amount;
        return;
    }
    if (product.default_amount && (line.amount === '' || line.amount == null)) {
        line.amount = product.default_amount;
    }
};

const isFixedAmount = (line) => Boolean(productByUuid(line.product_uuid)?.amount_is_fixed);

const filledCount = computed(() => form.payments.filter((line) => line.product_uuid && Number(parseAmount(line.amount)) > 0).length);

const totalNaira = computed(() => form.payments.reduce((sum, line) => sum + (Number(parseAmount(line.amount)) || 0), 0));

const walletLimit = computed(() => {
    if (props.walletNaira === null || props.walletNaira === '' || props.walletNaira === undefined) {
        return null;
    }

    return Number(props.walletNaira);
});

const overWallet = computed(() => walletLimit.value != null && totalNaira.value > walletLimit.value);

const submitText = computed(() => {
    if (props.submitLabel) {
        return props.submitLabel;
    }
    const count = form.payments.length;

    return count > 1 ? `Pay ${count} contributions` : 'Pay contribution';
});

const submit = () => form
    .transform((data) => ({
        payments: data.payments
            .filter((line) => line.product_uuid && Number(parseAmount(line.amount)) > 0)
            .map((line) => ({
                product_uuid: line.product_uuid,
                amount: parseAmount(line.amount),
            })),
    }))
    .post(`${base}/portal/contribute`, {
        onSuccess: () => {
            emit('success');
            form.reset();
            form.payments = seedLines();
        },
        onFinish: () => form.transform((data) => data),
    });
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <p v-if="wallet" class="text-sm text-slate-600">
            Wallet balance <span class="font-semibold tabular-nums text-slate-900">{{ wallet }}</span>.
            Contributions are posted for this month from your wallet.
        </p>
        <p v-if="overWallet" class="text-sm font-medium text-rose-700">This total is more than your wallet balance. Transfer to your virtual account first.</p>

        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Products</h3>
                <button
                    type="button"
                    class="text-sm font-medium text-teal-800 hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
                    :disabled="!canAddProduct"
                    @click="addLine"
                >
                    Add product
                </button>
            </div>
            <p v-if="form.errors.payments" class="mb-2 text-xs font-medium text-rose-700">{{ form.errors.payments }}</p>
            <div class="space-y-2">
                <div class="grid grid-cols-[minmax(0,1fr)_6.75rem_2.25rem] gap-2 px-0.5 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-[minmax(0,1fr)_8.5rem_2.5rem] sm:text-xs">
                    <span>Product</span>
                    <span>Amount (₦)</span>
                    <span class="sr-only">Remove</span>
                </div>
                <div v-for="(line, index) in form.payments" :key="index" class="space-y-1">
                    <div class="grid grid-cols-[minmax(0,1fr)_6.75rem_2.25rem] items-start gap-2 sm:grid-cols-[minmax(0,1fr)_8.5rem_2.5rem]">
                        <FormField class="min-w-0 sm:mb-0" :error="form.errors[`payments.${index}.product_uuid`]">
                            <AppSelect
                                v-model="line.product_uuid"
                                placeholder="Select product"
                                :options="productOptionsFor(index)"
                                required
                                size="sm"
                                @change="onProductChange(line)"
                            />
                        </FormField>
                        <FormField class="min-w-0 sm:mb-0" :error="form.errors[`payments.${index}.amount`]">
                            <AmountInput
                                v-model="line.amount"
                                :readonly="isFixedAmount(line)"
                                :class="[input + ' !rounded-xl !px-2.5 !py-2', isFixedAmount(line) ? ' bg-slate-100 text-slate-700' : ''].join('')"
                                required
                            />
                        </FormField>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center self-start rounded-xl text-rose-600 hover:bg-rose-50 sm:h-11 sm:w-10"
                            title="Remove"
                            aria-label="Remove product line"
                            @click="removeLine(index)"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <AppButton v-if="showCancel" type="button" variant="secondary" @click="emit('cancel')">Cancel</AppButton>
            <AppButton type="submit" variant="gold" :disabled="form.processing || !form.payments.length || !filledCount || overWallet">
                {{ submitText }}
            </AppButton>
        </div>
    </form>
</template>
