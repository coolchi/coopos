<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import RecordPaymentModal from '../../../Components/RecordPaymentModal.vue';
import Modal from '../../../Components/Modal.vue';
import FormField from '../../../Components/FormField.vue';
import AppSelect from '../../../Components/AppSelect.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { parseAmount } from '../../../money';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    tab: { type: String, default: 'summary' },
    month: { type: String, default: '' },
    month_label: { type: String, default: '' },
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    can_filter_branches: { type: Boolean, default: false },
    needs_branch: { type: Boolean, default: false },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    column_totals: { type: Object, default: () => ({}) },
    grand_total: { type: String, default: '₦0' },
    contributions: { type: Object, default: null },
    members: Array,
    products: Array,
    required_products: { type: Array, default: () => [] },
    member_plans: { type: Object, default: () => ({}) },
    member_paid: { type: Object, default: () => ({}) },
    member_facilities: { type: Object, default: () => ({}) },
    can_manage_transactions: { type: Boolean, default: false },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const payOpen = ref(false);
const monthValue = ref(props.month);
const branchValue = ref(props.branch?.uuid || '');
const editing = ref(null);
const deleting = ref(null);
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';

watch(() => props.month, (value) => {
    monthValue.value = value;
});
watch(() => props.branch?.uuid, (value) => {
    branchValue.value = value || '';
});

const isTransactions = computed(() => props.tab === 'transactions');

const monthOptions = computed(() => {
    const now = new Date();
    const options = [];
    for (let i = 17; i >= 0; i -= 1) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        options.push({
            value,
            label: date.toLocaleString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }
    if (props.month && ! options.some((row) => row.value === props.month)) {
        const [year, month] = props.month.split('-').map(Number);
        const date = new Date(year, month - 1, 1);
        options.unshift({
            value: props.month,
            label: date.toLocaleString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }

    return options;
});

const branchOptions = computed(() => (props.branches || []).map((b) => ({
    value: b.uuid || '',
    label: b.name,
})));

const showBranchFilter = computed(() => props.can_filter_branches && branchOptions.value.length > 0);

const productOptions = computed(() => (props.products || []).map((p) => ({
    value: p.uuid,
    label: p.name,
})));

const forMonthOptions = computed(() => {
    const now = new Date();
    const options = [];
    for (let i = 23; i >= -2; i -= 1) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        options.push({
            value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
            label: date.toLocaleString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }
    return options;
});

const channelOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'transfer', label: 'Transfer' },
];

const tabClass = (active) => active
    ? 'bg-[#071c1b] text-white shadow-sm'
    : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50';

const goTab = (tab) => {
    router.get(`${base}/contributions`, {
        tab: tab === 'transactions' ? 'transactions' : undefined,
        month: tab === 'summary' ? (monthValue.value || undefined) : undefined,
        branch: branchValue.value || undefined,
    }, { preserveState: false });
};

const reload = (overrides = {}) => {
    const tab = overrides.tab ?? props.tab;
    router.get(`${base}/contributions`, {
        tab: tab === 'transactions' ? 'transactions' : undefined,
            month: tab === 'summary' ? ((overrides.month ?? monthValue.value) || undefined) : undefined,
        branch: (overrides.branch ?? branchValue.value) || undefined,
    }, { preserveState: false });
};

const onMonthChange = (value) => {
    monthValue.value = value;
    reload({ month: value });
};

const onBranchChange = (value) => {
    branchValue.value = value || '';
    reload({ branch: value || '' });
};

const cellClass = (value) => value === '—'
    ? 'text-slate-300'
    : 'text-slate-900';

const editForm = useForm({
    access_code: '',
    product_uuid: '',
    amount: '',
    channel: 'cash',
    paid_on: '',
    for_month: '',
});

const deleteForm = useForm({
    access_code: '',
});

const openEdit = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.access_code = '';
    editForm.product_uuid = row.product_uuid;
    editForm.amount = row.amount_naira;
    editForm.channel = row.channel === 'bank_transfer' ? 'transfer' : (row.channel || 'cash');
    editForm.paid_on = row.paid_on;
    editForm.for_month = row.for_month_value || '';
};

const closeEdit = () => {
    editing.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (! editing.value) return;
    editForm.transform((data) => ({
        ...data,
        amount: parseAmount(data.amount),
    })).put(`${base}/contributions/${editing.value.uuid}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const openDelete = (row) => {
    deleting.value = row;
    deleteForm.clearErrors();
    deleteForm.access_code = '';
};

const closeDelete = () => {
    deleting.value = null;
    deleteForm.reset();
};

const submitDelete = () => {
    if (! deleting.value) return;
    deleteForm.delete(`${base}/contributions/${deleting.value.uuid}`, {
        preserveScroll: true,
        onSuccess: () => closeDelete(),
    });
};
</script>

<template>
    <Head title="Contributions" />
    <PageHeader title="Contributions">
        <template #actions>
            <AppButton :href="`${base}/contributions/enrollments`" variant="secondary">Mandates</AppButton>
            <AppButton
                v-if="page.props.can_manage_opening_balances"
                :href="`${base}/opening-balances`"
                variant="secondary"
            >
                {{ page.props.opening_balances_locked ? 'Brought forward' : 'Opening balances' }}
            </AppButton>
            <AppButton v-if="page.props.allow_manual_payments && can('contributions.record')" type="button" variant="gold" @click="payOpen = true">Record payment</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-black/5 sm:p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="tabClass(!isTransactions)"
                    @click="goTab('summary')"
                >
                    Summary
                </button>
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="tabClass(isTransactions)"
                    @click="goTab('transactions')"
                >
                    Transactions
                </button>
            </div>

            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                <div v-if="!isTransactions" class="w-full sm:w-52">
                    <AppSelect
                        :model-value="monthValue"
                        :options="monthOptions"
                        size="sm"
                        @update:model-value="onMonthChange"
                    />
                </div>
                <div v-if="showBranchFilter" class="w-full sm:w-48">
                    <AppSelect
                        :model-value="branchValue"
                        :options="branchOptions"
                        placeholder="Select branch"
                        size="sm"
                        @update:model-value="onBranchChange"
                    />
                </div>
            </div>
        </div>
    </div>

    <template v-if="!isTransactions">
        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="needs_branch" icon="members" title="Select a branch" body="Choose a branch to view contribution totals by product." />
            <EmptyState v-else-if="!rows.length" icon="members" title="No active members" body="Activate members to see contribution totals by product." />
            <div v-else class="max-h-[min(70vh,52rem)] overflow-auto">
                <table class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            <th class="sticky left-0 top-0 z-30 bg-slate-50 px-4 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Mem no</th>
                            <th class="sticky left-24 top-0 z-30 min-w-[10rem] bg-slate-50 px-4 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Name</th>
                            <th
                                v-for="col in columns"
                                :key="col.uuid"
                                class="sticky top-0 z-20 whitespace-nowrap bg-slate-50 px-3 py-3 text-right shadow-[inset_0_-1px_0_0_rgb(226_232_240)]"
                            >
                                {{ col.label }}
                            </th>
                            <th class="sticky top-0 z-20 bg-slate-50 px-4 py-3 text-right shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in rows"
                            :key="row.uuid"
                            class="group transition-colors hover:bg-teal-50/40"
                            :class="index % 2 ? 'bg-slate-50/40' : 'bg-white'"
                        >
                            <td
                                class="sticky left-0 z-10 px-4 py-3.5 font-medium text-teal-900 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                                :class="index % 2 ? 'bg-slate-50 group-hover:bg-teal-50' : 'bg-white group-hover:bg-teal-50'"
                            >
                                {{ row.number }}
                            </td>
                            <td
                                class="sticky left-24 z-10 min-w-[10rem] px-4 py-3.5 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                                :class="index % 2 ? 'bg-slate-50 group-hover:bg-teal-50' : 'bg-white group-hover:bg-teal-50'"
                            >
                                <div class="font-medium text-slate-900">{{ row.name }}</div>
                                <div v-if="row.introducer" class="text-xs text-slate-500">Introduced by {{ row.introducer }}</div>
                            </td>
                            <td
                                v-for="col in columns"
                                :key="col.uuid"
                                class="whitespace-nowrap px-3 py-3.5 text-right tabular-nums shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                                :class="cellClass(row.cells[col.uuid])"
                            >
                                {{ row.cells[col.uuid] }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]">
                                {{ row.total }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <td class="sticky bottom-0 left-0 z-30 bg-slate-200 px-4 py-3.5 font-semibold text-slate-900 shadow-[inset_0_1px_0_0_rgb(203_213_225)]">
                                Total
                            </td>
                            <td class="sticky bottom-0 left-24 z-30 bg-slate-200 px-4 py-3.5 text-slate-600 shadow-[inset_0_1px_0_0_rgb(203_213_225)]">
                                All columns
                            </td>
                            <td
                                v-for="col in columns"
                                :key="`total-${col.uuid}`"
                                class="sticky bottom-0 z-20 whitespace-nowrap bg-slate-200 px-3 py-3.5 text-right font-semibold tabular-nums shadow-[inset_0_1px_0_0_rgb(203_213_225)]"
                                :class="column_totals[col.uuid] === '—' ? 'text-slate-500' : 'text-slate-900'"
                            >
                                {{ column_totals[col.uuid] || '—' }}
                            </td>
                            <td class="sticky bottom-0 z-20 bg-slate-200 px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 shadow-[inset_0_1px_0_0_rgb(203_213_225)]">
                                {{ grand_total }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </AppCard>
    </template>

    <template v-else>
        <AppCard class="mt-6" :padded="false">
            <EmptyState
                v-if="needs_branch"
                icon="savings"
                title="Select a branch"
                body="Choose a branch to view contribution transactions."
            />
            <EmptyState
                v-else-if="!contributions?.data?.length"
                icon="savings"
                title="No transactions recorded"
                :body="page.props.allow_manual_payments
                    ? 'Use Record payment to post cash or transfer savings for a member.'
                    : 'Manual posting is off. Payments will appear here after bank transfers land.'"
            />
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Member</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">For month</th>
                            <th class="px-5 py-3">Date</th>
                            <th v-if="can_manage_transactions" class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(c, index) in contributions.data"
                            :key="c.uuid"
                            class="border-t border-slate-100 transition-colors hover:bg-teal-50/40"
                            :class="index % 2 ? 'bg-slate-50/40' : 'bg-white'"
                        >
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ c.member }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ c.branch || '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-800">
                                <div>{{ c.product }}</div>
                                <div v-if="c.kind && c.kind !== 'contribution'" class="text-xs text-slate-500">Repayment</div>
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-medium text-slate-900">{{ c.amount }}</td>
                            <td class="px-5 py-3.5 capitalize text-slate-600">{{ c.channel || '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ c.for_month || '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-500">{{ c.paid_on }}</td>
                            <td v-if="can_manage_transactions" class="px-5 py-3.5">
                                <div v-if="c.editable !== false" class="flex justify-end gap-2">
                                    <AppButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs" @click="openEdit(c)">Edit</AppButton>
                                    <AppButton type="button" variant="danger" class="!px-3 !py-1.5 text-xs" @click="openDelete(c)">Delete</AppButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template v-if="contributions" #footer>
                <Pagination :links="contributions.links" :from="contributions.from" :to="contributions.to" :total="contributions.total" />
            </template>
        </AppCard>
    </template>

    <RecordPaymentModal
        :show="payOpen"
        :members="members"
        :products="products"
        :required_products="required_products"
        :member_plans="member_plans"
        :member_paid="member_paid"
        :member_facilities="member_facilities"
        @close="payOpen = false"
    />

    <Modal :show="!!editing" title="Edit transaction" subtitle="Super Admin only. Access code required." @close="closeEdit">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <p class="text-sm text-slate-600">Member: <span class="font-medium text-slate-900">{{ editing?.member }}</span></p>
            <FormField label="Product" :error="editForm.errors.product_uuid">
                <AppSelect v-model="editForm.product_uuid" :options="productOptions" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Amount (₦)" :error="editForm.errors.amount">
                    <AmountInput v-model="editForm.amount" :class="input" required />
                </FormField>
                <FormField label="Channel" :error="editForm.errors.channel">
                    <AppSelect v-model="editForm.channel" :options="channelOptions" />
                </FormField>
                <FormField label="For month" :error="editForm.errors.for_month">
                    <AppSelect v-model="editForm.for_month" :options="forMonthOptions" />
                </FormField>
                <FormField label="Paid on" :error="editForm.errors.paid_on">
                    <input v-model="editForm.paid_on" type="date" :class="input" required>
                </FormField>
            </div>
            <FormField label="Access code" :error="editForm.errors.access_code" hint="Required to save changes.">
                <input v-model="editForm.access_code" type="password" autocomplete="off" :class="input" required>
            </FormField>
            <div class="flex justify-end gap-2 pt-2">
                <AppButton type="button" variant="secondary" :disabled="editForm.processing" @click="closeEdit">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="editForm.processing">Save changes</AppButton>
            </div>
        </form>
    </Modal>

    <Modal :show="!!deleting" title="Delete transaction" subtitle="This reverses the ledger entry. Access code required." @close="closeDelete">
        <form class="space-y-4" @submit.prevent="submitDelete">
            <p class="text-sm text-slate-600">
                Delete <span class="font-medium text-slate-900">{{ deleting?.amount }}</span> for
                <span class="font-medium text-slate-900">{{ deleting?.member }}</span>
                ({{ deleting?.product }})?
            </p>
            <FormField label="Access code" :error="deleteForm.errors.access_code">
                <input v-model="deleteForm.access_code" type="password" autocomplete="off" :class="input" required>
            </FormField>
            <div class="flex justify-end gap-2 pt-2">
                <AppButton type="button" variant="secondary" :disabled="deleteForm.processing" @click="closeDelete">Cancel</AppButton>
                <AppButton type="submit" variant="danger" :disabled="deleteForm.processing">Delete</AppButton>
            </div>
        </form>
    </Modal>
</template>
