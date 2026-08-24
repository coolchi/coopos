<script setup>
import PageHeader from '../../Components/PageHeader.vue';
import StatCard from '../../Components/StatCard.vue';
import AppCard from '../../Components/AppCard.vue';
import AppButton from '../../Components/AppButton.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import AddMemberModal from '../../Components/AddMemberModal.vue';
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';

const props = defineProps({
    kpis: Object,
    pending_approvals: Number,
    pending_rows: { type: Array, default: () => [] },
    recent_financing: { type: Array, default: () => [] },
    recent_loans: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    form_branches: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    introducers: { type: Array, default: () => [] },
    locked_branch: { type: Boolean, default: false },
});

const page = usePage();
const { can } = useCan();
const tenant = computed(() => page.props.tenant);
const base = computed(() => page.props.tenantBase ?? `/${tenant.value?.slug || ''}`);
const recent = computed(() => props.recent_financing?.length ? props.recent_financing : props.recent_loans);
const addOpen = ref(false);
</script>

<template>
    <Head title="Dashboard" />
        <PageHeader
            eyebrow="Overview"
            title="Dashboard"
        >
            <template #actions>
                <AppButton v-if="can('members.create')" type="button" variant="gold" @click="addOpen = true">Add member</AppButton>
                <AppButton v-if="can('approvals.act')" :href="`${base}/approvals`" variant="secondary">Review approvals</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Active members" :value="kpis.active_members" :hint="`${kpis.new_members || 0} new this month`" />
            <StatCard label="Savings" :value="kpis.savings" />
            <StatCard v-if="tenant?.offers_conventional" label="Loans outstanding" :value="kpis.loans_outstanding" />
            <StatCard v-else-if="tenant?.offers_islamic" label="Loans outstanding" :value="kpis.islamic_outstanding" />
            <StatCard label="Pending approvals" :value="pending_approvals" hint="Needs an officer decision" />
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <AppCard title="Recent loans" :padded="false">
                <EmptyState v-if="!recent.length" icon="loans" title="No loans yet" body="Applications will appear here once members request funds." />
                <table v-else class="w-full text-sm">
                    <tbody>
                        <tr v-for="row in recent" :key="row.uuid + row.kind" class="border-t border-slate-100 first:border-0">
                            <td class="px-5 py-4">
                                <Link :href="row.href || `${base}/loan`" class="font-medium hover:text-teal-800">{{ row.member }}</Link>
                                <div class="text-xs capitalize text-slate-500">{{ (row.kind || 'loan').replace('_', ' ') }}</div>
                            </td>
                            <td class="px-5 py-4 tabular-nums">{{ row.amount }}</td>
                            <td class="px-5 py-4 text-right"><StatusBadge :value="row.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>

            <AppCard title="Waiting on you" :padded="false">
                <EmptyState v-if="!pending_rows.length" icon="approvals" title="All clear" body="No pending approval steps. Enjoy the quiet." />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="row in pending_rows" :key="row.uuid" class="flex items-center justify-between px-5 py-4 text-sm">
                        <div>
                            <div class="font-medium">{{ row.workflow }}</div>
                            <div class="text-xs text-slate-500">{{ row.type }} · step {{ row.step }}</div>
                        </div>
                        <Link :href="`${base}/approvals`" class="text-teal-800">Review</Link>
                    </li>
                </ul>
            </AppCard>
        </div>

        <AppCard v-if="branches?.length" class="mt-6" title="Branches" :padded="false">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Branch</th>
                        <th class="px-5 py-3">Admin</th>
                        <th class="px-5 py-3">Staff</th>
                        <th class="px-5 py-3">Members</th>
                        <th class="px-5 py-3">Payments</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in branches" :key="b.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <Link :href="`${base}/branches/${b.uuid}`" class="font-medium text-teal-800">{{ b.name }}</Link>
                            <div class="text-xs text-slate-500">{{ b.code }}</div>
                        </td>
                        <td class="px-5 py-4">{{ b.admin || 'Unassigned' }}</td>
                        <td class="px-5 py-4">{{ b.staff_count ?? 0 }}</td>
                        <td class="px-5 py-4">{{ b.members_count }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ b.payments_total }}</td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <AddMemberModal
            :show="addOpen"
            :branches="form_branches"
            :states="states"
            :introducers="introducers"
            :locked_branch="locked_branch"
            @close="addOpen = false"
        />
</template>
