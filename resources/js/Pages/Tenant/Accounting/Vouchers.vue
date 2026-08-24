<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import Modal from '../../../Components/Modal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    vouchers: { type: Object, default: () => ({ data: [] }) },
    accounts: { type: Array, default: () => [] },
    cash_accounts: { type: Array, default: () => [] },
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    show_branch: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const composeOpen = ref(false);
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';
const isExpense = computed(() => props.kind === 'expense');
const path = computed(() => `${base}/accounts/${isExpense.value ? 'expenses' : 'income'}`);
const partyLabel = computed(() => (isExpense.value ? 'Receiver' : 'Source'));

const filterForm = useForm({
    q: props.filters.q || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
});

const hasFilters = computed(() => Boolean(filterForm.q || filterForm.from || filterForm.to));

const form = useForm({
    account_uuid: '',
    contra_account_uuid: '',
    amount: '',
    occurred_on: new Date().toISOString().slice(0, 10),
    payee: '',
    description: '',
    branch_uuid: props.branch?.uuid || '',
});

const defaultCash = computed(() => props.cash_accounts.find((a) => a.code === '1010')?.uuid || props.cash_accounts[0]?.uuid || '');

const openCreate = () => {
    form.reset();
    form.occurred_on = new Date().toISOString().slice(0, 10);
    form.contra_account_uuid = defaultCash.value;
    form.branch_uuid = props.branch?.uuid || '';
    form.account_uuid = props.accounts[0]?.uuid || '';
    composeOpen.value = true;
};

const reload = () => {
    router.get(path.value, {
        q: filterForm.q || undefined,
        from: filterForm.from || undefined,
        to: filterForm.to || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const openJournal = (row) => {
    if (! row.journal_uuid) {
        return;
    }
    router.visit(`${base}/accounts/journals/${row.journal_uuid}`);
};

const submit = () => form.post(path.value, { onSuccess: () => { composeOpen.value = false; } });
</script>

<template>
    <Head :title="title" />
    <PageHeader eyebrow="Accounts" :title="title">
        <template #actions>
            <AppButton v-if="can('finance.manage')" type="button" variant="gold" @click="openCreate">{{ isExpense ? 'Record expense' : 'Record income' }}</AppButton>
        </template>
    </PageHeader>

    <form class="mt-6 grid gap-3 sm:grid-cols-3" @submit.prevent="reload">
        <FormField :label="isExpense ? 'Search expenses' : 'Search income'">
            <input
                v-model="filterForm.q"
                type="search"
                :class="input"
                :placeholder="`Reference, ${partyLabel.toLowerCase()}, or description`"
                @change="reload"
            >
        </FormField>
        <FormField label="From"><input v-model="filterForm.from" type="date" :class="input" @change="reload"></FormField>
        <FormField label="To"><input v-model="filterForm.to" type="date" :class="input" @change="reload"></FormField>
    </form>

    <AppCard class="mt-6" :padded="false">
        <EmptyState
            v-if="!vouchers.data?.length"
            icon="billing"
            :title="hasFilters
                ? (isExpense ? 'No matching expenses' : 'No matching income')
                : (isExpense ? 'No expenses posted' : 'No income posted')"
            :body="hasFilters
                ? 'Try a different date range or search.'
                : (isExpense ? 'Record rent, salary, and other costs to build the profit and loss.' : 'Record donations, fines, and other receipts here.')"
        />
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Ref</th>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3">{{ partyLabel }}</th>
                        <th class="px-5 py-3">Description</th>
                        <th class="px-5 py-3">{{ isExpense ? 'Paid via' : 'Received into' }}</th>
                        <th v-if="show_branch" class="px-5 py-3">Branch</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in vouchers.data"
                        :key="row.uuid"
                        class="border-t border-slate-100 transition-colors"
                        :class="[
                            index % 2 ? 'bg-slate-50/40' : 'bg-white',
                            row.journal_uuid ? 'cursor-pointer hover:bg-teal-50/40' : '',
                        ]"
                        @click="openJournal(row)"
                    >
                        <td class="whitespace-nowrap px-5 py-3.5 tabular-nums text-slate-500">{{ row.occurred_on }}</td>
                        <td class="px-5 py-3.5 font-medium tabular-nums text-teal-800">{{ row.reference }}</td>
                        <td class="px-5 py-3.5">
                            <div class="font-medium text-slate-900">{{ row.account }}</div>
                            <div class="text-xs tabular-nums text-slate-500">{{ row.account_code }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-slate-800">{{ row.payee || '—' }}</td>
                        <td class="max-w-[16rem] truncate px-5 py-3.5 text-slate-600" :title="row.description">{{ row.description }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ row.contra }}</td>
                        <td v-if="show_branch" class="px-5 py-3.5 text-slate-600">{{ row.branch || '—' }}</td>
                        <td class="px-5 py-3.5 text-right font-medium tabular-nums text-slate-900">{{ row.amount }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <template v-if="vouchers.total" #footer>
            <Pagination :links="vouchers.links" :from="vouchers.from" :to="vouchers.to" :total="vouchers.total" />
        </template>
    </AppCard>

    <Modal :show="composeOpen" :title="isExpense ? 'Record expense' : 'Record income'" @close="composeOpen = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField :label="isExpense ? 'Expense account' : 'Income account'" :error="form.errors.account_uuid">
                <AppSelect v-model="form.account_uuid" :options="accounts" option-value="uuid" required />
            </FormField>
            <FormField :label="isExpense ? 'Paid from' : 'Received into'" :error="form.errors.contra_account_uuid">
                <AppSelect v-model="form.contra_account_uuid" :options="cash_accounts" option-value="uuid" required />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Amount (₦)" :error="form.errors.amount"><AmountInput v-model="form.amount" :class="input" required /></FormField>
                <FormField label="Date" :error="form.errors.occurred_on"><input v-model="form.occurred_on" type="date" :class="input" required></FormField>
            </div>
            <FormField :label="isExpense ? 'Receiver' : 'Received from'"><input v-model="form.payee" :class="input"></FormField>
            <FormField label="Description" :error="form.errors.description"><input v-model="form.description" :class="input" required></FormField>
            <FormField v-if="branches.length && !page.props.branch" label="Branch">
                <AppSelect v-model="form.branch_uuid" :options="[{ uuid: '', name: 'Current / HQ' }, ...branches]" option-value="uuid" option-label="name" />
            </FormField>
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" @click="composeOpen = false">Cancel</AppButton>
                <AppButton type="submit" :disabled="form.processing">Post to ledger</AppButton>
            </div>
        </form>
    </Modal>
</template>
