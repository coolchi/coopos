<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { printReport } from '../../../print';

const props = defineProps({
    ledger: { type: Object, default: null },
    accounts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm';

const reload = (extra = {}) => {
    router.get(`${base}/reports/ledger`, {
        account: extra.account ?? props.filters.account,
        from: extra.from ?? props.filters.from,
        to: extra.to ?? props.filters.to,
        branch: extra.branch === undefined ? props.filters.branch : extra.branch,
    });
};
</script>

<template>
    <Head title="General ledger" />
    <PageHeader eyebrow="Reports" title="General ledger" :subtitle="ledger ? `${ledger.account.code} · ${ledger.account.name}` : 'Choose an account'">
        <template #actions>
            <AppButton type="button" variant="gold" @click="printReport">Print</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 grid gap-3 sm:grid-cols-4 print:hidden">
        <FormField label="Account">
            <AppSelect
                :model-value="filters.account || ''"
                :options="accounts"
                option-value="uuid"
                @update:model-value="(uuid) => reload({ account: uuid })"
            />
        </FormField>
        <FormField label="From">
            <input :value="filters.from" type="date" :class="input" @change="(e) => reload({ from: e.target.value })">
        </FormField>
        <FormField label="To">
            <input :value="filters.to" type="date" :class="input" @change="(e) => reload({ to: e.target.value })">
        </FormField>
        <FormField v-if="branches.length" label="Branch">
            <AppSelect
                :model-value="filters.branch || ''"
                :options="[{ uuid: '', name: 'All branches' }, ...branches]"
                option-value="uuid"
                option-label="name"
                @update:model-value="(uuid) => reload({ branch: uuid || '' })"
            />
        </FormField>
    </div>

    <div v-if="ledger" class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5">
            <div class="text-xs uppercase tracking-wide text-slate-500">Opening</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ ledger.opening }}</div>
        </div>
        <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5">
            <div class="text-xs uppercase tracking-wide text-slate-500">Closing</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ ledger.closing }}</div>
        </div>
    </div>

    <AppCard class="mt-6" :padded="false">
        <EmptyState v-if="!ledger?.lines?.length" icon="statement" title="No movement in this period" body="Posted journals for this account will list here." />
        <table v-else class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Reference</th>
                    <th class="px-5 py-3">Narration</th>
                    <th class="px-5 py-3 text-right">Debit</th>
                    <th class="px-5 py-3 text-right">Credit</th>
                    <th class="px-5 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, i) in ledger.lines" :key="i" class="border-t border-slate-100">
                    <td class="px-5 py-2.5 tabular-nums text-slate-500">{{ line.date }}</td>
                    <td class="px-5 py-2.5">
                        <Link v-if="line.uuid" :href="`${base}/accounts/journals/${line.uuid}`" class="font-medium text-teal-800">{{ line.reference }}</Link>
                        <span v-else>{{ line.reference }}</span>
                    </td>
                    <td class="px-5 py-2.5">{{ line.description }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ line.debit_kobo ? line.debit : '' }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ line.credit_kobo ? line.credit : '' }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ line.balance }}</td>
                </tr>
            </tbody>
        </table>
    </AppCard>
</template>
