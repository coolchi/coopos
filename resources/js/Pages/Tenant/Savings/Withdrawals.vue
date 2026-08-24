<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    withdrawals: Object,
    members: Array,
    products: Array,
    can_manage_withdrawals: { type: Boolean, default: false },
    month: { type: String, default: '' },
    months: { type: Array, default: () => [] },
});
const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const open = ref(false);
const editing = ref(null);
const deleting = ref(null);
const monthValue = ref(props.month || '');
const NEW_BANK = '__new__';
const form = useForm({
    member_uuid: '',
    product_uuid: '',
    amount: '',
    reason: '',
    bank_account_uuid: '',
    bank_name: '',
    account_number: '',
    account_name: '',
});
const editForm = useForm({
    access_code: '',
    member_uuid: '',
    product_uuid: '',
    amount: '',
    reason: '',
    bank_account_uuid: '',
    bank_name: '',
    account_number: '',
    account_name: '',
});
const deleteForm = useForm({ access_code: '' });
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

watch(() => props.month, (value) => {
    monthValue.value = value || '';
});

const monthOptions = computed(() => props.months.length
    ? props.months
    : [{ value: '', label: 'All months' }]);

const onMonthChange = (value) => {
    monthValue.value = value || '';
    router.get(`${base}/withdrawals`, {
        month: monthValue.value || undefined,
    }, { preserveState: false, preserveScroll: true });
};

const memberFor = (uuid) => (props.members || []).find((m) => m.uuid === uuid);

const banksFor = (uuid) => memberFor(uuid)?.bank_accounts || [];

const bankOptionsFor = (uuid) => {
    const banks = banksFor(uuid);
    const options = banks.map((a) => ({ uuid: a.uuid, name: a.label }));
    options.push({
        uuid: NEW_BANK,
        name: banks.length ? 'Different bank account' : 'Enter bank account',
    });

    return options;
};

const defaultBankUuid = (uuid) => {
    const banks = banksFor(uuid);
    const primary = banks.find((a) => a.is_primary) || banks[0];

    return primary?.uuid || NEW_BANK;
};

const showNewBank = (uuid, selected) => Boolean(uuid) && (! banksFor(uuid).length || selected === NEW_BANK);

watch(() => form.member_uuid, (uuid) => {
    form.bank_account_uuid = uuid ? defaultBankUuid(uuid) : '';
    form.bank_name = '';
    form.account_number = '';
    form.account_name = '';
});

watch(() => editForm.member_uuid, (uuid) => {
    if (! editing.value) {
        return;
    }
    if (uuid === editing.value.member_uuid && editing.value.bank_account_uuid) {
        editForm.bank_account_uuid = editing.value.bank_account_uuid;

        return;
    }
    editForm.bank_account_uuid = uuid ? defaultBankUuid(uuid) : NEW_BANK;
    editForm.bank_name = '';
    editForm.account_number = '';
    editForm.account_name = '';
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    open.value = true;
};

const submit = () => form.post(`${base}/withdrawals`, {
    onSuccess: () => {
        open.value = false;
        form.reset();
    },
});

const openEdit = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.access_code = '';
    editForm.member_uuid = row.member_uuid;
    editForm.product_uuid = row.product_uuid;
    editForm.amount = row.amount_naira;
    editForm.reason = row.reason || '';
    editForm.bank_account_uuid = row.bank_account_uuid || defaultBankUuid(row.member_uuid);
    editForm.bank_name = '';
    editForm.account_number = '';
    editForm.account_name = '';
};

const closeEdit = () => {
    editing.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (! editing.value) return;
    editForm.put(`${base}/withdrawals/${editing.value.uuid}`, {
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
    deleteForm.delete(`${base}/withdrawals/${deleting.value.uuid}`, {
        preserveScroll: true,
        onSuccess: () => closeDelete(),
    });
};
</script>

<template>
    <Head title="Withdrawals" />
        <PageHeader eyebrow="Savings" title="Withdrawals">
            <template #actions>
                <div class="w-full sm:w-52">
                    <AppSelect
                        :model-value="monthValue"
                        :options="monthOptions"
                        size="sm"
                        @update:model-value="onMonthChange"
                    />
                </div>
                <AppButton v-if="can('withdrawals.approve')" type="button" variant="gold" @click="openModal">Request withdrawal</AppButton>
            </template>
        </PageHeader>

        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!withdrawals.data.length" icon="withdrawals" title="No withdrawal requests" body="When a member asks to take savings out, the request waits here until an officer approves it." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Member</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Sent to</th>
                        <th class="hidden px-5 py-3 sm:table-cell">Date</th>
                        <th class="px-5 py-3">Status</th>
                        <th v-if="can_manage_withdrawals" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="w in withdrawals.data" :key="w.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">{{ w.member }}</td>
                        <td class="px-5 py-4">{{ w.product }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ w.amount }}</td>
                        <td class="px-5 py-4">
                            <div v-if="w.sent_to" class="text-slate-800">{{ w.sent_to }}</div>
                            <div v-if="w.sent_to_account" class="text-xs tabular-nums text-slate-500">{{ w.sent_to_account }}</div>
                            <span v-if="!w.sent_to" class="text-slate-400">—</span>
                        </td>
                        <td class="hidden px-5 py-4 whitespace-nowrap text-slate-500 sm:table-cell">{{ w.requested_on }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="String(w.status)" /></td>
                        <td v-if="can_manage_withdrawals" class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <AppButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs" @click="openEdit(w)">Edit</AppButton>
                                <AppButton type="button" variant="danger" class="!px-3 !py-1.5 text-xs" @click="openDelete(w)">Delete</AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer>
                <Pagination :links="withdrawals.links" :from="withdrawals.from" :to="withdrawals.to" :total="withdrawals.total" />
            </template>
        </AppCard>

        <Modal :show="open" title="Request withdrawal" wide @close="open = false">
            <form class="space-y-5" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Member" :error="form.errors.member_uuid">
                            <AppSelect v-model="form.member_uuid" placeholder="Select member" :options="members" option-value="uuid" required variant="outline" />
                        </FormField>
                        <FormField label="Product" :error="form.errors.product_uuid" hint="Only Special Target Scheme (STS) supports withdrawals.">
                            <AppSelect v-model="form.product_uuid" placeholder="Select STS product" :options="products" option-value="uuid" option-label="name" required variant="outline" />
                        </FormField>
                        <FormField label="Amount (₦)" :error="form.errors.amount">
                            <AmountInput v-model="form.amount" :class="input" required />
                        </FormField>
                        <FormField label="Reason" :error="form.errors.reason">
                            <input v-model="form.reason" :class="input">
                        </FormField>
                        <FormField
                            class="sm:col-span-2"
                            label="Sent to"
                            :error="form.errors.bank_account_uuid || form.errors.bank_name || form.errors.account_number"
                            hint="The member bank this payout will be transferred to."
                        >
                            <AppSelect
                                v-model="form.bank_account_uuid"
                                placeholder="Select bank"
                                :options="bankOptionsFor(form.member_uuid)"
                                option-value="uuid"
                                option-label="name"
                                :disabled="!form.member_uuid"
                                required
                                variant="outline"
                            />
                        </FormField>
                        <template v-if="showNewBank(form.member_uuid, form.bank_account_uuid)">
                            <FormField label="Bank" :error="form.errors.bank_name">
                                <input v-model="form.bank_name" :class="input" placeholder="e.g. GTBank" required>
                            </FormField>
                            <FormField label="Account number" :error="form.errors.account_number">
                                <input v-model="form.account_number" :class="input" inputmode="numeric" required>
                            </FormField>
                            <FormField label="Account name" class="sm:col-span-2" :error="form.errors.account_name">
                                <input v-model="form.account_name" :class="input" placeholder="As it appears on the account">
                            </FormField>
                        </template>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="open = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing">Submit request</AppButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!editing" title="Edit withdrawal" subtitle="Super Admin only. Access code required." @close="closeEdit">
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Member" :error="editForm.errors.member_uuid">
                        <AppSelect v-model="editForm.member_uuid" placeholder="Select member" :options="members" option-value="uuid" required variant="outline" />
                    </FormField>
                    <FormField label="Product" :error="editForm.errors.product_uuid">
                        <AppSelect v-model="editForm.product_uuid" placeholder="Select STS product" :options="products" option-value="uuid" option-label="name" required variant="outline" />
                    </FormField>
                    <FormField label="Amount (₦)" :error="editForm.errors.amount">
                        <AmountInput v-model="editForm.amount" :class="input" required />
                    </FormField>
                    <FormField label="Reason" :error="editForm.errors.reason">
                        <input v-model="editForm.reason" :class="input">
                    </FormField>
                    <FormField
                        class="sm:col-span-2"
                        label="Sent to"
                        :error="editForm.errors.bank_account_uuid || editForm.errors.bank_name || editForm.errors.account_number"
                    >
                        <AppSelect
                            v-model="editForm.bank_account_uuid"
                            placeholder="Select bank"
                            :options="bankOptionsFor(editForm.member_uuid)"
                            option-value="uuid"
                            option-label="name"
                            required
                            variant="outline"
                        />
                    </FormField>
                    <template v-if="showNewBank(editForm.member_uuid, editForm.bank_account_uuid)">
                        <FormField label="Bank" :error="editForm.errors.bank_name">
                            <input v-model="editForm.bank_name" :class="input" placeholder="e.g. GTBank" required>
                        </FormField>
                        <FormField label="Account number" :error="editForm.errors.account_number">
                            <input v-model="editForm.account_number" :class="input" inputmode="numeric" required>
                        </FormField>
                        <FormField label="Account name" class="sm:col-span-2" :error="editForm.errors.account_name">
                            <input v-model="editForm.account_name" :class="input">
                        </FormField>
                    </template>
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

        <Modal :show="!!deleting" title="Delete withdrawal" subtitle="This removes the request and reverses any ledger posting. Access code required." @close="closeDelete">
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
