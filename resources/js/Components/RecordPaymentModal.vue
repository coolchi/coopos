<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import AppButton from './AppButton.vue';
import AmountInput from './AmountInput.vue';
import { parseAmount } from '../money';
import { toast } from '../toast';

const props = defineProps({
    show: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    required_products: { type: Array, default: () => [] },
    member_plans: { type: Object, default: () => ({}) },
    member_paid: { type: Object, default: () => ({}) },
    member_facilities: { type: Object, default: () => ({}) },
    memberUuid: { type: String, default: '' },
    memberName: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium tabular-nums text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20 text-left';

const currentMonth = () => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const today = () => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const monthOptions = computed(() => {
    const now = new Date();
    const options = [];
    for (let i = 11; i >= 0; i -= 1) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        options.push({
            value,
            label: date.toLocaleString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }

    return options;
});

const fiscalStartMonth = computed(() => Number(page.props.financial_year?.start_month || 1));

const fiscalYearForMonth = (ym) => {
    if (! ym) {
        return null;
    }
    const [year, month] = String(ym).split('-').map(Number);
    if (! year || ! month) {
        return null;
    }

    return month >= fiscalStartMonth.value ? year : year - 1;
};

const blankLine = (product = null) => ({
    kind: 'product',
    select_value: product?.uuid ? `product:${product.uuid}` : '',
    product_uuid: product?.uuid || '',
    facility_uuid: '',
    amount: product?.default_amount ?? '',
    channel: 'transfer',
});

const blankFacility = (facility) => ({
    kind: facility.kind,
    select_value: `${facility.kind}:${facility.uuid}`,
    product_uuid: '',
    facility_uuid: facility.uuid,
    amount: facility.default_amount ?? '',
    channel: 'transfer',
});

const form = useForm({
    member_uuid: '',
    for_month: currentMonth(),
    paid_on: today(),
    payments: [blankLine()],
});

const locked = computed(() => Boolean(props.memberUuid));
const selectedMemberUuid = computed(() => props.memberUuid || form.member_uuid);

const isAlreadyPaid = (product) => {
    const memberKey = selectedMemberUuid.value;
    if (! memberKey || ! product?.uuid) {
        return false;
    }
    const paid = props.member_paid?.[memberKey];
    if (! paid) {
        return false;
    }
    if (product.frequency === 'one_time') {
        return (paid.one_time || []).includes(product.uuid);
    }
    if (product.frequency === 'yearly') {
        const fy = String(fiscalYearForMonth(form.for_month) ?? '');
        return Boolean(fy && (paid.yearly?.[fy] || []).includes(product.uuid));
    }

    return false;
};

const memberFacilities = computed(() => props.member_facilities?.[selectedMemberUuid.value] || []);

const hasMdsLoan = computed(() => memberFacilities.value.some((facility) => facility.is_mds));

const isLoanLinkedProduct = (product) => {
    if (! product) {
        return false;
    }
    if (product.bucket === 'mds') {
        return true;
    }

    return String(product.name || '').trim().toLowerCase() === 'mds';
};

const isFacilityPaid = (facility) => {
    if (! facility) {
        return false;
    }

    return (facility.paid_months || []).includes(form.for_month);
};

const availableFacilities = computed(() => memberFacilities.value.filter((facility) => ! isFacilityPaid(facility)));

const addableFacilities = computed(() => memberFacilities.value);

const isFacilityLine = (line) => Boolean(line?.kind && line.kind !== 'product');

const availableProducts = computed(() => {
    const map = new Map();
    (props.required_products || []).forEach((product) => {
        map.set(product.uuid, { ...product, is_required: true });
    });
    const enrolled = props.member_plans?.[selectedMemberUuid.value] || [];
    enrolled.forEach((product) => {
        map.set(product.uuid, {
            ...product,
            is_required: Boolean(map.get(product.uuid)?.is_required || product.is_required),
            enrolled: true,
        });
    });
    if (! map.size && (props.products || []).length) {
        (props.products || []).forEach((product) => map.set(product.uuid, product));
    }
    if (hasMdsLoan.value) {
        (props.products || []).forEach((product) => {
            if (! isLoanLinkedProduct(product)) {
                return;
            }
            map.set(product.uuid, {
                ...product,
                ...map.get(product.uuid),
                is_required: true,
            });
        });
    }

    return [...map.values()]
        .filter((product) => ! isAlreadyPaid(product))
        .filter((product) => hasMdsLoan.value || ! isLoanLinkedProduct(product));
});

const addableProducts = computed(() => {
    const map = new Map();
    availableProducts.value.forEach((product) => map.set(product.uuid, product));
    (props.products || []).forEach((product) => {
        if (isAlreadyPaid(product) || (! hasMdsLoan.value && isLoanLinkedProduct(product))) {
            return;
        }
        const existing = map.get(product.uuid);
        map.set(product.uuid, {
            ...product,
            ...existing,
            is_required: Boolean(existing?.is_required || product.is_required),
        });
    });

    return [...map.values()];
});

const itemOptionsFor = (index) => {
    const selectedProducts = new Set(
        form.payments
            .map((line, i) => (i === index || isFacilityLine(line) ? null : line.product_uuid))
            .filter(Boolean),
    );
    const selectedFacilities = new Set(
        form.payments
            .map((line, i) => (i === index || ! isFacilityLine(line) ? null : line.facility_uuid))
            .filter(Boolean),
    );

    const products = addableProducts.value
        .filter((product) => ! selectedProducts.has(product.uuid))
        .map((product) => ({
            value: `product:${product.uuid}`,
            label: product.is_required ? `${product.name} *` : product.name,
        }));

    const facilities = addableFacilities.value
        .filter((facility) => ! selectedFacilities.has(facility.uuid))
        .map((facility) => ({
            value: `${facility.kind}:${facility.uuid}`,
            label: facility.name,
        }));

    return [...products, ...facilities];
};

const syncPayments = () => {
    if (! selectedMemberUuid.value) {
        form.payments = [];
        return;
    }
    const products = availableProducts.value.map((product) => blankLine(product));
    const facilities = availableFacilities.value.map((facility) => blankFacility(facility));
    const lines = [...products, ...facilities];
    form.payments = lines.length ? lines : [blankLine()];
};

watch(() => props.show, (open) => {
    if (! open) {
        return;
    }
    form.clearErrors();
    form.member_uuid = props.memberUuid || '';
    form.for_month = currentMonth();
    form.paid_on = today();
    syncPayments();
});

watch(selectedMemberUuid, () => {
    if (props.show) {
        syncPayments();
    }
});

watch(() => form.for_month, () => {
    if (props.show && selectedMemberUuid.value) {
        syncPayments();
    }
});

const addLine = () => {
    const usedProducts = new Set(form.payments.map((line) => line.product_uuid).filter(Boolean));
    const usedFacilities = new Set(form.payments.map((line) => line.facility_uuid).filter(Boolean));
    const nextProduct = addableProducts.value.find((product) => ! usedProducts.has(product.uuid));
    if (nextProduct) {
        form.payments.push(blankLine(nextProduct));
        return;
    }
    const nextFacility = addableFacilities.value.find((facility) => ! usedFacilities.has(facility.uuid));
    form.payments.push(nextFacility ? blankFacility(nextFacility) : blankLine());
};

const removeLine = (index) => {
    if (form.payments.length === 1) {
        form.payments = [blankLine()];
        return;
    }
    form.payments.splice(index, 1);
};

const lineProduct = (line) => addableProducts.value.find((p) => p.uuid === line.product_uuid);

const lineFacility = (line) => addableFacilities.value.find((f) => f.uuid === line.facility_uuid && f.kind === line.kind);

const lineLabel = (line, index) => {
    if (isFacilityLine(line)) {
        const facility = lineFacility(line);
        return facility ? facility.name : `Row ${index + 1}`;
    }
    const product = lineProduct(line);

    return product?.name || `Row ${index + 1}`;
};

const isFixedAmount = (line) => ! isFacilityLine(line) && Boolean(lineProduct(line)?.amount_is_fixed);

const onItemChange = (line) => {
    const [kind, ...rest] = String(line.select_value || '').split(':');
    const uuid = rest.join(':');
    if (! kind || ! uuid) {
        line.kind = 'product';
        line.product_uuid = '';
        line.facility_uuid = '';
        return;
    }
    if (kind === 'product') {
        line.kind = 'product';
        line.product_uuid = uuid;
        line.facility_uuid = '';
        const product = lineProduct(line);
        if (! product) {
            return;
        }
        if (product.amount_is_fixed && product.default_amount != null) {
            line.amount = product.default_amount;
            return;
        }
        if (product.default_amount && (line.amount === '' || line.amount === null || line.amount === undefined)) {
            line.amount = product.default_amount;
        }
        return;
    }
    line.kind = kind;
    line.product_uuid = '';
    line.facility_uuid = uuid;
    const facility = lineFacility(line);
    if (facility && (line.amount === '' || line.amount === null || line.amount === undefined)) {
        line.amount = facility.default_amount;
    }
};

const canAddProduct = computed(() => {
    const usedProducts = new Set(form.payments.map((line) => line.product_uuid).filter(Boolean));
    const usedFacilities = new Set(form.payments.map((line) => line.facility_uuid).filter(Boolean));

    return addableProducts.value.some((product) => ! usedProducts.has(product.uuid))
        || addableFacilities.value.some((facility) => ! usedFacilities.has(facility.uuid));
});

const friendlyFieldError = (message, field) => {
    if (! message) {
        return '';
    }
    const text = String(message);
    const lower = text.toLowerCase();

    if (field === 'amount') {
        if (lower.includes('at least') || lower.includes('min')) {
            return 'Enter at least ₦1.';
        }
        if (lower.includes('required')) {
            return 'Enter an amount.';
        }
        if (lower.includes('numeric') || lower.includes('number')) {
            return 'Enter a valid amount.';
        }
    }

    if (field === 'product_uuid' || field === 'facility_uuid') {
        if (lower.includes('required')) {
            return 'Select a product.';
        }

        return 'Choose a valid product.';
    }

    if (field === 'channel') {
        return 'Choose Cash or Transfer.';
    }

    return text
        .replace(/^The payments\.\d+\.[a-z_]+ field /i, '')
        .replace(/^The /i, '')
        .replace(/\.$/, '');
};

const lineError = (index) => {
    const fields = ['product_uuid', 'facility_uuid', 'amount', 'channel'];
    for (const field of fields) {
        const raw = form.errors[`payments.${index}.${field}`];
        if (raw) {
            return friendlyFieldError(raw, field);
        }
    }

    return '';
};

const paymentErrors = computed(() => form.payments
    .map((line, index) => {
        const message = lineError(index);
        if (! message) {
            return null;
        }

        return { index, label: lineLabel(line, index), message };
    })
    .filter(Boolean));

const onAmountInput = (line, index) => {
    if (isFixedAmount(line)) {
        return;
    }
    form.clearErrors(`payments.${index}.amount`);
};

const submit = () => form
    .transform((data) => ({
        member_uuid: data.member_uuid,
        for_month: data.for_month,
        paid_on: data.paid_on,
        payments: data.payments.map((line) => ({
            kind: line.kind || 'product',
            product_uuid: ! line.kind || line.kind === 'product' ? (line.product_uuid || null) : null,
            facility_uuid: line.kind && line.kind !== 'product' ? (line.facility_uuid || null) : null,
            amount: parseAmount(line.amount),
            channel: line.channel,
        })),
    }))
    .post(`${base}/contributions`, {
        onSuccess: (page) => {
            toast.success(page.props.flash?.success || 'Payment recorded.');
            emit('close');
        },
        onError: (errors) => {
            const first = Object.values(errors || {})[0];
            toast.error(Array.isArray(first) ? first[0] : (first || 'Could not record payment.'));
        },
        onFinish: () => form.transform((data) => data),
    });
</script>

<template>
    <Modal :show="show" title="Record payment" subtitle="Post savings for the month they belong to." wide @close="emit('close')">
        <form class="space-y-5" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField v-if="locked" label="Member">
                        <div class="rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                            {{ memberName }}
                        </div>
                    </FormField>
                    <FormField v-else label="Member" :error="form.errors.member_uuid">
                        <AppSelect v-model="form.member_uuid" placeholder="Select member" :options="members" option-value="uuid" searchable required />
                    </FormField>
                    <FormField label="Month" :error="form.errors.for_month">
                        <AppSelect v-model="form.for_month" :options="monthOptions" required />
                    </FormField>
                    <FormField label="Date" :error="form.errors.paid_on">
                        <input v-model="form.paid_on" type="date" :class="input" required>
                    </FormField>
                </div>
            </section>

            <section v-if="selectedMemberUuid" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
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
                <p v-if="!availableProducts.length && !availableFacilities.length && !addableProducts.length && !addableFacilities.length" class="text-sm text-slate-500">
                    No products left to record for this member and month. One-time fees already paid, or yearly fees paid this financial year, are hidden.
                </p>
                <p v-if="form.errors.payments" class="mb-2 text-xs font-medium text-rose-700">{{ form.errors.payments }}</p>
                <div
                    v-if="paymentErrors.length"
                    class="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-800"
                >
                    <p class="font-semibold">Fix the highlighted amounts before recording.</p>
                    <ul class="mt-1.5 space-y-0.5 text-xs">
                        <li v-for="item in paymentErrors" :key="item.index">
                            <span class="font-medium">{{ item.label }}:</span> {{ item.message }}
                        </li>
                    </ul>
                </div>
                <div v-if="form.payments.length && (availableProducts.length || addableProducts.length || availableFacilities.length || addableFacilities.length)" class="space-y-2">
                    <div class="hidden grid-cols-[minmax(0,1.4fr)_7.5rem_8.5rem_2.5rem] gap-2 px-0.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid">
                        <span class="text-left">Product</span>
                        <span class="text-left">Amount (₦)</span>
                        <span class="text-left">Channel</span>
                        <span class="sr-only">Remove</span>
                    </div>
                    <div
                        v-for="(line, index) in form.payments"
                        :key="index"
                        class="space-y-1"
                    >
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1.4fr)_7.5rem_8.5rem_2.5rem] sm:items-start">
                            <FormField class="sm:mb-0">
                                <div
                                    v-if="isFacilityLine(line)"
                                    class="flex min-h-9 items-center truncate rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900"
                                >
                                    {{ lineLabel(line, index) }}
                                </div>
                                <AppSelect
                                    v-else
                                    v-model="line.select_value"
                                    placeholder="Select product"
                                    :options="itemOptionsFor(index)"
                                    required
                                    size="sm"
                                    @change="onItemChange(line)"
                                />
                            </FormField>
                            <FormField class="sm:mb-0">
                                <AmountInput
                                    v-model="line.amount"
                                    :readonly="isFixedAmount(line)"
                                    :aria-invalid="form.errors[`payments.${index}.amount`] ? 'true' : undefined"
                                    :title="form.errors[`payments.${index}.amount`] ? friendlyFieldError(form.errors[`payments.${index}.amount`], 'amount') : undefined"
                                    :class="[
                                        input + ' !rounded-xl !py-2',
                                        isFixedAmount(line) ? ' bg-slate-100 text-slate-700' : '',
                                        form.errors[`payments.${index}.amount`] ? ' border-rose-400 ring-2 ring-rose-200 focus:border-rose-500 focus:ring-rose-200' : '',
                                    ].join('')"
                                    required
                                    @update:model-value="onAmountInput(line, index)"
                                />
                            </FormField>
                            <FormField class="sm:mb-0">
                                <AppSelect
                                    v-model="line.channel"
                                    size="sm"
                                    :options="[
                                        { value: 'cash', label: 'Cash' },
                                        { value: 'transfer', label: 'Transfer' },
                                    ]"
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

            <div
                v-else
                class="flex flex-col items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-6 py-12 text-center"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-teal-800 shadow-sm ring-1 ring-slate-200">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-900">Choose a member first</p>
                <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-slate-500">
                    Their required products, mandates, and any outstanding loan repayments will appear here so you can post the month’s payments.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton type="button" variant="secondary" @click="emit('close')">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="form.processing || !selectedMemberUuid || !form.payments.length">
                    Record {{ form.payments.length > 1 ? `${form.payments.length} payments` : 'payment' }}
                </AppButton>
            </div>
        </form>
    </Modal>
</template>
