<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import Modal from '../../../Components/Modal.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import AppSelect from '../../../Components/AppSelect.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { parseAmount } from '../../../money';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    tab: { type: String, default: 'view' },
    enrollments: { type: Object, default: null },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    column_totals: { type: Object, default: () => ({}) },
    grand_total: { type: String, default: '₦0' },
    branch: { type: Object, default: null },
    member: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    can_filter_branches: { type: Boolean, default: false },
    needs_branch: { type: Boolean, default: false },
    members: Array,
    products: Array,
    member_plans: { type: Object, default: () => ({}) },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const enrollOpen = ref(false);
const confirmEnrollOpen = ref(false);
const pendingRemove = ref(null);
const removing = ref(false);
const memberFilter = ref(props.member?.uuid || '');
const branchFilter = ref(props.branch?.uuid || '');
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium tabular-nums text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20 disabled:bg-slate-100 disabled:text-slate-500';

watch(() => props.member?.uuid, (value) => {
    memberFilter.value = value || '';
});
watch(() => props.branch?.uuid, (value) => {
    branchFilter.value = value || '';
});

const isList = computed(() => props.tab === 'list');

const tabClass = (active) => active
    ? 'bg-[#071c1b] text-white shadow-sm'
    : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50';

const branchOptions = computed(() => (props.branches || []).map((b) => ({
    value: b.uuid || '',
    label: b.name,
})));

const showBranchFilter = computed(() => props.can_filter_branches && branchOptions.value.length > 0);

const filterMemberOptions = computed(() => [
    { value: '', label: 'All members' },
    ...(props.members || []).map((m) => ({
        value: m.uuid,
        label: `${m.first_name} ${m.last_name} · ${m.membership_number}`,
    })),
]);

const reload = (overrides = {}) => {
    router.get(`${base}/contributions/enrollments`, {
        tab: (overrides.tab ?? props.tab) === 'list' ? 'list' : undefined,
        member: (overrides.member ?? memberFilter.value) || undefined,
        branch: (overrides.branch ?? branchFilter.value) || undefined,
    }, { preserveState: false, preserveScroll: true });
};

const goTab = (tab) => {
    reload({ tab });
};

const onMemberFilter = (value) => {
    memberFilter.value = value || '';
    reload({ member: value || '' });
};

const onBranchFilter = (value) => {
    branchFilter.value = value || '';
    reload({ branch: value || '' });
};

const cellClass = (value) => (value === '—' ? 'text-slate-300' : 'text-slate-900');

const productLine = (product, enrollment = null) => {
    const isRequired = Boolean(product.is_required);
    const isFixed = Boolean(product.amount_is_fixed);
    const fixedRequired = isRequired && isFixed;
    const enrolled = Boolean(enrollment);

    return {
        selected: fixedRequired ? false : (enrolled || (isRequired && ! isFixed)),
        product_uuid: product.uuid,
        name: product.name,
        is_required: isRequired,
        amount_is_fixed: isFixed,
        fixed_required: fixedRequired,
        enrolled,
        amount: parseAmount(enrollment?.default_amount ?? product.default_amount ?? ''),
    };
};

const buildProductLines = (memberUuid = '') => {
    const plans = memberUuid ? (props.member_plans?.[memberUuid] || []) : [];
    const byUuid = Object.fromEntries(plans.map((plan) => [plan.uuid, plan]));

    return (props.products || []).map((product) => productLine(product, byUuid[product.uuid] || null));
};

const form = useForm({
    member_uuid: '',
    member_uuids: [],
    enroll_all: false,
    products: [],
});

const memberOptions = computed(() => (props.members || []).map((m) => ({
    uuid: m.uuid,
    name: `${m.first_name} ${m.last_name}`,
    membership_number: m.membership_number,
})));

const requiredProducts = computed(() => form.products.filter((line) => line.is_required && ! line.amount_is_fixed));
const optionalProducts = computed(() => form.products.filter((line) => ! line.is_required));
const fixedRequiredProducts = computed(() => form.products.filter((line) => line.is_required && line.amount_is_fixed));
const selectedProducts = computed(() => form.products.filter((line) => {
    if (line.fixed_required) {
        return false;
    }
    return line.selected || (line.is_required && ! line.amount_is_fixed);
}));
const selectedCount = computed(() => selectedProducts.value.length);
const optionalSelectedCount = computed(() => optionalProducts.value.filter((line) => line.selected).length);
const memberHasEnrollments = computed(() => {
    if (form.enroll_all || ! form.member_uuid) {
        return false;
    }
    return (props.member_plans?.[form.member_uuid] || []).length > 0;
});

const selectedMemberLabel = computed(() => {
    if (form.enroll_all) {
        const total = (props.members || []).length;
        return `all ${total} active ${total === 1 ? 'member' : 'members'}`;
    }
    const member = memberOptions.value.find((m) => m.uuid === form.member_uuid);
    return member ? `${member.name} (${member.membership_number})` : 'the selected member';
});

const enrollConfirmMessage = computed(() => {
    const productWord = selectedCount.value === 1 ? 'product' : 'products';
    if (memberHasEnrollments.value) {
        return `Update mandate for ${selectedMemberLabel.value} to ${selectedCount.value} ${productWord}?`;
    }
    return `Add a mandate for ${selectedMemberLabel.value} on ${selectedCount.value} ${productWord}?`;
});

const removeMessage = computed(() => {
    if (! pendingRemove.value) {
        return '';
    }
    return `Remove “${pendingRemove.value.member}” from ${pendingRemove.value.product}? You can add this mandate again later.`;
});

watch(enrollOpen, (open) => {
    if (! open) {
        confirmEnrollOpen.value = false;
        return;
    }
    form.clearErrors();
    form.member_uuid = '';
    form.member_uuids = [];
    form.enroll_all = false;
    form.products = buildProductLines();
});

watch(() => form.member_uuid, (uuid) => {
    if (! enrollOpen.value || form.enroll_all) {
        return;
    }
    form.products = buildProductLines(uuid || '');
});

const setScope = (all) => {
    form.enroll_all = all;
    if (all) {
        form.member_uuid = '';
        form.clearErrors('member_uuid');
        form.products = buildProductLines();
        return;
    }
    form.products = buildProductLines(form.member_uuid || '');
};

const toggleOptional = (line) => {
    if (line.is_required || line.fixed_required) {
        return;
    }
    line.selected = ! line.selected;
};

const selectAllOptional = () => {
    optionalProducts.value.forEach((line) => {
        line.selected = true;
    });
};

const clearOptional = () => {
    optionalProducts.value.forEach((line) => {
        line.selected = false;
    });
};

const askEnroll = () => {
    form.products.forEach((line) => {
        if (line.fixed_required) {
            line.selected = false;
            return;
        }
        if (line.is_required && ! line.amount_is_fixed) {
            line.selected = true;
        }
    });

    if (! selectedCount.value) {
        return;
    }
    if (! form.enroll_all && ! form.member_uuid) {
        form.setError('member_uuid', 'Select a member first.');
        return;
    }
    form.clearErrors('member_uuid');
    confirmEnrollOpen.value = true;
};

const confirmEnroll = () => {
    const products = selectedProducts.value.map((line) => ({
        product_uuid: line.product_uuid,
        amount: parseAmount(line.amount) || 0,
    }));

    form.member_uuids = form.enroll_all ? (props.members || []).map((m) => m.uuid) : [];
    if (form.enroll_all) {
        form.member_uuid = '';
    }

    form.transform((data) => ({
        member_uuid: data.enroll_all ? undefined : data.member_uuid,
        member_uuids: data.enroll_all ? data.member_uuids : undefined,
        products,
    })).post(`${base}/contributions/enroll`, {
        onSuccess: () => {
            confirmEnrollOpen.value = false;
            enrollOpen.value = false;
            form.reset();
        },
        onError: () => {
            confirmEnrollOpen.value = false;
        },
    });
};

const memberInitials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('') || 'M';
};

const formatDate = (value) => {
    if (! value) {
        return '—';
    }
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const listMemberCount = computed(() => props.enrollments?.total || 0);

const askRemove = (plan, group) => {
    pendingRemove.value = {
        uuid: plan.uuid,
        member: group.member,
        product: plan.product,
    };
};

const confirmRemove = () => {
    if (! pendingRemove.value) {
        return;
    }
    removing.value = true;
    router.post(`${base}/contributions/enrollments/${pendingRemove.value.uuid}/cancel`, {}, {
        onFinish: () => {
            removing.value = false;
            pendingRemove.value = null;
        },
    });
};
</script>

<template>
    <Head title="Mandates" />
    <PageHeader eyebrow="Savings" title="Mandates">
        <template #actions>
            <AppButton :href="`${base}/contributions`" variant="secondary">Savings</AppButton>
            <AppButton v-if="can('contributions.manage')" type="button" variant="gold" :disabled="!products.length" @click="enrollOpen = true">Add mandate</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-black/5 sm:p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="tabClass(!isList)"
                    @click="goTab('view')"
                >
                    View mandates
                </button>
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="tabClass(isList)"
                    @click="goTab('list')"
                >
                    List
                </button>
            </div>

            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                <div v-if="!needs_branch" class="w-full sm:w-64">
                    <AppSelect
                        :model-value="memberFilter"
                        :options="filterMemberOptions"
                        size="sm"
                        searchable
                        @update:model-value="onMemberFilter"
                    />
                </div>
                <div v-if="showBranchFilter" class="w-full sm:w-48">
                    <AppSelect
                        :model-value="branchFilter"
                        :options="branchOptions"
                        placeholder="Select branch"
                        size="sm"
                        @update:model-value="onBranchFilter"
                    />
                </div>
            </div>
        </div>
    </div>

    <template v-if="!isList">
        <AppCard class="mt-6" :padded="false">
            <EmptyState
                v-if="needs_branch"
                icon="savings"
                title="Select a branch"
                body="Choose a branch to view mandates by product."
            />
            <EmptyState
                v-else-if="!rows.length"
                icon="savings"
                title="No mandates yet"
                body="Add a mandate on savings plans to see amounts by product."
            />
            <div v-else class="max-h-[min(70vh,52rem)] overflow-auto">
                <table class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            <th class="sticky left-0 top-0 z-30 bg-slate-50 px-4 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Mem no</th>
                            <th class="sticky left-24 top-0 z-30 min-w-[10rem] bg-slate-50 px-4 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Name</th>
                            <th class="sticky top-0 z-20 whitespace-nowrap bg-slate-50 px-3 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Branch</th>
                            <th
                                v-for="col in columns"
                                :key="col.uuid"
                                class="sticky top-0 z-20 whitespace-nowrap bg-slate-50 px-3 py-3 text-right shadow-[inset_0_-1px_0_0_rgb(226_232_240)]"
                                :title="col.name"
                            >
                                {{ col.label || col.name }}
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
                                class="sticky left-24 z-10 min-w-[10rem] px-4 py-3.5 font-medium text-slate-900 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                                :class="index % 2 ? 'bg-slate-50 group-hover:bg-teal-50' : 'bg-white group-hover:bg-teal-50'"
                            >
                                {{ row.name }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-slate-600 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]">
                                {{ row.branch || '—' }}
                            </td>
                            <td
                                v-for="col in columns"
                                :key="`${row.uuid}-${col.uuid}`"
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
                                All products
                            </td>
                            <td class="sticky bottom-0 z-20 bg-slate-200 px-3 py-3.5 shadow-[inset_0_1px_0_0_rgb(203_213_225)]"></td>
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
        <AppCard class="mt-4" title="Active mandates" :padded="false">
            <template v-if="listMemberCount" #header>
                <p class="text-sm text-slate-500">
                    {{ listMemberCount }} {{ listMemberCount === 1 ? 'member' : 'members' }}
                </p>
            </template>
            <EmptyState
                v-if="needs_branch"
                icon="savings"
                title="Select a branch"
                body="Choose a branch to view active mandates."
            />
            <EmptyState v-else-if="!enrollments?.data?.length" icon="savings" title="No mandates yet" body="Add a mandate on the products the member should save into." />
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            <th class="bg-slate-50 py-3 pl-16 pr-5">Product</th>
                            <th class="bg-slate-50 px-5 py-3 text-right">Amount</th>
                            <th class="bg-slate-50 px-5 py-3">Started</th>
                            <th class="bg-slate-50 px-5 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody v-for="group in enrollments.data" :key="group.uuid">
                        <tr class="border-t border-slate-200 bg-teal-50/50">
                            <td colspan="4" class="px-5 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#071c1b] text-xs font-semibold text-[#d4b06a]">
                                            {{ memberInitials(group.member) }}
                                        </div>
                                        <div class="min-w-0">
                                            <Link :href="`${base}/members/${group.uuid}`" class="font-medium text-slate-900 hover:text-teal-800">
                                                {{ group.member }}
                                            </Link>
                                            <div class="text-xs text-slate-500">
                                                {{ group.membership_number }}
                                                <span v-if="group.branch"> · {{ group.branch }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="font-semibold tabular-nums text-slate-900">{{ group.total }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ group.plans.length }} {{ group.plans.length === 1 ? 'plan' : 'plans' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="(plan, index) in group.plans"
                            :key="plan.uuid"
                            class="border-t border-slate-100 transition-colors hover:bg-teal-50/40"
                            :class="index % 2 ? 'bg-slate-50/40' : 'bg-white'"
                        >
                            <td class="py-3 pl-16 pr-5 font-medium text-slate-900">{{ plan.product }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-900">{{ plan.amount }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-slate-500">{{ formatDate(plan.starts_on) }}</td>
                            <td class="px-5 py-3 text-right">
                                <button
                                    v-if="can('contributions.manage')"
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium text-rose-700 ring-1 ring-rose-200 transition hover:bg-rose-50"
                                    @click="askRemove(plan, group)"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template v-if="enrollments" #footer>
                <Pagination :links="enrollments.links" :from="enrollments.from" :to="enrollments.to" :total="enrollments.total" />
            </template>
        </AppCard>
    </template>

    <Modal
        :show="enrollOpen"
        title="Add mandate"
        subtitle="Set savings amounts for a member. Fixed required fees are listed for reference and paid when you record payment."
        wide
        @close="enrollOpen = false"
    >
        <form class="flex flex-col gap-6" @submit.prevent="askEnroll">
            <section class="space-y-3">
                <div class="inline-flex rounded-full bg-slate-100 p-1">
                    <button
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                        :class="!form.enroll_all ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="setScope(false)"
                    >
                        One member
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                        :class="form.enroll_all ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="setScope(true)"
                    >
                        All active members
                    </button>
                </div>

                <FormField v-if="!form.enroll_all" label="Member" :error="form.errors.member_uuid">
                    <AppSelect
                        v-model="form.member_uuid"
                        placeholder="Search or select a member"
                        :options="memberOptions"
                        option-value="uuid"
                        option-label="name"
                        :required="!form.enroll_all"
                        searchable
                        variant="outline"
                    />
                </FormField>
                <p v-if="!form.enroll_all && memberHasEnrollments" class="rounded-2xl bg-teal-50 px-4 py-3 text-sm text-teal-950">
                    Current mandates loaded. Change amounts or optional products, then continue to update.
                </p>
                <p v-else-if="form.enroll_all" class="rounded-2xl bg-teal-50 px-4 py-3 text-sm text-teal-950">
                    This will add mandates for <span class="font-semibold">{{ members.length }}</span> active {{ members.length === 1 ? 'member' : 'members' }} on the selected products.
                </p>
            </section>

            <section class="space-y-5">
                <p v-if="form.errors.products" class="text-xs font-medium text-rose-700">{{ form.errors.products }}</p>
                <p v-if="!products.length" class="text-sm text-slate-500">Create a savings product first.</p>

                <div v-if="requiredProducts.length" class="space-y-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Required</h3>
                        <span class="text-xs text-slate-400">Always on</span>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div
                            v-for="line in requiredProducts"
                            :key="line.product_uuid"
                            class="flex items-center gap-3 border-t border-slate-100 px-4 py-3 first:border-t-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-900">{{ line.name }}</div>
                                <div class="text-xs text-slate-500">{{ line.enrolled ? 'Current mandate' : 'Editable amount' }}</div>
                            </div>
                            <div class="w-44 shrink-0 sm:w-52">
                                <label class="sr-only">Amount for {{ line.name }}</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
                                    <AmountInput
                                        v-model="line.amount"
                                        :class="[input, 'pl-7']"
                                    />
                                </div>
                                <p v-if="form.errors[`products.${form.products.indexOf(line)}.amount`]" class="mt-1 text-xs text-rose-600">
                                    {{ form.errors[`products.${form.products.indexOf(line)}.amount`] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="optionalProducts.length" class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Optional</h3>
                            <span class="text-xs text-slate-400">{{ optionalSelectedCount }} of {{ optionalProducts.length }}</span>
                        </div>
                        <div class="flex gap-2 text-xs font-medium">
                            <button type="button" class="text-teal-800 hover:underline" @click="selectAllOptional">Select all</button>
                            <span class="text-slate-300">·</span>
                            <button type="button" class="text-slate-500 hover:underline" @click="clearOptional">Clear</button>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div
                            v-for="line in optionalProducts"
                            :key="line.product_uuid"
                            class="flex items-center gap-3 border-t border-slate-100 px-4 py-3 first:border-t-0"
                            :class="line.selected ? 'bg-teal-50/40' : 'bg-white'"
                        >
                            <button
                                type="button"
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded border transition"
                                :class="line.selected ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-300 bg-white text-transparent'"
                                :aria-pressed="line.selected"
                                @click="toggleOptional(line)"
                            >
                                <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                    <path d="M2.5 6.2 4.8 8.5 9.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button type="button" class="min-w-0 flex-1 text-left" @click="toggleOptional(line)">
                                <div class="font-medium text-slate-900">{{ line.name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ line.enrolled ? 'Current mandate' : (line.amount_is_fixed ? 'Fixed amount' : 'Optional plan') }}
                                </div>
                            </button>
                            <div class="w-44 shrink-0 sm:w-52">
                                <label class="sr-only">Amount for {{ line.name }}</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
                                    <AmountInput
                                        v-model="line.amount"
                                        :class="[input, 'pl-7']"
                                        :disabled="!line.selected || line.amount_is_fixed"
                                        :readonly="line.amount_is_fixed"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="fixedRequiredProducts.length" class="space-y-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Fixed required</h3>
                        <span class="text-xs text-slate-400">No mandate · pay via Record payment</span>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50/70">
                        <div
                            v-for="line in fixedRequiredProducts"
                            :key="line.product_uuid"
                            class="flex items-center gap-3 border-t border-slate-100 px-4 py-3 first:border-t-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-700">{{ line.name }}</div>
                                <div class="text-xs text-slate-500">Fixed amount</div>
                            </div>
                            <div class="w-44 shrink-0 sm:w-52">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
                                    <AmountInput
                                        v-model="line.amount"
                                        :class="[input, 'pl-7']"
                                        disabled
                                        readonly
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="sticky bottom-0 -mx-5 -mb-5 flex flex-col-reverse gap-2 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    <span class="font-medium text-slate-800">{{ selectedCount }}</span>
                    {{ selectedCount === 1 ? 'product' : 'products' }} selected
                </p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <AppButton type="button" variant="secondary" @click="enrollOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing || !selectedCount">
                        Continue
                    </AppButton>
                </div>
            </div>
        </form>
    </Modal>

    <ConfirmModal
        :show="confirmEnrollOpen"
        :title="memberHasEnrollments ? 'Update mandate' : 'Confirm mandate'"
        :message="enrollConfirmMessage"
        :confirm-label="memberHasEnrollments ? 'Update mandate' : 'Add mandate'"
        variant="gold"
        :processing="form.processing"
        @close="confirmEnrollOpen = false"
        @confirm="confirmEnroll"
    />

    <ConfirmModal
        :show="Boolean(pendingRemove)"
        title="Remove mandate"
        :message="removeMessage"
        confirm-label="Remove"
        variant="danger"
        :processing="removing"
        @close="pendingRemove = null"
        @confirm="confirmRemove"
    />
</template>
