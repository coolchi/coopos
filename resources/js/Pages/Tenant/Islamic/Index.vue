<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import StatCard from '../../../Components/StatCard.vue';
import Modal from '../../../Components/Modal.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import RepaymentModal from '../../../Components/RepaymentModal.vue';
import LoanEligibilityChecklist from '../../../Components/LoanEligibilityChecklist.vue';
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useLoanEligibility } from '../../../composables/useLoanEligibility';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    products: Array,
    qard: Array,
    murabaha: Array,
    members: Array,
    kpis: { type: Object, default: () => ({}) },
});
const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const loanBase = `${base}/loan`;
const type = ref('all');
const status = ref('all');
const qardOpen = ref(false);
const murabahaOpen = ref(false);
const pendingAct = ref(null);
const repayTarget = ref(null);
const viewKey = ref(null);
const qardForm = useForm({ member_uuid: '', product_uuid: '', amount: '', tenure_months: 6, purpose: '' });
const murabahaForm = useForm({
    member_uuid: '', product_uuid: '', asset_description: '', supplier: '',
    purchase_cost: '', profit: '', down_payment: '', tenure_months: 6,
});
const {
    checks: qardChecks,
    loading: qardChecking,
    passed: qardEligible,
    ready: qardReady,
} = useLoanEligibility(() => qardForm.member_uuid, () => qardForm.amount);
const {
    checks: murabahaChecks,
    loading: murabahaChecking,
    passed: murabahaEligible,
    ready: murabahaReady,
} = useLoanEligibility(() => murabahaForm.member_uuid, () => murabahaForm.purchase_cost);
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const qardProducts = computed(() => (props.products || []).filter((p) => p.structure === 'qard_hasan'));
const murabahaProducts = computed(() => (props.products || []).filter((p) => p.structure === 'murabaha'));
const selectedQard = computed(() => qardProducts.value.find((p) => p.uuid === qardForm.product_uuid));
const selectedMurabaha = computed(() => murabahaProducts.value.find((p) => p.uuid === murabahaForm.product_uuid));
const productRange = (product) => {
    if (!product) {
        return '';
    }
    if (product.minimum && product.maximum) {
        return `${product.minimum} – ${product.maximum}`;
    }
    return product.minimum ? `From ${product.minimum}` : (product.maximum || '');
};

const pendingStatuses = ['approved', 'submitted', 'pending_approval'];
const disbursableStatuses = ['approved'];
const liveStatuses = ['active', 'disbursed'];

const murabahaSale = computed(() => {
    const cost = Number(murabahaForm.purchase_cost || 0);
    const profit = Number(murabahaForm.profit || 0);

    return cost + profit;
});

const rows = computed(() => {
    const qardRows = (props.qard || []).map((f) => ({
        ...f,
        kind_label: 'Qard Hasan',
        product_name: f.product || 'Qard Hasan',
        detail: f.purpose || '',
        amount: f.principal,
    }));
    const murabahaRows = (props.murabaha || []).map((f) => ({
        ...f,
        kind_label: 'Murabaha',
        product_name: f.product || 'Murabaha',
        detail: f.asset || '',
        amount: f.sale_price,
    }));

    let list = [...qardRows, ...murabahaRows];
    if (type.value === 'qard') {
        list = qardRows;
    }
    if (type.value === 'murabaha') {
        list = murabahaRows;
    }
    if (status.value === 'pending') {
        list = list.filter((row) => pendingStatuses.includes(row.status));
    }
    if (status.value === 'live') {
        list = list.filter((row) => liveStatuses.includes(row.status));
    }
    if (status.value === 'done') {
        list = list.filter((row) => ['completed', 'rejected', 'written_off'].includes(row.status));
    }

    return list;
});

const viewTarget = computed(() => {
    if (! viewKey.value) {
        return null;
    }

    return rows.value.find((row) => row.kind === viewKey.value.kind && row.uuid === viewKey.value.uuid) || null;
});

const pill = (active) => active
    ? 'bg-[#071c1b] text-white'
    : 'bg-slate-100 text-slate-700 hover:bg-slate-200';

const submitQard = () => qardForm.post(`${loanBase}/qard`, {
    onSuccess: () => {
        qardOpen.value = false;
        qardForm.reset();
        qardForm.tenure_months = 6;
    },
});

const submitMurabaha = () => murabahaForm.post(`${loanBase}/murabaha`, {
    onSuccess: () => {
        murabahaOpen.value = false;
        murabahaForm.reset();
        murabahaForm.tenure_months = 6;
    },
});

const requestAct = (row) => {
    if (row.kind === 'qard_hasan' && disbursableStatuses.includes(row.status)) {
        pendingAct.value = {
            url: `${loanBase}/qard/${row.uuid}/disburse`,
            title: 'Disburse Qard Hasan',
            message: `Pay out ${row.amount} to ${row.member}. No interest will be applied.`,
            label: 'Disburse',
        };
        return;
    }
    if (row.kind === 'murabaha' && disbursableStatuses.includes(row.status)) {
        pendingAct.value = {
            url: `${loanBase}/murabaha/${row.uuid}/finalize`,
            title: 'Finalize Murabaha sale',
            message: `Freeze the sale price of ${row.amount} for ${row.member}. The disclosed profit cannot change after this.`,
            label: 'Finalize sale',
        };
    }
};

const confirmAct = () => {
    if (! pendingAct.value) {
        return;
    }
    useForm({}).post(pendingAct.value.url, {
        onFinish: () => {
            pendingAct.value = null;
        },
    });
};

const openRepay = (row) => {
    repayTarget.value = row;
};

const closeRepay = () => {
    repayTarget.value = null;
};

const openView = (row) => {
    viewKey.value = { kind: row.kind, uuid: row.uuid };
};

const closeView = () => {
    if (repayTarget.value) {
        return;
    }
    viewKey.value = null;
};

const startRepayFromView = () => {
    if (viewTarget.value) {
        openRepay(viewTarget.value);
    }
};

const formatDate = (value) => {
    if (! value) {
        return '—';
    }
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const canViewRepayments = (row) => liveStatuses.includes(row.status)
    || ['completed', 'rejected', 'written_off'].includes(row.status)
    || (row.transaction_count || 0) > 0;

const repayAction = computed(() => {
    if (! repayTarget.value) {
        return '';
    }

    return repayTarget.value.kind === 'qard_hasan'
        ? `${loanBase}/qard/${repayTarget.value.uuid}/repay`
        : `${loanBase}/murabaha/${repayTarget.value.uuid}/repay`;
});
</script>

<template>
    <Head title="Loans" />
        <PageHeader eyebrow="Lending" title="Loans">
            <template #actions>
                <AppButton v-if="can('products.view')" :href="`${base}/products`" variant="secondary">Products</AppButton>
                <AppButton v-if="can('islamic.manage')" type="button" variant="secondary" :disabled="!qardProducts.length" @click="qardOpen = true">New Qard Hasan</AppButton>
                <AppButton v-if="can('islamic.manage')" type="button" variant="gold" :disabled="!murabahaProducts.length" @click="murabahaOpen = true">New Murabaha</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Qard outstanding" :value="kpis.qard_outstanding || '₦0'" hint="Interest-free facilities" />
            <StatCard label="Murabaha outstanding" :value="kpis.murabaha_outstanding || '₦0'" hint="Cost plus disclosed profit" />
            <StatCard label="Facilities" :value="kpis.facilities ?? 0" />
            <StatCard label="Products" :value="kpis.products ?? 0" />
        </div>

        <div class="mt-6 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-black/5 sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(type === 'all')" @click="type = 'all'">All</button>
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(type === 'qard')" @click="type = 'qard'">Qard Hasan</button>
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(type === 'murabaha')" @click="type = 'murabaha'">Murabaha</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(status === 'all')" @click="status = 'all'">Every status</button>
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(status === 'pending')" @click="status = 'pending'">To action</button>
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(status === 'live')" @click="status = 'live'">Live</button>
                    <button type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition" :class="pill(status === 'done')" @click="status = 'done'">Closed</button>
                </div>
            </div>
        </div>

        <AppCard class="mt-4" :padded="false">
            <EmptyState
                v-if="!products?.length"
                icon="islamic"
                title="No loan products"
                body="Add Qard Hasan or Murabaha on the products page before you can book a facility."
            />
            <EmptyState
                v-else-if="!rows.length"
                icon="loans"
                title="No loans match"
                body="Create a Qard Hasan or Murabaha, or clear the filters."
            />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Member</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Amount paid</th>
                        <th class="px-5 py-3">Transactions</th>
                        <th class="px-5 py-3">Outstanding</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.kind + row.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ row.member }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ row.product_name }}</div>
                            <div class="text-xs text-slate-500">{{ row.kind_label }}</div>
                        </td>
                        <td class="px-5 py-4 tabular-nums">{{ row.amount }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ row.amount_paid || '₦0' }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ row.transaction_count ?? 0 }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ row.outstanding }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="row.status" /></td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    v-if="canViewRepayments(row)"
                                    type="button"
                                    class="text-sm font-medium text-teal-800"
                                    @click="openView(row)"
                                >View</button>
                                <Link
                                    v-if="row.status === 'pending_approval' && can('approvals.act')"
                                    :href="`${base}/approvals`"
                                    class="text-sm font-medium text-teal-800"
                                >Review in Approvals</Link>
                                <button
                                    v-else-if="disbursableStatuses.includes(row.status) && can('islamic.manage')"
                                    type="button"
                                    class="text-sm font-medium text-teal-800"
                                    @click="requestAct(row)"
                                >{{ row.kind === 'murabaha' ? 'Finalize sale' : 'Disburse' }}</button>
                                <button
                                    v-else-if="liveStatuses.includes(row.status) && can('islamic.manage')"
                                    type="button"
                                    class="text-sm font-medium text-teal-800"
                                    @click="openRepay(row)"
                                >Repay</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <Modal :show="qardOpen" title="New Qard Hasan" subtitle="An interest-free facility. Only principal is repaid." wide @close="qardOpen = false">
            <form class="space-y-4" @submit.prevent="submitQard">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Member" :error="qardForm.errors.member_uuid">
                        <AppSelect v-model="qardForm.member_uuid" placeholder="Select member" :options="members" option-value="uuid" variant="outline" required />
                    </FormField>
                    <FormField label="Product" :error="qardForm.errors.product_uuid">
                        <AppSelect v-model="qardForm.product_uuid" placeholder="Select product" :options="qardProducts" option-value="uuid" option-label="name" variant="outline" required />
                    </FormField>
                    <FormField label="Amount (₦)" :error="qardForm.errors.amount" :hint="productRange(selectedQard)">
                        <AmountInput v-model="qardForm.amount" :class="input" required />
                    </FormField>
                    <FormField label="Tenure (months)" :error="qardForm.errors.tenure_months">
                        <input v-model="qardForm.tenure_months" type="number" min="1" :class="input">
                    </FormField>
                    <FormField label="Purpose" class="sm:col-span-2"><input v-model="qardForm.purpose" :class="input"></FormField>
                    <div class="sm:col-span-2">
                        <LoanEligibilityChecklist
                            :checks="qardChecks"
                            :loading="qardChecking"
                            :ready="qardReady"
                            :error="qardForm.errors.eligibility"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="qardOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="qardForm.processing || (qardReady && !qardEligible)">Submit Qard Hasan</AppButton>
                </div>
            </form>
        </Modal>

        <Modal :show="murabahaOpen" title="New Murabaha" subtitle="Sale price is cost plus a profit disclosed up front. Interest is never added." wide @close="murabahaOpen = false">
            <form class="space-y-4" @submit.prevent="submitMurabaha">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Member" :error="murabahaForm.errors.member_uuid">
                        <AppSelect v-model="murabahaForm.member_uuid" placeholder="Select member" :options="members" option-value="uuid" variant="outline" required />
                    </FormField>
                    <FormField label="Product" :error="murabahaForm.errors.product_uuid">
                        <AppSelect v-model="murabahaForm.product_uuid" placeholder="Select product" :options="murabahaProducts" option-value="uuid" option-label="name" variant="outline" required />
                    </FormField>
                    <FormField label="Asset" class="sm:col-span-2" :error="murabahaForm.errors.asset_description"><input v-model="murabahaForm.asset_description" :class="input" required></FormField>
                    <FormField label="Supplier"><input v-model="murabahaForm.supplier" :class="input"></FormField>
                    <FormField label="Tenure (months)" :error="murabahaForm.errors.tenure_months">
                        <input v-model="murabahaForm.tenure_months" type="number" min="1" :class="input">
                    </FormField>
                    <FormField label="Cost (₦)" :error="murabahaForm.errors.purchase_cost" :hint="productRange(selectedMurabaha)">
                        <AmountInput v-model="murabahaForm.purchase_cost" :class="input" required />
                    </FormField>
                    <FormField label="Profit (₦)" :error="murabahaForm.errors.profit"><AmountInput v-model="murabahaForm.profit" :class="input" required /></FormField>
                    <FormField label="Down payment (₦)"><AmountInput v-model="murabahaForm.down_payment" :class="input" /></FormField>
                    <div class="rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sale price</div>
                        <div class="mt-1 font-semibold tabular-nums">₦{{ murabahaSale.toLocaleString() }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <LoanEligibilityChecklist
                            :checks="murabahaChecks"
                            :loading="murabahaChecking"
                            :ready="murabahaReady"
                            :error="murabahaForm.errors.eligibility"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="murabahaOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="murabahaForm.processing || (murabahaReady && !murabahaEligible)">Submit Murabaha</AppButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(pendingAct)"
            :title="pendingAct?.title || 'Confirm'"
            :message="pendingAct?.message || ''"
            :confirm-label="pendingAct?.label || 'Confirm'"
            @close="pendingAct = null"
            @confirm="confirmAct"
        />

        <RepaymentModal
            :show="Boolean(repayTarget)"
            :title="repayTarget ? `Repay ${repayTarget.product_name}` : 'Record repayment'"
            :subtitle="repayTarget ? `${repayTarget.member} · ${repayTarget.kind_label}` : ''"
            :action="repayAction"
            :product="repayTarget?.product_name || ''"
            :amount="repayTarget?.next_amount_naira || ''"
            :outstanding="repayTarget?.outstanding || ''"
            @close="closeRepay"
        />

        <Modal
            :show="Boolean(viewTarget)"
            title="Repayments"
            :subtitle="viewTarget ? `${viewTarget.member} · ${viewTarget.product_name} · ${viewTarget.kind_label}` : ''"
            wide
            @close="closeView"
        >
            <div v-if="viewTarget" class="overflow-hidden rounded-2xl ring-1 ring-slate-200">
                <section class="bg-slate-50 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge :value="viewTarget.status" />
                                <span v-if="viewTarget.tenure_months" class="text-sm text-slate-600">{{ viewTarget.tenure_months }} months</span>
                            </div>
                            <p v-if="viewTarget.account" class="mt-1 font-mono text-xs text-slate-400">{{ viewTarget.account }}</p>
                            <p v-if="viewTarget.purpose || viewTarget.asset" class="mt-2 text-sm leading-relaxed text-slate-500">
                                {{ viewTarget.purpose || viewTarget.asset }}
                            </p>
                        </div>
                        <AppButton
                            v-if="liveStatuses.includes(viewTarget.status) && can('islamic.manage')"
                            type="button"
                            variant="gold"
                            class="shrink-0"
                            @click="startRepayFromView"
                        >
                            Record repayment
                        </AppButton>
                    </div>

                    <div class="mt-4 grid gap-px overflow-hidden rounded-xl bg-slate-200 sm:grid-cols-3">
                        <div class="bg-white px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ viewTarget.kind === 'murabaha' ? 'Sale price' : 'Principal' }}</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums tracking-tight text-slate-900">{{ viewTarget.amount }}</div>
                            <div v-if="viewTarget.kind === 'murabaha'" class="mt-1 text-xs text-slate-500">Cost {{ viewTarget.cost }} · Profit {{ viewTarget.profit }}</div>
                            <div v-else-if="viewTarget.total && viewTarget.total !== viewTarget.amount" class="mt-1 text-xs text-slate-500">Total repayable {{ viewTarget.total }}</div>
                        </div>
                        <div class="bg-white px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount paid</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums tracking-tight text-slate-900">{{ viewTarget.amount_paid || '₦0' }}</div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ viewTarget.transaction_count ?? 0 }} {{ (viewTarget.transaction_count || 0) === 1 ? 'transaction' : 'transactions' }}
                                <span v-if="viewTarget.installments_due"> · {{ viewTarget.installments_paid || 0 }}/{{ viewTarget.installments_due }} paid</span>
                            </div>
                        </div>
                        <div class="bg-white px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Outstanding</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums tracking-tight text-slate-900">{{ viewTarget.outstanding }}</div>
                            <div v-if="viewTarget.next_due" class="mt-1 text-xs text-slate-500">
                                Next due {{ formatDate(viewTarget.next_due) }}<span v-if="viewTarget.next_amount"> · {{ viewTarget.next_amount }}</span>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
                        <div class="flex gap-1.5">
                            <dt class="text-slate-500">Disbursed</dt>
                            <dd class="tabular-nums font-medium text-slate-800">{{ formatDate(viewTarget.disbursed_on) }}</dd>
                        </div>
                        <div class="flex gap-1.5">
                            <dt class="text-slate-500">Matures</dt>
                            <dd class="tabular-nums font-medium text-slate-800">{{ formatDate(viewTarget.maturity_on) }}</dd>
                        </div>
                        <div v-if="viewTarget.kind === 'murabaha'" class="flex gap-1.5">
                            <dt class="text-slate-500">Down payment</dt>
                            <dd class="tabular-nums font-medium text-slate-800">{{ viewTarget.down_payment || '₦0' }}</dd>
                        </div>
                        <div v-else class="flex gap-1.5">
                            <dt class="text-slate-500">Admin fee</dt>
                            <dd class="tabular-nums font-medium text-slate-800">{{ viewTarget.admin_fee || '₦0' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="border-t border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Repayments</h3>
                        <span class="text-xs text-slate-500">{{ viewTarget.repayments?.length || 0 }} posted</span>
                    </div>
                    <EmptyState
                        v-if="!viewTarget.repayments?.length"
                        compact
                        icon="loans"
                        title="No repayments yet"
                        :body="liveStatuses.includes(viewTarget.status)
                            ? 'Nothing has been posted against this facility. Record the first installment when the member pays.'
                            : 'Repayments will appear here after this facility is disbursed and payments are posted.'"
                    >
                        <AppButton
                            v-if="liveStatuses.includes(viewTarget.status) && can('islamic.manage')"
                            type="button"
                            variant="gold"
                            @click="startRepayFromView"
                        >
                            Record repayment
                        </AppButton>
                    </EmptyState>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-2.5">Product</th>
                                <th class="px-5 py-2.5">Amount (₦)</th>
                                <th class="px-5 py-2.5">Channel</th>
                                <th class="px-5 py-2.5">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in viewTarget.repayments" :key="item.uuid" class="border-t border-slate-100">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ item.product }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ item.amount }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ item.channel }}</td>
                                <td class="px-5 py-3 tabular-nums text-slate-500">{{ formatDate(item.paid_on) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </Modal>
</template>
