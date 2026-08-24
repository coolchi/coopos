<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import Modal from '../../../Components/Modal.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import AppSelect from '../../../Components/AppSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { parseAmount } from '../../../money';

const props = defineProps({
    year: Number,
    as_of: String,
    locked: { type: Boolean, default: false },
    locked_at: { type: String, default: null },
    locked_by: { type: String, default: null },
    posted_count: { type: Number, default: 0 },
    can_lock: { type: Boolean, default: false },
    can_unlock: { type: Boolean, default: false },
    can_filter_branches: { type: Boolean, default: false },
    needs_branch: { type: Boolean, default: false },
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const cells = reactive({});
const confirmLockOpen = ref(false);
const unlockOpen = ref(false);
const locking = ref(false);
const branchFilter = ref(props.branch?.uuid || '');

const branchOptions = computed(() => (props.branches || []).map((b) => ({
    value: b.uuid || '',
    label: b.name,
})));

const showBranchFilter = computed(() => props.can_filter_branches && branchOptions.value.length > 0);

watch(() => props.branch?.uuid, (value) => {
    branchFilter.value = value || '';
});

const amountNumber = (value) => Number(parseAmount(value)) || 0;

const displayMoney = (value) => {
    const amount = Number(value) || 0;
    if (amount <= 0) {
        return '—';
    }

    const whole = Number.isInteger(amount);
    return `₦${amount.toLocaleString('en-NG', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 })}`;
};

const hydrate = () => {
    Object.keys(cells).forEach((key) => delete cells[key]);
    props.rows.forEach((row) => {
        cells[row.uuid] = {};
        props.columns.forEach((col) => {
            const amount = Number(row.cells?.[col.uuid] || 0);
            cells[row.uuid][col.uuid] = amount > 0 ? parseAmount(amount) : '';
        });
    });
};

hydrate();
watch(() => [props.year, props.rows], hydrate, { deep: true });

const form = useForm({
    year: props.year,
    entries: [],
});

const unlockForm = useForm({
    access_code: '',
});

const cellPosted = (row, colUuid) => Boolean(row?.posted?.[colUuid]);

const cellAmount = (row, colUuid) => {
    const live = cells[row.uuid]?.[colUuid];
    if (live !== undefined && live !== '') {
        return amountNumber(live);
    }

    return amountNumber(row.cells?.[colUuid]);
};

const rowTotal = (row) => props.columns.reduce((sum, col) => sum + cellAmount(row, col.uuid), 0);

const columnTotals = computed(() => {
    const totals = {};
    props.columns.forEach((col) => {
        totals[col.uuid] = props.rows.reduce((sum, row) => sum + cellAmount(row, col.uuid), 0);
    });

    return totals;
});

const grandTotal = computed(() => props.rows.reduce((sum, row) => sum + rowTotal(row), 0));

const draftCount = computed(() => {
    let count = 0;
    props.rows.forEach((row) => {
        props.columns.forEach((col) => {
            if (cellPosted(row, col.uuid)) {
                return;
            }
            if (amountNumber(cells[row.uuid]?.[col.uuid]) > 0) {
                count += 1;
            }
        });
    });

    return count;
});

const asOfLabel = computed(() => {
    if (! props.as_of) {
        return '';
    }
    const date = new Date(`${props.as_of}T12:00:00`);
    if (Number.isNaN(date.getTime())) {
        return props.as_of;
    }

    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
});

const reload = (overrides = {}) => router.get(`${base}/opening-balances`, {
    year: form.year,
    branch: (overrides.branch ?? branchFilter.value) || undefined,
}, { preserveState: false });

const onBranchFilter = (value) => {
    branchFilter.value = value || '';
    reload({ branch: value || '' });
};

const setAmount = (rowUuid, colUuid, value) => {
    if (! cells[rowUuid]) {
        cells[rowUuid] = {};
    }
    cells[rowUuid][colUuid] = parseAmount(value);
};

const submit = () => {
    if (props.locked) {
        return;
    }
    form.year = props.year;
    form.entries = props.rows.flatMap((row) => props.columns
        .filter((col) => ! cellPosted(row, col.uuid))
        .map((col) => ({
            member_uuid: row.uuid,
            product_uuid: col.uuid,
            amount: amountNumber(cells[row.uuid]?.[col.uuid]),
        }))
        .filter((entry) => entry.amount > 0));
    form.post(`${base}/opening-balances`);
};

const confirmLock = () => {
    locking.value = true;
    router.post(`${base}/opening-balances/lock`, {}, {
        onFinish: () => {
            locking.value = false;
            confirmLockOpen.value = false;
        },
    });
};

const submitUnlock = () => {
    unlockForm.post(`${base}/opening-balances/unlock`, {
        onSuccess: () => {
            unlockOpen.value = false;
            unlockForm.reset();
        },
    });
};

</script>

<template>
    <Head title="Brought forward" />
    <PageHeader
        eyebrow="Savings"
        title="Brought forward"
    >
        <template #actions>
            <AppButton
                v-if="can_unlock"
                type="button"
                variant="secondary"
                @click="unlockOpen = true"
            >
                Unlock
            </AppButton>
            <AppButton
                v-if="can_lock"
                type="button"
                variant="secondary"
                :disabled="!rows.length"
                @click="confirmLockOpen = true"
            >
                Lock cutover
            </AppButton>
            <AppButton
                v-if="can_lock"
                type="button"
                variant="gold"
                :disabled="form.processing || !rows.length || draftCount === 0"
                @click="submit"
            >
                Save{{ draftCount ? ` (${draftCount})` : '' }}
            </AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="locked ? 'bg-slate-100 text-slate-700 ring-slate-200' : 'bg-teal-50 text-teal-900 ring-teal-200'"
                >
                    {{ locked ? 'Locked' : 'Open for cutover' }}
                </span>
                <p class="text-sm text-slate-600">
                    <template v-if="locked">
                        Locked{{ locked_at ? ` on ${locked_at}` : '' }}{{ locked_by ? ` by ${locked_by}` : '' }}
                    </template>
                    <template v-else>
                        <span class="font-medium text-slate-800">{{ posted_count }}</span> posted
                        <span class="text-slate-300">·</span>
                        <span class="font-medium text-slate-800">{{ draftCount }}</span> ready to save
                    </template>
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div v-if="showBranchFilter" class="w-full sm:w-52">
                    <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Branch</span>
                    <AppSelect
                        class="mt-1.5"
                        :model-value="branchFilter"
                        :options="branchOptions"
                        placeholder="Select branch"
                        size="sm"
                        @update:model-value="onBranchFilter"
                    />
                </div>
                <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Fiscal year start
                    <input
                        v-model="form.year"
                        type="number"
                        min="2000"
                        class="mt-1.5 block w-28 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 disabled:bg-slate-100"
                        :disabled="locked || !can_lock"
                        @change="reload"
                    >
                </label>
                <p class="pb-2.5 text-sm text-slate-500">
                    As of <span class="font-medium text-slate-700">{{ asOfLabel }}</span>
                </p>
            </div>
        </div>
    </div>

    <AppCard class="mt-6" :padded="false">
        <EmptyState
            v-if="needs_branch"
            icon="members"
            title="Select a branch"
            body="Choose a branch to view and post brought-forward balances."
        />
        <EmptyState
            v-else-if="!rows.length"
            icon="members"
            title="No active members"
            body="Activate members before posting brought-forward balances."
        />
        <div v-else class="max-h-[min(70vh,52rem)] overflow-auto">
            <table class="min-w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        <th class="sticky left-0 top-0 z-30 bg-slate-50 px-4 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Mem no</th>
                        <th class="sticky left-24 top-0 z-30 min-w-[11rem] bg-slate-50 px-4 py-3 shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Name</th>
                        <th
                            v-for="col in columns"
                            :key="col.uuid"
                            class="sticky top-0 z-20 min-w-[8.5rem] whitespace-nowrap bg-slate-50 px-3 py-3 text-right shadow-[inset_0_-1px_0_0_rgb(226_232_240)]"
                        >
                            {{ col.label }}
                        </th>
                        <th class="sticky top-0 z-20 min-w-[8.5rem] bg-slate-50 px-4 py-3 text-right shadow-[inset_0_-1px_0_0_rgb(226_232_240)]">Total</th>
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
                            class="sticky left-0 z-10 px-4 py-2.5 font-medium text-teal-900 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                            :class="index % 2 ? 'bg-slate-50 group-hover:bg-teal-50' : 'bg-white group-hover:bg-teal-50'"
                        >
                            {{ row.number }}
                        </td>
                        <td
                            class="sticky left-24 z-10 min-w-[11rem] px-4 py-2.5 font-medium text-slate-900 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                            :class="index % 2 ? 'bg-slate-50 group-hover:bg-teal-50' : 'bg-white group-hover:bg-teal-50'"
                        >
                            <div>{{ row.name }}</div>
                        </td>
                        <td
                            v-for="col in columns"
                            :key="col.uuid"
                            class="px-2 py-2 text-right shadow-[inset_0_-1px_0_0_rgb(241_245_249)]"
                        >
                            <div
                                v-if="locked || !can_lock || cellPosted(row, col.uuid)"
                                class="inline-flex min-w-[7.5rem] justify-end rounded-xl px-3 py-2 tabular-nums"
                                :class="cellPosted(row, col.uuid)
                                    ? 'bg-teal-50 font-medium text-teal-950'
                                    : 'text-slate-400'"
                                :title="cellPosted(row, col.uuid) ? 'Already posted' : ''"
                            >
                                {{ displayMoney(row.cells[col.uuid]) }}
                            </div>
                            <div v-else class="relative ml-auto w-[8.5rem]">
                                <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-xs text-slate-400">₦</span>
                                <AmountInput
                                    :model-value="cells[row.uuid]?.[col.uuid] ?? ''"
                                    placeholder="0"
                                    class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-6 pr-2.5 text-right text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-300 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20"
                                    @update:model-value="setAmount(row.uuid, col.uuid, $event)"
                                />
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900 shadow-[inset_0_-1px_0_0_rgb(241_245_249)]">
                            {{ displayMoney(rowTotal(row)) }}
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
                            :class="columnTotals[col.uuid] > 0 ? 'text-slate-900' : 'text-slate-500'"
                        >
                            {{ displayMoney(columnTotals[col.uuid]) }}
                        </td>
                        <td class="sticky bottom-0 z-20 bg-slate-200 px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 shadow-[inset_0_1px_0_0_rgb(203_213_225)]">
                            {{ displayMoney(grandTotal) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </AppCard>

    <ConfirmModal
        :show="confirmLockOpen"
        title="Lock brought forward?"
        message="This marks go-live. Staff will no longer see or edit brought-forward balances. Super Admin can unlock later with the access code."
        confirm-label="Lock cutover"
        variant="gold"
        :processing="locking"
        @close="confirmLockOpen = false"
        @confirm="confirmLock"
    />

    <Modal :show="unlockOpen" title="Unlock brought forward" subtitle="Super Admin only. Access code required." @close="unlockOpen = false">
        <form class="space-y-4" @submit.prevent="submitUnlock">
            <p class="text-sm text-slate-600">
                Unlock only for cutover corrections. Lock again when finished.
            </p>
            <FormField label="Access code" :error="unlockForm.errors.access_code">
                <input
                    v-model="unlockForm.access_code"
                    type="password"
                    autocomplete="off"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm"
                    required
                >
            </FormField>
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" :disabled="unlockForm.processing" @click="unlockOpen = false">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="unlockForm.processing">Unlock</AppButton>
            </div>
        </form>
    </Modal>
</template>
