<script setup>
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kpis: Object,
    chart: Array,
    attention: Array,
    recent: Array,
    tickets: Array,
});

const peak = computed(() => {
    const max = Math.max(0, ...(props.chart || []).map((row) => row.revenue_kobo));
    const top = (props.chart || []).find((row) => row.revenue_kobo === max);

    return {
        compact: top?.compact || '—',
        mid: max ? compactHalf(max) : '—',
    };
});

const compactHalf = (kobo) => {
    const naira = kobo / 200;
    if (naira >= 1000) {
        return `₦${Math.round(naira / 1000)}k`;
    }

    return `₦${Math.round(naira).toLocaleString()}`;
};

const barHeight = (row) => {
    if (! row.revenue_kobo) {
        return '3px';
    }

    return `${Math.max(8, row.pct)}%`;
};

const accent = {
    amber: 'bg-amber-400',
    orange: 'bg-orange-400',
    rose: 'bg-rose-400',
    slate: 'bg-teal-500',
};
</script>

<template>
            <Head title="Dashboard" />

        <PageHeader
            title="Dashboard"
            :subtitle="`${kpis.cooperatives} cooperatives · ${kpis.mrr} MRR${kpis.pending ? ` · ${kpis.pending} waiting to go live` : ''}`"
        >
            <template #actions>
                <Link
                    v-if="kpis.pending"
                    href="/platform/cooperatives?status=pending"
                    class="rounded-full bg-amber-50 px-5 py-2.5 text-sm font-medium text-amber-900"
                >Review {{ kpis.pending }} pending</Link>
                <Link href="/platform/cooperatives" class="rounded-full bg-[#071c1b] px-5 py-2.5 text-sm font-medium text-white">All cooperatives</Link>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Cooperatives</div>
                <div class="mt-2 text-3xl font-semibold tabular-nums">{{ kpis.cooperatives }}</div>
                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                    <span class="text-teal-800">{{ kpis.active }} active</span>
                    <span class="text-amber-800">{{ kpis.pending }} pending</span>
                    <span class="text-rose-800">{{ kpis.suspended }} suspended</span>
                </div>
                <div class="mt-3 text-xs text-slate-500">{{ kpis.members }} members across tenants</div>
            </div>
            <div class="rounded-3xl bg-teal-50 p-6 shadow-sm ring-1 ring-teal-100">
                <div class="text-xs font-medium uppercase tracking-wide text-teal-800">Monthly recurring</div>
                <div class="mt-2 text-3xl font-semibold tabular-nums text-teal-950">{{ kpis.mrr }}</div>
                <div class="mt-3 text-xs text-teal-800/80">{{ kpis.trials }} trials · {{ kpis.past_due }} past due</div>
                <Link href="/platform/subscriptions" class="mt-3 inline-block text-xs text-teal-900">Subscriptions →</Link>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ kpis.collected_label }}</div>
                <div class="mt-2 text-3xl font-semibold tabular-nums">{{ kpis.collected_this_month }}</div>
                <div class="mt-3 text-xs text-slate-500">Paid invoices this calendar month</div>
                <Link href="/platform/invoices" class="mt-3 inline-block text-xs text-teal-800">Invoices →</Link>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Support queue</div>
                <div class="mt-2 text-3xl font-semibold tabular-nums">{{ kpis.open_tickets }}</div>
                <div class="mt-3 text-xs text-slate-500">
                    <span v-if="kpis.high_tickets">{{ kpis.high_tickets }} high priority · </span>
                    Open and pending tickets
                </div>
                <Link href="/platform/tickets" class="mt-3 inline-block text-xs text-teal-800">Triage →</Link>
            </div>
        </div>

        <div class="mt-6 grid items-stretch gap-6 xl:grid-cols-5">
            <section class="flex min-h-[280px] flex-col rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 xl:col-span-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Collections</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Paid invoice totals, last 6 months</p>
                    </div>
                    <Link href="/platform/invoices?status=paid" class="text-sm text-teal-800">All paid</Link>
                </div>

                <div class="mt-5 flex min-h-0 flex-1 gap-3">
                    <div class="flex w-12 shrink-0 flex-col justify-between pb-[52px] text-right text-[10px] tabular-nums text-slate-400">
                        <span>{{ peak.compact }}</span>
                        <span>{{ peak.mid }}</span>
                        <span>₦0</span>
                    </div>
                    <div class="relative min-h-[180px] flex-1">
                        <div class="pointer-events-none absolute inset-x-0 top-0 bottom-[52px]">
                            <div class="absolute inset-x-0 top-0 border-t border-dashed border-slate-200"></div>
                            <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-slate-200"></div>
                            <div class="absolute inset-x-0 bottom-0 border-t border-slate-200"></div>
                        </div>
                        <div class="absolute inset-x-0 top-0 bottom-[52px] flex gap-2 sm:gap-3">
                            <div
                                v-for="row in chart"
                                :key="row.label"
                                class="group relative min-w-0 flex-1 cursor-pointer"
                            >
                                <div class="pointer-events-none absolute left-1/2 top-0 z-10 hidden w-max -translate-x-1/2 group-hover:block group-first:left-0 group-first:translate-x-0 group-last:left-auto group-last:right-0 group-last:translate-x-0">
                                    <div class="rounded-xl bg-[#071c1b] px-3 py-2 text-left text-xs text-white shadow-lg">
                                        <div class="font-semibold">{{ row.month }}</div>
                                        <div class="mt-0.5 tabular-nums">{{ row.revenue }}</div>
                                        <div class="mt-0.5 text-teal-100/80">{{ row.invoices }} paid · {{ row.signups }} new</div>
                                    </div>
                                </div>
                                <div class="absolute inset-x-0 top-6 bottom-0">
                                    <div
                                        class="absolute bottom-0 left-1/2 w-7 -translate-x-1/2 rounded-t-xl transition group-hover:brightness-110 sm:w-9"
                                        :class="row.revenue_kobo ? 'bg-[#0f766e]' : 'bg-slate-200'"
                                        :style="{ height: barHeight(row) }"
                                    >
                                        <div class="absolute inset-x-0 -top-5 text-center text-[11px] font-medium tabular-nums whitespace-nowrap text-slate-500">
                                            {{ row.compact }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="absolute inset-x-0 bottom-0 flex h-[52px] gap-2 sm:gap-3">
                            <div v-for="row in chart" :key="`${row.label}-axis`" class="min-w-0 flex-1 pt-2 text-center">
                                <div class="text-xs font-medium text-slate-600">{{ row.label }}</div>
                                <div class="text-[10px] text-slate-400">{{ row.signups }} new</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Needs attention</h2>
                    <span class="text-xs text-slate-400">{{ attention?.length || 0 }}</span>
                </div>
                <EmptyState
                    v-if="!attention?.length"
                    icon="approvals"
                    title="You're caught up"
                    body="No pending approvals, past-due bills, or high-priority tickets."
                />
                <ul v-else class="mt-4 space-y-2">
                    <li v-for="(item, i) in attention" :key="i">
                        <Link :href="item.href" class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3 text-slate-800 transition hover:bg-slate-100">
                            <span class="mt-1 h-8 w-1 shrink-0 rounded-full" :class="accent[item.tone] || accent.slate"></span>
                            <span class="min-w-0 flex-1">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ item.label }}</span>
                                <span class="mt-0.5 block truncate font-medium">{{ item.title }}</span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ item.meta }}</span>
                            </span>
                            <span class="shrink-0 self-center text-xs font-medium text-teal-800">{{ item.action }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Newest cooperatives</h2>
                    <Link href="/platform/cooperatives" class="text-sm text-teal-800">View all</Link>
                </div>
                <EmptyState v-if="!recent?.length" icon="cooperatives" title="No cooperatives yet" body="New societies appear here as they register on CoopOS." />
                <ul v-else class="mt-2 divide-y divide-slate-100">
                    <li v-for="item in recent" :key="item.uuid" class="flex items-center gap-3 py-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-50 text-sm font-medium text-teal-800">{{ item.initials }}</div>
                        <div class="min-w-0 flex-1">
                            <Link :href="`/platform/cooperatives/${item.slug}`" class="block truncate font-medium hover:text-teal-800">{{ item.name }}</Link>
                            <div class="truncate text-xs text-slate-500">{{ item.host }} · {{ item.plan || 'No plan' }}</div>
                        </div>
                        <StatusBadge :value="item.status" />
                    </li>
                </ul>
            </section>
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Support</h2>
                    <Link href="/platform/tickets" class="text-sm text-teal-800">Inbox</Link>
                </div>
                <EmptyState v-if="!tickets?.length" icon="inbox" title="Inbox is clear" body="No open support tickets. New messages from cooperatives will land here." />
                <ul v-else class="mt-2 divide-y divide-slate-100">
                    <li v-for="ticket in tickets" :key="ticket.uuid" class="flex items-start justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <Link :href="`/platform/tickets/${ticket.uuid}`" class="block truncate font-medium hover:text-teal-800">{{ ticket.subject }}</Link>
                            <div class="mt-0.5 truncate text-xs text-slate-500">{{ ticket.cooperative }} · {{ ticket.created_at }}</div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <StatusBadge :value="ticket.priority" />
                            <StatusBadge :value="ticket.status" />
                        </div>
                    </li>
                </ul>
            </section>
        </div>
</template>
