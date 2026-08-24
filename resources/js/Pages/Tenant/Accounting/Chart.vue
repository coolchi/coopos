<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import Modal from '../../../Components/Modal.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const filter = ref('all');
const query = ref('');
const composeOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const form = useForm({
    code: '',
    name: '',
    type: 'expense',
    description: '',
    sort_order: 500,
});

const editForm = useForm({
    name: '',
    type: 'expense',
    description: '',
    sort_order: 0,
});

const filtered = computed(() => props.accounts.filter((row) => {
    if (filter.value !== 'all' && row.type !== filter.value) {
        return false;
    }
    const q = query.value.trim().toLowerCase();
    if (! q) {
        return true;
    }
    return `${row.code} ${row.name}`.toLowerCase().includes(q);
}));

const tabs = computed(() => [
    { id: 'all', label: 'All', count: props.counts.all },
    { id: 'asset', label: 'Assets', count: props.counts.asset },
    { id: 'liability', label: 'Liabilities', count: props.counts.liability },
    { id: 'equity', label: 'Equity', count: props.counts.equity },
    { id: 'income', label: 'Income', count: props.counts.income },
    { id: 'expense', label: 'Expenses', count: props.counts.expense },
]);

const openCreate = () => {
    form.reset();
    form.type = filter.value !== 'all' ? filter.value : 'expense';
    form.sort_order = 500;
    composeOpen.value = true;
};

const save = () => form.post(`${base}/accounts`, { onSuccess: () => { composeOpen.value = false; form.reset(); } });

const openEdit = (row) => {
    editing.value = row;
    editForm.name = row.name;
    editForm.type = row.type;
    editForm.description = row.description || '';
    editForm.sort_order = row.sort_order;
};

const saveEdit = () => editForm.post(`${base}/accounts/${editing.value.uuid}`, {
    onSuccess: () => { editing.value = null; },
});

const toggling = ref(null);
const toggleForm = useForm({});

const toggleTitle = computed(() => (toggling.value?.is_active ? 'Deactivate account' : 'Activate account'));
const toggleMessage = computed(() => {
    if (! toggling.value) {
        return '';
    }

    const name = `${toggling.value.code} ${toggling.value.name}`;

    return toggling.value.is_active
        ? `Deactivate ${name}? It will not be available for new income or expense posts until you activate it again.`
        : `Activate ${name}? It will be available for posting again.`;
});
const toggleConfirmLabel = computed(() => (toggling.value?.is_active ? 'Deactivate' : 'Activate'));
const toggleVariant = computed(() => (toggling.value?.is_active ? 'secondary' : 'gold'));

const confirmToggle = () => {
    if (! toggling.value) {
        return;
    }
    toggleForm.post(`${base}/accounts/${toggling.value.uuid}/toggle`, {
        onSuccess: () => { toggling.value = null; },
    });
};

const destroy = () => useForm({}).delete(`${base}/accounts/${deleting.value.uuid}`, {
    onSuccess: () => { deleting.value = null; },
});
</script>

<template>
    <Head title="Chart of accounts" />
    <PageHeader eyebrow="Accounts" title="Chart of accounts" subtitle="Standard heads for income, expenditure, assets, and equity.">
        <template #actions>
            <AppButton v-if="can('finance.manage')" type="button" variant="gold" @click="openCreate">Add account</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-1 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="shrink-0 rounded-full px-3 py-1.5 text-sm"
                :class="filter === tab.id ? 'bg-[#071c1b] text-white' : 'bg-white text-slate-600 ring-1 ring-black/5'"
                @click="filter = tab.id"
            >
                {{ tab.label }} <span class="text-xs opacity-70">{{ tab.count }}</span>
            </button>
        </div>
        <input v-model="query" type="search" placeholder="Search code or name" class="w-full rounded-full border-0 bg-white px-4 py-2 text-sm shadow-sm ring-1 ring-black/5 sm:max-w-xs">
    </div>

    <AppCard class="mt-6" :padded="false">
        <EmptyState v-if="!filtered.length" icon="billing" title="No accounts in this view" body="Add a custom head or switch category." />
        <table v-else class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Account</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3 text-right">Balance</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in filtered" :key="row.uuid" class="border-t border-slate-100">
                    <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ row.code }}</td>
                    <td class="px-5 py-3">
                        <div class="font-medium">{{ row.name }}</div>
                        <div v-if="row.description" class="text-xs text-slate-500">{{ row.description }}</div>
                    </td>
                    <td class="px-5 py-3 capitalize text-slate-600">{{ row.type_label }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">
                        <Link :href="`${base}/reports/ledger?account=${row.uuid}`" class="hover:text-teal-800">{{ row.balance }}</Link>
                    </td>
                    <td class="px-5 py-3"><StatusBadge :value="row.is_active ? 'active' : 'inactive'" /></td>
                    <td class="px-5 py-3 text-right">
                        <div v-if="can('finance.manage')" class="flex justify-end gap-2">
                            <button type="button" class="text-sm font-medium text-teal-800" @click="openEdit(row)">Edit</button>
                            <button type="button" class="text-sm text-slate-500" @click="toggling = row">{{ row.is_active ? 'Deactivate' : 'Activate' }}</button>
                            <button v-if="!row.is_system && !row.has_activity" type="button" class="text-sm text-rose-700" @click="deleting = row">Remove</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </AppCard>

    <Modal :show="composeOpen" title="Add account" subtitle="Custom heads post like the standard chart." @close="composeOpen = false">
        <form class="space-y-4" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Code" :error="form.errors.code"><input v-model="form.code" :class="input" required placeholder="5190"></FormField>
                <FormField label="Type">
                    <AppSelect v-model="form.type" :options="types" required />
                </FormField>
            </div>
            <FormField label="Name" :error="form.errors.name"><input v-model="form.name" :class="input" required></FormField>
            <FormField label="Description"><input v-model="form.description" :class="input"></FormField>
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" @click="composeOpen = false">Cancel</AppButton>
                <AppButton type="submit" :disabled="form.processing">Save account</AppButton>
            </div>
        </form>
    </Modal>

    <Modal :show="Boolean(editing)" title="Edit account" @close="editing = null">
        <form v-if="editing" class="space-y-4" @submit.prevent="saveEdit">
            <p class="text-sm text-slate-500">{{ editing.code }} · {{ editing.is_system ? 'System account' : 'Custom account' }}</p>
            <FormField label="Name" :error="editForm.errors.name"><input v-model="editForm.name" :class="input" required></FormField>
            <FormField v-if="!editing.is_system" label="Type">
                <AppSelect v-model="editForm.type" :options="types" required />
            </FormField>
            <FormField label="Description"><input v-model="editForm.description" :class="input"></FormField>
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" @click="editing = null">Cancel</AppButton>
                <AppButton type="submit" :disabled="editForm.processing">Save changes</AppButton>
            </div>
        </form>
    </Modal>

    <ConfirmModal
        :show="Boolean(toggling)"
        :title="toggleTitle"
        :message="toggleMessage"
        :confirm-label="toggleConfirmLabel"
        :variant="toggleVariant"
        :processing="toggleForm.processing"
        @close="toggling = null"
        @confirm="confirmToggle"
    />

    <ConfirmModal
        :show="Boolean(deleting)"
        title="Remove account"
        :message="deleting ? `Remove ${deleting.code} ${deleting.name}?` : ''"
        confirm-label="Remove"
        variant="danger"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
