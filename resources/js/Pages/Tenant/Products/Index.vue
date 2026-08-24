<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import StatCard from '../../../Components/StatCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { parseAmount } from '../../../money';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    products: Array,
    counts: { type: Object, default: () => ({}) },
    kinds: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    savings_products: { type: Array, default: () => [] },
    buckets: { type: Array, default: () => [] },
    monipay_configured: { type: Boolean, default: false },
    revenue_heads: { type: Array, default: () => [] },
    settlement_banks: { type: Array, default: () => [] },
    monipay_error: { type: String, default: null },
});
const page = usePage();
const { can } = useCan();
const tenant = computed(() => page.props.tenant);
const base = page.props.tenantBase ?? `/${tenant.value?.slug || ''}`;
const composeOpen = ref(false);
const enrollOpen = ref(false);
const editing = ref(null);
const filter = ref('all');
const query = ref('');
const revenueHeads = ref([...props.revenue_heads]);
const settlementBanks = ref([...props.settlement_banks]);
const monipayError = ref(props.monipay_error);
const monipayLoading = ref(false);
const monipayLoaded = ref(false);
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';
const moneyInput = `${input} text-left`;

const defaultKind = () => props.kinds[0]?.value || 'savings';
const defaultFinanceMode = () => (tenant.value?.offers_islamic && ! tenant.value?.offers_conventional ? 'islamic' : 'conventional');

const form = useForm({
    kind: defaultKind(),
    name: '',
    description: '',
    finance_mode: defaultFinanceMode(),
    bucket: 'savings',
    frequency: 'monthly',
    minimum_amount: '',
    maximum_amount: '',
    default_amount: '',
    amount_is_fixed: false,
    is_locked: false,
    is_required: false,
    revenue_head_id: '',
    new_revenue_head_name: '',
    settlement_bank_id: '',
    minimum_tenure_months: 1,
    maximum_tenure_months: 12,
    interest_method: 'reducing_balance',
    interest_rate: 12,
    processing_fee: 0,
    guarantors_required: 0,
    structure: 'qard_hasan',
    admin_fee: 0,
    default_profit_rate: 10,
});

const financeModeOptions = computed(() => {
    const options = [];
    if (tenant.value?.offers_conventional) {
        options.push({ value: 'conventional', label: 'Conventional' });
    }
    if (tenant.value?.offers_islamic) {
        options.push({ value: 'islamic', label: 'Non-interest' });
    }

    return options.length ? options : [{ value: 'conventional', label: 'Conventional' }];
});

const frequencyOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
    { value: 'one_time', label: 'One-time' },
];

const isOneTime = computed(() => form.frequency === 'one_time');
const allowsWithdrawal = computed(() => form.bucket === 'sts');

const applyBucketDefaults = (bucket) => {
    const rules = {
        registration_fee: { frequency: 'one_time', is_required: true, is_locked: true },
        insurance: { frequency: 'one_time', is_required: true, is_locked: true },
        foundation_fee: { frequency: 'one_time', is_required: true, is_locked: true },
        development_levy: { frequency: 'yearly', is_required: true, is_locked: true },
        agm: { frequency: 'yearly', is_required: true, is_locked: true },
        savings: { frequency: 'monthly', is_required: true, is_locked: true },
        mds: { frequency: 'monthly', is_required: false, is_locked: true },
        sts: { frequency: 'monthly', is_required: false, is_locked: false },
        share: { frequency: 'monthly', is_required: true, is_locked: true },
    };
    const rule = rules[bucket];
    if (! rule) {
        return;
    }
    form.frequency = rule.frequency;
    form.is_required = rule.is_required;
    form.is_locked = rule.is_locked;
    if (rule.frequency === 'one_time') {
        form.minimum_amount = '';
        form.maximum_amount = '';
    }
};

watch(() => form.bucket, (bucket, previous) => {
    if (! composeOpen.value || form.kind !== 'savings' || bucket === previous) {
        return;
    }
    applyBucketDefaults(bucket);
});

watch(isOneTime, (oneTime) => {
    if (oneTime) {
        form.minimum_amount = '';
        form.maximum_amount = '';
    }
});

watch(allowsWithdrawal, (allowed) => {
    if (! allowed) {
        form.is_locked = true;
    }
});

const revenueHeadOptions = computed(() => {
    const options = [
        { value: '', label: 'None' },
        ...revenueHeads.value,
    ];
    const current = form.revenue_head_id;
    if (current && current !== '__create__' && ! options.some((row) => String(row.value) === String(current))) {
        options.splice(1, 0, {
            value: current,
            label: editing.value?.form?.revenue_head_name || current,
        });
    }
    options.push({ value: '__create__', label: 'Create New Revenue Head' });

    return options;
});

const settlementBankOptions = computed(() => {
    const options = [
        { value: '', label: 'None' },
        ...settlementBanks.value,
    ];
    const current = form.settlement_bank_id;
    if (current && ! options.some((row) => String(row.value) === String(current))) {
        const product = editing.value;
        const name = product?.form
            ? [product.form.settlement_account_name, product.form.settlement_bank_name, product.form.settlement_account_no]
                .filter(Boolean)
                .join(' · ')
            : current;
        options.splice(1, 0, { value: current, label: name || current });
    }

    return options;
});

const creatingRevenueHead = computed(() => form.revenue_head_id === '__create__');
const syncedRevenueName = ref('');

const cookieValue = (name) => {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name.replace(/[-.*+?^${}()|[\]\\]/g, '\\$&')}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : '';
};

const loadMonipayOptions = async () => {
    if (! props.monipay_configured || monipayLoading.value) {
        return;
    }
    if (monipayLoaded.value) {
        applyDefaultSettlement();
        return;
    }

    monipayLoading.value = true;
    try {
        const response = await fetch(`${base}/products/monipay-options`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': cookieValue('XSRF-TOKEN'),
            },
            credentials: 'same-origin',
        });
        const data = await response.json();
        revenueHeads.value = data.revenue_heads || [];
        settlementBanks.value = data.settlement_banks || [];
        monipayError.value = data.monipay_error || null;
        monipayLoaded.value = true;
        applyDefaultSettlement();
    } catch (error) {
        monipayError.value = 'Could not load Monipay options.';
    } finally {
        monipayLoading.value = false;
    }
};

const applyDefaultSettlement = () => {
    if (! composeOpen.value || (form.kind !== 'savings' && form.kind !== 'islamic')) {
        return;
    }

    if (! form.revenue_head_id && revenueHeads.value.length) {
        form.revenue_head_id = revenueHeads.value[0].value;
    }

    if (! form.settlement_bank_id) {
        const head = revenueHeads.value.find((row) => String(row.value) === String(form.revenue_head_id));
        if (head?.settlement_bank_id) {
            form.settlement_bank_id = head.settlement_bank_id;
        } else if (settlementBanks.value.length) {
            form.settlement_bank_id = settlementBanks.value[0].value;
        }
    }
};

watch(composeOpen, (open) => {
    if (open && (form.kind === 'savings' || form.kind === 'islamic')) {
        loadMonipayOptions();
    }
});

watch(() => form.kind, (kind) => {
    if (composeOpen.value && (kind === 'savings' || kind === 'islamic')) {
        loadMonipayOptions();
    }
});

watch(() => form.revenue_head_id, (id) => {
    if (id === '__create__') {
        const name = (form.name || '').trim();
        form.new_revenue_head_name = name;
        syncedRevenueName.value = name;

        return;
    }

    form.new_revenue_head_name = '';
    syncedRevenueName.value = '';

    if (! id) {
        return;
    }

    const head = revenueHeads.value.find((row) => String(row.value) === String(id));
    if (head?.settlement_bank_id) {
        form.settlement_bank_id = head.settlement_bank_id;
    } else if (! form.settlement_bank_id && settlementBanks.value.length) {
        form.settlement_bank_id = settlementBanks.value[0].value;
    }
});

watch(() => form.name, (name) => {
    if (form.revenue_head_id !== '__create__') {
        return;
    }
    const next = (name || '').trim();
    if (! form.new_revenue_head_name?.trim() || form.new_revenue_head_name.trim() === syncedRevenueName.value) {
        form.new_revenue_head_name = next;
        syncedRevenueName.value = next;
    }
});

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return props.products.filter((p) => {
        if (filter.value === 'inactive' && p.is_active) {
            return false;
        }
        if (filter.value !== 'all' && filter.value !== 'inactive' && p.kind !== filter.value) {
            return false;
        }
        if (term && ! `${p.name} ${p.summary} ${p.kind_label}`.toLowerCase().includes(term)) {
            return false;
        }

        return true;
    });
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.kind = defaultKind();
    form.finance_mode = defaultFinanceMode();
    form.bucket = 'savings';
    form.frequency = 'monthly';
    form.minimum_tenure_months = 1;
    form.maximum_tenure_months = 12;
    form.interest_method = 'reducing_balance';
    form.interest_rate = 12;
    form.processing_fee = 0;
    form.guarantors_required = 0;
    form.structure = 'qard_hasan';
    form.admin_fee = 0;
    form.default_profit_rate = 10;
    form.amount_is_fixed = false;
    form.is_locked = false;
    form.is_required = false;
    form.revenue_head_id = '';
    form.new_revenue_head_name = '';
    form.settlement_bank_id = '';
    editing.value = null;
};

const openCreate = () => {
    resetForm();
    composeOpen.value = true;
};

const openEdit = (item) => {
    resetForm();
    editing.value = item;
    Object.assign(form, {
        kind: item.form.kind,
        name: item.form.name,
        description: item.form.description || '',
        finance_mode: item.form.finance_mode || defaultFinanceMode(),
        bucket: item.form.bucket || 'savings',
        frequency: item.form.frequency || 'monthly',
        minimum_amount: item.form.minimum_amount ?? '',
        maximum_amount: item.form.maximum_amount ?? '',
        default_amount: item.form.default_amount ?? '',
        amount_is_fixed: Boolean(item.form.amount_is_fixed),
        is_locked: Boolean(item.form.is_locked),
        is_required: Boolean(item.form.is_required),
        revenue_head_id: item.form.revenue_head_id || '',
        new_revenue_head_name: '',
        settlement_bank_id: item.form.settlement_bank_id || '',
        minimum_tenure_months: item.form.minimum_tenure_months ?? 1,
        maximum_tenure_months: item.form.maximum_tenure_months ?? 12,
        interest_method: item.form.interest_method || 'reducing_balance',
        interest_rate: item.form.interest_rate ?? 12,
        processing_fee: item.form.processing_fee ?? 0,
        guarantors_required: item.form.guarantors_required ?? 0,
        structure: item.form.structure || 'qard_hasan',
        admin_fee: item.form.admin_fee ?? 0,
        default_profit_rate: item.form.default_profit_rate ?? 10,
    });
    composeOpen.value = true;
};

const save = () => {
    const options = {
        onSuccess: () => {
            composeOpen.value = false;
            resetForm();
        },
        onFinish: () => form.transform((data) => data),
    };
    form.transform((data) => ({
        ...data,
        default_amount: parseAmount(data.default_amount),
        minimum_amount: parseAmount(data.minimum_amount),
        maximum_amount: parseAmount(data.maximum_amount),
        processing_fee: parseAmount(data.processing_fee),
        admin_fee: parseAmount(data.admin_fee),
    }));
    if (editing.value) {
        form.post(`${base}/products/${editing.value.kind}/${editing.value.uuid}`, options);
        return;
    }
    form.post(`${base}/products`, options);
};

const pendingToggle = ref(null);
const toggling = ref(false);

const toggleTitle = computed(() => (pendingToggle.value?.is_active ? 'Deactivate product' : 'Activate product'));
const toggleMessage = computed(() => {
    if (! pendingToggle.value) {
        return '';
    }

    return pendingToggle.value.is_active
        ? `Deactivate “${pendingToggle.value.name}”? It will no longer be available for new mandates or applications.`
        : `Activate “${pendingToggle.value.name}”? It will be available for members again.`;
});
const toggleConfirmLabel = computed(() => (pendingToggle.value?.is_active ? 'Deactivate' : 'Activate'));
const toggleVariant = computed(() => (pendingToggle.value?.is_active ? 'secondary' : 'gold'));

const askToggle = (item) => {
    pendingToggle.value = item;
};

const confirmToggle = () => {
    if (! pendingToggle.value) {
        return;
    }
    toggling.value = true;
    router.post(`${base}/products/${pendingToggle.value.kind}/${pendingToggle.value.uuid}/toggle`, {}, {
        onFinish: () => {
            toggling.value = false;
            pendingToggle.value = null;
        },
    });
};

const pendingDelete = ref(null);
const deleting = ref(false);

const deleteMessage = computed(() => {
    if (! pendingDelete.value) {
        return '';
    }
    if (! pendingDelete.value.can_delete) {
        return `“${pendingDelete.value.name}” has payment or financing records and cannot be deleted.`;
    }

    return `Delete “${pendingDelete.value.name}”? This cannot be undone.`;
});

const askDelete = (item) => {
    pendingDelete.value = item;
};

const confirmDelete = () => {
    if (! pendingDelete.value?.can_delete) {
        pendingDelete.value = null;
        return;
    }
    deleting.value = true;
    router.delete(`${base}/products/${pendingDelete.value.kind}/${pendingDelete.value.uuid}`, {
        onFinish: () => {
            deleting.value = false;
            pendingDelete.value = null;
        },
    });
};

const enroll = useForm({ member_uuid: '', product_uuid: '', amount: '' });
const selectedSavings = computed(() => props.savings_products.find((p) => p.uuid === enroll.product_uuid));
const amountHint = computed(() => {
    const product = selectedSavings.value;
    if (! product) {
        return '';
    }
    if (product.amount_is_fixed && product.default_amount != null) {
        return `Fixed at ₦${product.default_amount}`;
    }
    const parts = [];
    if (product.min_amount) {
        parts.push(`Min ₦${product.min_amount}`);
    }
    if (product.max_amount) {
        parts.push(`max ₦${product.max_amount}`);
    }
    return parts.join(' · ');
});

watch(() => enroll.product_uuid, (uuid) => {
    const product = props.savings_products.find((p) => p.uuid === uuid);
    if (! product) {
        return;
    }
    if (product.default_amount != null && (product.amount_is_fixed || enroll.amount === '' || enroll.amount == null)) {
        enroll.amount = product.default_amount;
    }
});

const openEnroll = () => {
    enroll.reset();
    enroll.clearErrors();
    enrollOpen.value = true;
};

const submitEnroll = () => enroll.post(`${base}/contributions/enroll`, {
    onSuccess: () => {
        enrollOpen.value = false;
        enroll.reset();
    },
});
</script>

<template>
    <Head title="Products" />
        <PageHeader eyebrow="Office" title="Products">
            <template #actions>
                <AppButton v-if="can('contributions.manage')" type="button" variant="secondary" :disabled="!savings_products.length" @click="openEnroll">Add mandate</AppButton>
                <AppButton v-if="can('products.manage')" type="button" variant="gold" @click="openCreate">Add product</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="All" :value="counts.all ?? products.length" />
            <StatCard label="Active" :value="counts.active ?? 0" />
            <StatCard label="Savings" :value="counts.savings ?? 0" />
            <StatCard v-if="tenant?.offers_conventional" label="Loans" :value="counts.loan ?? 0" />
            <StatCard v-else-if="tenant?.offers_islamic" label="Loans" :value="counts.islamic ?? 0" />
            <StatCard v-if="tenant?.offers_conventional && tenant?.offers_islamic" label="Loans" :value="counts.islamic ?? 0" />
        </div>

        <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex flex-wrap gap-2 text-xs">
                <button class="rounded-full px-3 py-1.5" :class="filter === 'all' ? 'bg-[#071c1b] text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'all'">All</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'savings' ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'savings'">Savings</button>
                <button v-if="tenant?.offers_conventional" class="rounded-full px-3 py-1.5" :class="filter === 'loan' ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'loan'">Loans</button>
                <button v-if="tenant?.offers_islamic" class="rounded-full px-3 py-1.5" :class="filter === 'islamic' ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'islamic'">Loans</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'inactive' ? 'bg-slate-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'inactive'">Inactive</button>
            </div>
            <input
                v-model="query"
                type="search"
                placeholder="Search products"
                class="w-full rounded-2xl border-0 bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-black/5 lg:ml-auto lg:max-w-md"
            >
        </div>

        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!filtered.length" icon="products" title="No products here" body="Add a savings plan or a loan product." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Range</th>
                        <th class="px-5 py-3">Settlement bank</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in filtered" :key="`${item.kind}-${item.uuid}`" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-900">{{ item.name }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ item.summary }}</div>
                        </td>
                        <td class="px-5 py-4">{{ item.kind_label }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ item.range }}</td>
                        <td class="px-5 py-4">
                            <template v-if="item.settlement_bank?.name || item.settlement_bank?.account_no">
                                <div class="font-medium text-slate-900">{{ item.settlement_bank.name || '—' }}</div>
                                <div v-if="item.settlement_bank.bank_name || item.settlement_bank.account_no" class="mt-0.5 text-xs text-slate-500">
                                    {{ [item.settlement_bank.bank_name, item.settlement_bank.account_no].filter(Boolean).join(' · ') }}
                                </div>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-5 py-4"><StatusBadge :value="item.is_active ? 'active' : 'inactive'" /></td>
                        <td class="px-5 py-4">
                            <div v-if="can('products.manage')" class="flex flex-wrap justify-end gap-2">
                                <AppButton type="button" variant="secondary" @click="openEdit(item)">Edit</AppButton>
                                <AppButton type="button" :variant="item.is_active ? 'secondary' : 'gold'" @click="askToggle(item)">
                                    {{ item.is_active ? 'Deactivate' : 'Activate' }}
                                </AppButton>
                                <button
                                    v-if="!item.is_active"
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-rose-400 ring-1 ring-rose-100 hover:bg-rose-50 hover:text-rose-500"
                                    aria-label="Delete product"
                                    title="Delete"
                                    @click="askDelete(item)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <Modal
            :show="composeOpen"
            :title="editing ? 'Edit product' : 'Add product'"
            wide
            @close="composeOpen = false"
        >
            <form class="space-y-5" @submit.prevent="save">
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField v-if="!editing" label="Type" :error="form.errors.kind">
                            <AppSelect v-model="form.kind" variant="outline" :options="kinds" />
                        </FormField>
                        <FormField label="Name" :error="form.errors.name" :class="editing ? 'sm:col-span-2' : ''">
                            <input v-model="form.name" maxlength="120" :class="input" required>
                        </FormField>
                        <FormField label="Description" class="sm:col-span-2" :error="form.errors.description">
                            <textarea v-model="form.description" rows="3" maxlength="2000" :class="input"></textarea>
                        </FormField>
                    </div>
                </section>

                <section v-if="form.kind === 'savings'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Savings</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <FormField label="Mode" :error="form.errors.finance_mode">
                            <AppSelect v-model="form.finance_mode" variant="outline" :options="financeModeOptions" />
                        </FormField>
                        <FormField label="Collection column" :error="form.errors.bucket">
                            <AppSelect v-model="form.bucket" variant="outline" :options="buckets" />
                        </FormField>
                        <FormField label="Frequency" :error="form.errors.frequency">
                            <AppSelect
                                v-model="form.frequency"
                                variant="outline"
                                :options="frequencyOptions"
                            />
                        </FormField>
                        <FormField label="Default (₦)" :error="form.errors.default_amount">
                            <AmountInput v-model="form.default_amount" :class="moneyInput" />
                        </FormField>
                        <FormField v-if="!isOneTime" label="Minimum (₦)" :error="form.errors.minimum_amount">
                            <AmountInput v-model="form.minimum_amount" :class="moneyInput" />
                        </FormField>
                        <FormField v-if="!isOneTime" label="Maximum (₦)" :error="form.errors.maximum_amount">
                            <AmountInput v-model="form.maximum_amount" :class="moneyInput" />
                        </FormField>
                    </div>
                    <label class="mt-4 flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input v-model="form.amount_is_fixed" type="checkbox" class="rounded border-slate-300">
                        Fixed amount
                    </label>
                    <label class="mt-3 flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input v-model="form.is_required" type="checkbox" class="rounded border-slate-300">
                        Required for every member
                    </label>
                    <p class="mt-1 text-xs text-slate-500">Required products show on Record payment even if the member has no mandate.</p>
                    <label v-if="allowsWithdrawal" class="mt-3 flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input v-model="form.is_locked" type="checkbox" class="rounded border-slate-300">
                        Locked (withdrawal restricted)
                    </label>
                    <p v-else class="mt-3 text-xs text-slate-500">Withdrawals are only available on Special Target Scheme (STS).</p>
                </section>

                <section v-if="form.kind === 'loan'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Loan</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <FormField label="Interest method" :error="form.errors.interest_method">
                            <AppSelect
                                v-model="form.interest_method"
                                variant="outline"
                                :options="[
                                    { value: 'flat', label: 'Flat' },
                                    { value: 'reducing_balance', label: 'Reducing balance' },
                                    { value: 'declining_balance', label: 'Declining balance' },
                                ]"
                            />
                        </FormField>
                        <FormField label="Rate % p.a." :error="form.errors.interest_rate">
                            <input v-model="form.interest_rate" type="number" min="0" step="0.01" :class="input" required>
                        </FormField>
                        <FormField label="Minimum (₦)" :error="form.errors.minimum_amount">
                            <AmountInput v-model="form.minimum_amount" :class="moneyInput" required />
                        </FormField>
                        <FormField label="Maximum (₦)" :error="form.errors.maximum_amount">
                            <AmountInput v-model="form.maximum_amount" :class="moneyInput" required />
                        </FormField>
                        <FormField label="Min tenure (months)" :error="form.errors.minimum_tenure_months">
                            <input v-model="form.minimum_tenure_months" type="number" min="1" :class="input" required>
                        </FormField>
                        <FormField label="Max tenure (months)" :error="form.errors.maximum_tenure_months">
                            <input v-model="form.maximum_tenure_months" type="number" min="1" :class="input" required>
                        </FormField>
                        <FormField label="Processing fee (₦)" :error="form.errors.processing_fee">
                            <AmountInput v-model="form.processing_fee" :class="input" />
                        </FormField>
                        <FormField label="Guarantors" :error="form.errors.guarantors_required">
                            <input v-model="form.guarantors_required" type="number" min="0" :class="input">
                        </FormField>
                    </div>
                </section>

                <section v-if="form.kind === 'islamic'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Loan</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <FormField label="Structure" :error="form.errors.structure">
                            <AppSelect
                                v-model="form.structure"
                                variant="outline"
                                :options="[
                                    { value: 'qard_hasan', label: 'Qard Hasan' },
                                    { value: 'murabaha', label: 'Murabaha' },
                                ]"
                            />
                        </FormField>
                        <FormField v-if="form.structure === 'murabaha'" label="Profit % " :error="form.errors.default_profit_rate">
                            <input v-model="form.default_profit_rate" type="number" min="0" step="0.01" :class="input">
                        </FormField>
                        <FormField label="Minimum (₦)" :error="form.errors.minimum_amount">
                            <AmountInput v-model="form.minimum_amount" :class="moneyInput" required />
                        </FormField>
                        <FormField label="Maximum (₦)" :error="form.errors.maximum_amount">
                            <AmountInput v-model="form.maximum_amount" :class="moneyInput" required />
                        </FormField>
                        <FormField label="Min tenure (months)" :error="form.errors.minimum_tenure_months">
                            <input v-model="form.minimum_tenure_months" type="number" min="1" :class="input">
                        </FormField>
                        <FormField label="Max tenure (months)" :error="form.errors.maximum_tenure_months">
                            <input v-model="form.maximum_tenure_months" type="number" min="1" :class="input">
                        </FormField>
                        <FormField v-if="form.structure === 'qard_hasan'" label="Admin fee (₦)" :error="form.errors.admin_fee">
                            <AmountInput v-model="form.admin_fee" :class="input" />
                        </FormField>
                    </div>
                </section>

                <section
                    v-if="(form.kind === 'savings' || form.kind === 'islamic') && monipay_configured"
                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                >
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Settlement</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <FormField label="Revenue Head" :error="form.errors.revenue_head_id" :class="creatingRevenueHead ? '' : 'sm:col-span-2'">
                            <AppSelect
                                v-model="form.revenue_head_id"
                                variant="outline"
                                searchable
                                :options="revenueHeadOptions"
                                :disabled="monipayLoading"
                            />
                        </FormField>
                        <FormField
                            v-if="creatingRevenueHead"
                            label="New Revenue Head Name"
                            :error="form.errors.new_revenue_head_name"
                        >
                            <input v-model="form.new_revenue_head_name" maxlength="160" :class="input" :required="creatingRevenueHead">
                        </FormField>
                        <FormField label="Settlement Bank" class="sm:col-span-2" :error="form.errors.settlement_bank_id" :hint="monipayError || (monipayLoading ? 'Loading Monipay options…' : '')">
                            <AppSelect
                                v-model="form.settlement_bank_id"
                                variant="outline"
                                searchable
                                :options="settlementBankOptions"
                                :required="creatingRevenueHead"
                                :disabled="monipayLoading"
                            />
                        </FormField>
                    </div>
                </section>
                <p
                    v-else-if="(form.kind === 'savings' || form.kind === 'islamic') && !monipay_configured"
                    class="text-xs text-slate-500"
                >
                    Add a Monipay secret key in Settings to link a Revenue Head and Settlement Bank.
                </p>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="composeOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing">
                        {{ editing ? 'Save changes' : 'Save product' }}
                    </AppButton>
                </div>
            </form>
        </Modal>

        <Modal :show="enrollOpen" title="Add mandate" wide @close="enrollOpen = false">
            <form class="space-y-5" @submit.prevent="submitEnroll">
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Member" :error="enroll.errors.member_uuid">
                            <AppSelect v-model="enroll.member_uuid" placeholder="Select member" :options="members" option-value="uuid" required variant="outline" />
                        </FormField>
                        <FormField label="Product" :error="enroll.errors.product_uuid">
                            <AppSelect v-model="enroll.product_uuid" placeholder="Select savings product" :options="savings_products" option-value="uuid" option-label="name" required variant="outline" />
                        </FormField>
                        <FormField label="Amount (₦)" class="sm:col-span-2" :error="enroll.errors.amount" :hint="amountHint">
                            <AmountInput v-model="enroll.amount" :class="input" :readonly="selectedSavings?.amount_is_fixed" required />
                        </FormField>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="enrollOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="enroll.processing">Add mandate</AppButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(pendingToggle)"
            :title="toggleTitle"
            :message="toggleMessage"
            :confirm-label="toggleConfirmLabel"
            :variant="toggleVariant"
            :processing="toggling"
            @close="pendingToggle = null"
            @confirm="confirmToggle"
        />

        <ConfirmModal
            :show="Boolean(pendingDelete)"
            title="Delete product"
            :message="deleteMessage"
            :confirm-label="pendingDelete?.can_delete ? 'Delete' : 'OK'"
            :variant="pendingDelete?.can_delete ? 'danger' : 'secondary'"
            :cancel-label="pendingDelete?.can_delete ? 'Cancel' : ''"
            :processing="deleting"
            @close="pendingDelete = null"
            @confirm="confirmDelete"
        />
</template>
