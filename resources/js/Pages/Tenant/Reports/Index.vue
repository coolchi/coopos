<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import StatCard from '../../../Components/StatCard.vue';
import AppCard from '../../../Components/AppCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    generated_at: String,
    membership: Object,
    savings: Object,
    financing: Object,
    withdrawals: Object,
    monthly: Array,
    top_savers: Array,
    qard_by_status: Array,
    murabaha_by_status: Array,
    loans_by_status: Array,
    recent_contributions: Array,
});

const page = usePage();
const tenant = computed(() => page.props.tenant);
const base = computed(() => page.props.tenantBase ?? `/${tenant.value?.slug}`);
const accountingReports = computed(() => [
    { href: `${base.value}/reports/profit-loss`, eyebrow: 'Income & expenditure', title: 'Profit & loss' },
    { href: `${base.value}/reports/trial-balance`, eyebrow: 'Ledger check', title: 'Trial balance' },
    { href: `${base.value}/reports/accounts`, eyebrow: 'Position', title: 'Balance sheet' },
    { href: `${base.value}/reports/ledger`, eyebrow: 'Account activity', title: 'General ledger' },
    { href: `${base.value}/reports/statement`, eyebrow: 'Members', title: 'Member statement' },
    { href: `${base.value}/accounts`, eyebrow: 'Books', title: 'Chart of accounts' },
]);
</script>

<template>
    <Head title="Reports" />
        <PageHeader eyebrow="Office" title="Reports" :subtitle="generated_at" />

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="report in accountingReports"
                :key="report.href"
                :href="report.href"
                class="group flex items-center justify-between gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-black/5 transition hover:bg-slate-50 hover:ring-teal-800/20"
            >
                <span class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-teal-800">{{ report.eyebrow }}</span>
                    <span class="mt-1 block truncate text-base font-semibold text-slate-900">{{ report.title }}</span>
                </span>
                <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-teal-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </Link>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Active members" :value="membership.active" :hint="`${membership.new_this_month} joined this month · ${membership.pending} pending`" />
            <StatCard label="Lifetime savings" :value="savings.lifetime" :hint="`${savings.lifetime_payments} payments recorded`" />
            <StatCard label="This month" :value="savings.this_month" :hint="`${savings.payments_this_month} payments · last month ${savings.last_month}`" />
            <StatCard v-if="tenant?.offers_islamic" label="Loans outstanding" :value="financing.qard_portfolio" :hint="`Murabaha ${financing.murabaha_portfolio}`" />
            <StatCard v-if="tenant?.offers_conventional" label="Loans outstanding" :value="financing.loan_portfolio" :hint="`${financing.delinquent_loans} delinquent`" />
            <StatCard label="Pending members" :value="membership.pending" />
            <StatCard label="Withdrawal requests" :value="withdrawals.pending" :hint="`${withdrawals.approved} already approved`" />
            <StatCard label="Withdrawal volume" :value="withdrawals.total" />
        </div>

        <AppCard class="mt-8" title="Savings over 6 months">
            <div class="flex h-40 items-end gap-2 sm:gap-3">
                <div v-for="row in monthly" :key="row.label + row.year" class="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div class="flex h-28 w-full items-end justify-center rounded-xl bg-slate-50 px-1">
                        <div class="w-full max-w-10 rounded-t-lg bg-teal-700" :style="{ height: `${Math.max(row.pct, 4)}%` }"></div>
                    </div>
                    <div class="text-center text-[11px] text-slate-500">
                        <div>{{ row.label }}</div>
                        <div class="hidden tabular-nums sm:block">{{ row.amount }}</div>
                    </div>
                </div>
            </div>
        </AppCard>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <AppCard title="Top savers" :padded="false">
                <EmptyState v-if="!top_savers.length" icon="savings" title="No savings yet" body="Top savers will rank here after contributions are posted." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Member</th>
                            <th class="px-5 py-3 text-right">Saved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in top_savers" :key="row.membership_number" class="border-t border-slate-100">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ row.member }}</div>
                                <div class="text-xs text-slate-500">{{ row.membership_number }}</div>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ row.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>

            <AppCard title="Recent contributions" :padded="false">
                <EmptyState v-if="!recent_contributions.length" icon="savings" title="No contributions yet" body="Recent payments will list here as members save." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Member</th>
                            <th class="px-5 py-3">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in recent_contributions" :key="i" class="border-t border-slate-100">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ row.member }}</div>
                                <div class="text-xs text-slate-500">{{ row.product }} · {{ row.paid_on }}</div>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ row.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <AppCard v-if="tenant?.offers_islamic" title="Qard Hasan by status" :padded="false">
                <EmptyState v-if="!qard_by_status.length" icon="islamic" title="No Qard Hasan facilities" body="Interest-free facilities will summarise here once they are issued." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Facilities</th>
                            <th class="px-5 py-3">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in qard_by_status" :key="row.status" class="border-t border-slate-100">
                            <td class="px-5 py-3"><StatusBadge :value="row.status" /></td>
                            <td class="px-5 py-3">{{ row.facilities }}</td>
                            <td class="px-5 py-3 tabular-nums">{{ row.outstanding }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
            <AppCard v-if="tenant?.offers_islamic" title="Murabaha by status" :padded="false">
                <EmptyState v-if="!murabaha_by_status.length" icon="islamic" title="No Murabaha facilities" body="Cost-plus sales will summarise here once they are booked." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Facilities</th>
                            <th class="px-5 py-3">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in murabaha_by_status" :key="row.status" class="border-t border-slate-100">
                            <td class="px-5 py-3"><StatusBadge :value="row.status" /></td>
                            <td class="px-5 py-3">{{ row.facilities }}</td>
                            <td class="px-5 py-3 tabular-nums">{{ row.outstanding }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
            <AppCard v-if="tenant?.offers_conventional" title="Loans by status" :padded="false">
                <EmptyState v-if="!loans_by_status.length" icon="loans" title="No loans yet" body="Conventional loan balances will appear here after disbursement." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Facilities</th>
                            <th class="px-5 py-3">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in loans_by_status" :key="row.status" class="border-t border-slate-100">
                            <td class="px-5 py-3"><StatusBadge :value="row.status" /></td>
                            <td class="px-5 py-3">{{ row.facilities }}</td>
                            <td class="px-5 py-3 tabular-nums">{{ row.outstanding }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
        </div>
</template>
