<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { router, usePage } from '@inertiajs/vue3';
import { printReport } from '../../../print';

const props = defineProps({
    as_of: String,
    rows: { type: Array, default: () => [] },
    debit_total: String,
    credit_total: String,
    is_balanced: Boolean,
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm';

const reload = (extra = {}) => {
    router.get(`${base}/reports/trial-balance`, {
        as_of: extra.as_of ?? props.as_of,
        branch: extra.branch === undefined ? props.branch?.uuid : extra.branch,
    });
};
</script>

<template>
    <Head title="Trial balance" />
    <PageHeader eyebrow="Reports" title="Trial balance" :subtitle="`As of ${as_of}`">
        <template #actions>
            <AppButton type="button" variant="gold" @click="printReport">Print</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 grid gap-3 sm:grid-cols-3 print:hidden">
        <FormField label="As of">
            <input :value="as_of" type="date" :class="input" @change="(e) => reload({ as_of: e.target.value })">
        </FormField>
        <FormField v-if="branches.length" label="Branch">
            <AppSelect
                :model-value="branch?.uuid || ''"
                :options="[{ uuid: '', name: 'All branches' }, ...branches]"
                option-value="uuid"
                option-label="name"
                @update:model-value="(uuid) => reload({ branch: uuid || '' })"
            />
        </FormField>
        <p class="self-end text-sm" :class="is_balanced ? 'text-teal-800' : 'text-rose-700'">
            {{ is_balanced ? 'Debits equal credits.' : 'Trial balance is out of balance.' }}
        </p>
    </div>

    <AppCard class="mt-6" :padded="false">
        <EmptyState v-if="!rows.length" icon="reports" title="No posted balances" body="Journals will appear here after contributions, financing, or expenses are recorded." />
        <table v-else class="w-full text-sm">
            <thead class="bg-amber-100 text-left text-xs uppercase tracking-wide text-amber-950">
                <tr>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Account</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3 text-right">Debit</th>
                    <th class="px-5 py-3 text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.uuid" class="border-t border-slate-100">
                    <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{{ row.code }}</td>
                    <td class="px-5 py-2.5">{{ row.name }}</td>
                    <td class="px-5 py-2.5 text-slate-500">{{ row.type }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ row.debit_kobo ? row.debit : '' }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ row.credit_kobo ? row.credit : '' }}</td>
                </tr>
                <tr class="border-t border-amber-200 bg-amber-100 font-semibold">
                    <td class="px-5 py-3" colspan="3">Total</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ debit_total }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ credit_total }}</td>
                </tr>
            </tbody>
        </table>
    </AppCard>
</template>
