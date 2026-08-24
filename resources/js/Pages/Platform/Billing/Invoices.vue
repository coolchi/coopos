<script setup>
import Pagination from '../../../Components/Pagination.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    invoices: Object,
    filters: Object,
    summary: Object,
    counts: { type: Object, default: () => ({}) },
});
const status = ref(props.filters?.status || '');
const q = ref(props.filters?.q || '');
const pending = ref(null);
const processing = ref(false);
const confirmTitle = computed(() => pending.value?.action === 'void' ? 'Void invoice' : 'Mark invoice paid');
const confirmLabel = computed(() => pending.value?.action === 'void' ? 'Void' : 'Mark paid');
const confirmVariant = computed(() => pending.value?.action === 'void' ? 'danger' : 'primary');
const confirmMessage = computed(() => {
    const invoice = pending.value?.invoice;
    if (! invoice) {
        return '';
    }
    return pending.value.action === 'void'
        ? `Void ${invoice.number}? This cannot be collected later.`
        : `Mark ${invoice.number} as paid?`;
});

const filter = (nextStatus) => {
    if (nextStatus !== undefined) {
        status.value = nextStatus || '';
    }
    router.get('/platform/invoices', {
        status: status.value || undefined,
        q: q.value || undefined,
    }, { preserveState: true, replace: true });
};

const chipClass = (value) => value === (status.value || '')
    ? 'bg-[#071c1b] text-white'
    : 'bg-white text-slate-700 ring-1 ring-black/5 hover:bg-slate-50';

const requestAction = (invoice, action) => {
    pending.value = { invoice, action };
};
const confirmAction = () => {
    if (! pending.value) {
        return;
    }
    const { invoice, action } = pending.value;
    processing.value = true;
    const path = action === 'void'
        ? `/platform/invoices/${invoice.uuid}/void`
        : `/platform/invoices/${invoice.uuid}/paid`;
    router.post(path, {}, {
        onFinish: () => {
            processing.value = false;
            pending.value = null;
        },
    });
};
</script>

<template>
            <Head title="Invoices" />
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">Revenue</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Invoices</h1>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" class="rounded-full px-3 py-1" :class="chipClass('')" @click="filter('')">All {{ counts.all ?? summary.count }}</button>
                <button type="button" class="rounded-full px-3 py-1" :class="status === 'paid' ? 'bg-teal-700 text-white' : 'bg-white text-slate-700 ring-1 ring-black/5'" @click="filter('paid')">Paid {{ counts.paid }}</button>
                <button type="button" class="rounded-full px-3 py-1" :class="status === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-slate-700 ring-1 ring-black/5'" @click="filter('pending')">Pending {{ counts.pending }}</button>
                <button type="button" class="rounded-full px-3 py-1" :class="status === 'past_due' ? 'bg-rose-700 text-white' : 'bg-white text-slate-700 ring-1 ring-black/5'" @click="filter('past_due')">Past due {{ counts.past_due }}</button>
                <button type="button" class="rounded-full px-3 py-1" :class="status === 'void' ? 'bg-slate-700 text-white' : 'bg-white text-slate-700 ring-1 ring-black/5'" @click="filter('void')">Void {{ counts.void }}</button>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <button type="button" class="rounded-3xl bg-white p-5 text-left shadow-sm ring-1 ring-black/5 transition hover:ring-teal-800/20" @click="filter('paid')">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Collected</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-teal-900">{{ summary.paid }}</div>
                <div class="mt-2 text-xs text-slate-500">{{ counts.paid }} paid invoices</div>
            </button>
            <button type="button" class="rounded-3xl bg-white p-5 text-left shadow-sm ring-1 ring-black/5 transition hover:ring-amber-600/20" @click="filter('pending')">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ summary.open }}</div>
                <div class="mt-2 text-xs text-slate-500">Pending and past due</div>
            </button>
            <button type="button" class="rounded-3xl bg-white p-5 text-left shadow-sm ring-1 ring-black/5 transition hover:ring-rose-700/20" @click="filter('past_due')">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Past due</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-rose-800">{{ summary.past_due }}</div>
                <div class="mt-2 text-xs text-rose-800/80">{{ counts.past_due }} invoices need collection</div>
            </button>
        </div>

        <form class="mt-6 grid gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-black/5 md:grid-cols-[1fr_14rem_auto]" @submit.prevent="filter()">
            <input v-model="q" type="search" placeholder="Search number or cooperative" class="rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm">
            <AppSelect
                v-model="status"
                variant="white"
                placeholder="All statuses"
                :options="[{ value: 'paid', label: 'Paid' }, { value: 'pending', label: 'Pending' }, { value: 'past_due', label: 'Past due' }, { value: 'void', label: 'Void' }]"
                @change="filter()"
            />
            <button class="rounded-2xl bg-[#071c1b] px-5 py-3 text-sm font-medium text-white">Search</button>
        </form>

        <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState v-if="!invoices.data.length" icon="search" title="No invoices found" body="Try another status or search term." />
            <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Number</th>
                        <th class="px-5 py-3">Cooperative</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Due</th>
                        <th class="px-5 py-3">Paid</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="invoice in invoices.data" :key="invoice.uuid" class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-5 py-4 font-medium tabular-nums">{{ invoice.number }}</td>
                        <td class="px-5 py-4">
                            <Link v-if="invoice.slug" :href="`/platform/cooperatives/${invoice.slug}`" class="font-medium hover:text-teal-800">{{ invoice.cooperative }}</Link>
                            <span v-else>{{ invoice.cooperative }}</span>
                        </td>
                        <td class="px-5 py-4 font-medium tabular-nums">{{ invoice.amount }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="invoice.status" /></td>
                        <td class="px-5 py-4 whitespace-nowrap" :class="invoice.is_overdue ? 'font-medium text-rose-700' : 'text-slate-600'">
                            {{ invoice.due_at || '—' }}
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap text-slate-600">{{ invoice.paid_at || '—' }}</td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <template v-if="invoice.can_collect">
                                <button type="button" class="rounded-full bg-teal-50 px-3 py-1 text-xs font-medium text-teal-900 hover:bg-teal-100" @click="requestAction(invoice, 'paid')">Mark paid</button>
                                <button type="button" class="ml-2 rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-rose-800 hover:bg-rose-100" @click="requestAction(invoice, 'void')">Void</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <Pagination :links="invoices.links" :from="invoices.from" :to="invoices.to" :total="invoices.total" />
        </div>

        <ConfirmModal
            :show="Boolean(pending)"
            :title="confirmTitle"
            :message="confirmMessage"
            :confirm-label="confirmLabel"
            :variant="confirmVariant"
            :processing="processing"
            @close="pending = null"
            @confirm="confirmAction"
        />
</template>
