<script setup>
import AppCard from '../../Components/AppCard.vue';
import AppButton from '../../Components/AppButton.vue';
import CopyButton from '../../Components/CopyButton.vue';
import EmptyState from '../../Components/EmptyState.vue';
import FormField from '../../Components/FormField.vue';
import MoneyAmount from '../../Components/MoneyAmount.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ statement: Object, products: Array, filters: Object });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900';
const run = () => router.get(`${base}/portal/statement`, { from: from.value, to: to.value });
</script>

<template>
    <Head title="Statement" />
    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Statement</h1>
    <p class="mt-1 text-sm text-slate-500">Opening and closing balances for the dates you pick.</p>

    <form class="mt-5 grid grid-cols-2 gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:grid-cols-[1fr_1fr_auto] sm:items-end sm:p-5" @submit.prevent="run">
        <FormField label="From">
            <input v-model="from" type="date" :class="input" required>
        </FormField>
        <FormField label="To">
            <input v-model="to" type="date" :class="input" required>
        </FormField>
        <AppButton type="submit" class="col-span-2 sm:col-span-1">Update</AppButton>
    </form>

    <template v-if="statement">
        <div class="mt-5 overflow-hidden rounded-3xl bg-ink px-5 py-5 text-white shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gold">Reference</p>
                    <p class="mt-1 truncate font-medium tracking-wide">{{ statement.reference }}</p>
                    <p class="mt-1 text-sm text-white/60">{{ statement.period_label || `${statement.period?.from} – ${statement.period?.to}` }}</p>
                </div>
                <CopyButton :value="statement.reference" />
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Opening</p>
                <p class="mt-2 text-xl font-semibold text-slate-900 sm:text-2xl">
                    <MoneyAmount :value="statement.opening" />
                </p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Closing</p>
                <p class="mt-2 text-xl font-semibold text-slate-900 sm:text-2xl">
                    <MoneyAmount :value="statement.closing" />
                </p>
            </div>
        </div>

        <AppCard class="mt-4" title="Activity" :padded="false">
            <EmptyState
                v-if="!statement.lines?.length"
                compact
                icon="statement"
                title="No movements in this period"
                body="Try a wider date range — contributions and withdrawals will fill this ledger."
            />
            <ul v-else class="divide-y divide-slate-100">
                <li
                    v-for="line in statement.lines"
                    :key="line.reference"
                    class="flex items-start justify-between gap-4 px-5 py-4"
                >
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-900">{{ line.label || line.type?.replaceAll('_', ' ') }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ line.date_label || line.date }}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div
                            class="font-semibold"
                            :class="line.inflow ? 'text-teal-800' : 'text-slate-900'"
                        >
                            {{ line.inflow ? '+' : '−' }}<MoneyAmount :value="line.amount" />
                        </div>
                        <div class="mt-0.5 text-xs text-slate-400">
                            Bal <MoneyAmount :value="line.running" />
                        </div>
                    </div>
                </li>
            </ul>
        </AppCard>
    </template>
</template>
