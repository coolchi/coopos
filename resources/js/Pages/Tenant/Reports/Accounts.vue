<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { router, usePage } from '@inertiajs/vue3';
import { printReport } from '../../../print';

const props = defineProps({
    year: Number,
    period: String,
    as_of: String,
    this_label: String,
    last_label: String,
    assets: Array,
    liabilities: Array,
    equity: { type: Array, default: () => [] },
    asset_total: String,
    liability_total: String,
    equity_total: String,
    financed_total: String,
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;

const reload = (extra = {}) => {
    router.get(`${base}/reports/accounts`, {
        year: extra.year ?? props.year,
        branch: extra.branch === undefined ? props.branch?.uuid : extra.branch,
    });
};
</script>

<template>
    <Head title="Balance sheet" />
    <PageHeader eyebrow="Reports" title="Statement of financial position" :subtitle="`As at ${as_of || period}`">
        <template #actions>
            <AppButton type="button" variant="secondary" @click="reload({ year: year - 1 })">Previous year</AppButton>
            <AppButton type="button" variant="secondary" @click="reload({ year: year + 1 })">Next year</AppButton>
            <AppButton type="button" variant="gold" @click="printReport">Print</AppButton>
        </template>
    </PageHeader>

    <div v-if="branches.length" class="mt-6 max-w-xs print:hidden">
        <FormField label="Branch">
            <AppSelect
                :model-value="branch?.uuid || ''"
                :options="[{ uuid: '', name: 'All branches' }, ...branches]"
                option-value="uuid"
                option-label="name"
                @update:model-value="(uuid) => reload({ branch: uuid || '' })"
            />
        </FormField>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <AppCard title="Assets" :padded="false">
            <EmptyState v-if="!assets.length" icon="reports" title="No asset balances" body="Post contributions and financing to build the statement of financial position." />
            <table v-else class="w-full text-sm">
                <thead class="bg-amber-100 text-left text-xs uppercase tracking-wide text-amber-950">
                    <tr>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3 text-right">{{ last_label }}</th>
                        <th class="px-5 py-3 text-right">{{ this_label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in assets" :key="row.code + row.label" class="border-t border-slate-100">
                        <td class="px-5 py-3">{{ row.label }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-slate-500">{{ row.last_year }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ row.this_year }}</td>
                    </tr>
                    <tr class="border-t border-amber-200 bg-amber-100 font-semibold">
                        <td class="px-5 py-3">Total assets</td>
                        <td class="px-5 py-3"></td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ asset_total }}</td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <div class="space-y-6">
            <AppCard title="Liabilities" :padded="false">
                <table class="w-full text-sm">
                    <thead class="bg-amber-100 text-left text-xs uppercase tracking-wide text-amber-950">
                        <tr>
                            <th class="px-5 py-3">Account</th>
                            <th class="px-5 py-3 text-right">{{ last_label }}</th>
                            <th class="px-5 py-3 text-right">{{ this_label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in liabilities" :key="row.code + row.label" class="border-t border-slate-100">
                            <td class="px-5 py-3">{{ row.label }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-500">{{ row.last_year }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ row.this_year }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 font-semibold">
                            <td class="px-5 py-3">Total</td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ liability_total }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
            <AppCard title="Equity" :padded="false">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="row in equity" :key="row.code + row.label" class="border-t border-slate-100">
                            <td class="px-5 py-3">{{ row.label }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-500">{{ row.last_year }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ row.this_year }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 font-semibold">
                            <td class="px-5 py-3">Total equity</td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ equity_total }}</td>
                        </tr>
                        <tr class="border-t border-amber-200 bg-amber-100 font-semibold">
                            <td class="px-5 py-3">Liabilities + equity</td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ financed_total }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
        </div>
    </div>
</template>
