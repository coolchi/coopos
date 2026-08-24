<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import RecordPaymentModal from '../../../Components/RecordPaymentModal.vue';
import AddMemberModal from '../../../Components/AddMemberModal.vue';
import ImportMembersModal from '../../../Components/ImportMembersModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    members: Object,
    filters: Object,
    counts: Object,
    branches: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    locked_branch: { type: Boolean, default: false },
    member_options: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    required_products: { type: Array, default: () => [] },
    member_plans: { type: Object, default: () => ({}) },
    member_paid: { type: Object, default: () => ({}) },
    member_facilities: { type: Object, default: () => ({}) },
    introducers: { type: Array, default: () => [] },
    monipay_configured: { type: Boolean, default: false },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const q = ref(props.filters?.q || '');
const addOpen = ref(false);
const importOpen = ref(false);
const payOpen = ref(false);
const importErrors = ref([]);

watch(() => page.props.flash?.import_errors, (rows) => {
    if (Array.isArray(rows) && rows.length) {
        importErrors.value = rows;
        importOpen.value = true;
    }
});

const search = (status) => {
    router.get(`${base}/members`, {
        q: q.value || undefined,
        status: status === undefined ? props.filters?.status : status || undefined,
        branch: props.filters?.branch || undefined,
    }, { preserveState: true, replace: true });
};

const filterBranch = (uuid) => {
    router.get(`${base}/members`, {
        q: q.value || undefined,
        status: props.filters?.status || undefined,
        branch: uuid || undefined,
    }, { preserveState: true, replace: true });
};

const openImport = () => {
    importErrors.value = [];
    importOpen.value = true;
};

const branchFilterOptions = computed(() => props.branches || []);
const selectedBranch = computed(() => props.filters?.branch || '');
</script>

<template>
    <Head title="Members" />
        <PageHeader eyebrow="Membership" title="Members">
            <template #actions>
                <AppButton v-if="page.props.allow_manual_payments && can('contributions.record')" type="button" variant="secondary" @click="payOpen = true">Record payment</AppButton>
                <AppButton v-if="can('members.import')" type="button" variant="secondary" @click="openImport">Import</AppButton>
                <AppButton v-if="can('members.create')" type="button" variant="gold" @click="addOpen = true">Add member</AppButton>
            </template>
        </PageHeader>

        <div class="mt-6 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-black/5 sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                        :class="!filters.status ? 'bg-[#071c1b] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="search('')"
                    >All {{ counts?.all ?? 0 }}</button>
                    <button
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                        :class="filters.status === 'active' ? 'bg-teal-800 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="search('active')"
                    >Active {{ counts?.active ?? 0 }}</button>
                    <button
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                        :class="filters.status === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="search('pending')"
                    >Pending {{ counts?.pending ?? 0 }}</button>
                </div>
                <form class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="search(filters.status)">
                    <div v-if="!locked_branch && branches?.length > 1" class="w-full shrink-0 sm:w-52">
                        <AppSelect
                            :model-value="selectedBranch"
                            placeholder="All branches"
                            :options="branchFilterOptions"
                            option-value="uuid"
                            option-label="name"
                            variant="outline"
                            size="sm"
                            @change="filterBranch"
                        />
                    </div>
                    <input
                        v-model="q"
                        type="search"
                        placeholder="Search name, phone, or membership no."
                        class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-800/20"
                    >
                    <AppButton type="submit" class="sm:shrink-0">Search</AppButton>
                </form>
            </div>
        </div>

        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!members.data.length" icon="search" title="No members match" body="Try another status, branch, or search term — or register someone new." />
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Number</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Introducer</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3">Contribution</th>
                            <th class="px-5 py-3">Virtual account</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in members.data" :key="m.uuid" class="border-t border-slate-100">
                            <td class="px-5 py-4">
                                <Link :href="`${base}/members/${m.uuid}`" class="font-medium text-teal-800">{{ m.membership_number }}</Link>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium">{{ m.name }}</div>
                                <div class="text-xs text-slate-500">{{ m.email || '—' }}</div>
                            </td>
                            <td class="px-5 py-4">{{ m.branch || '—' }}</td>
                            <td class="px-5 py-4">{{ m.introducer || '—' }}</td>
                            <td class="px-5 py-4">{{ m.phone || '—' }}</td>
                            <td class="px-5 py-4 tabular-nums">{{ m.contribution || '₦0' }}</td>
                            <td class="px-5 py-4">
                                <div v-if="m.virtual_account?.account_number" class="tabular-nums font-medium">{{ m.virtual_account.account_number }}</div>
                                <div v-if="m.virtual_account?.bank_name" class="text-xs text-slate-500">{{ m.virtual_account.bank_name }}</div>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-5 py-4"><StatusBadge :value="m.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template #footer>
                <Pagination :links="members.links" :from="members.from" :to="members.to" :total="members.total" />
            </template>
        </AppCard>

        <AddMemberModal
            :show="addOpen"
            :branches="branches"
            :states="states"
            :introducers="introducers"
            :locked_branch="locked_branch"
            @close="addOpen = false"
        />

        <ImportMembersModal
            :show="importOpen"
            :errors="importErrors"
            @close="importOpen = false"
        />

        <RecordPaymentModal
            :show="payOpen"
            :members="member_options"
            :products="products"
            :required_products="required_products"
            :member_plans="member_plans"
            :member_paid="member_paid"
            :member_facilities="member_facilities"
            @close="payOpen = false"
        />
</template>
