<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { printReport } from '../../../print';

const props = defineProps({
    year: Number,
    period: String,
    this_label: String,
    last_label: String,
    income: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    total_income: String,
    total_income_last: String,
    total_expenditure: String,
    total_expenditure_last: String,
    net_surplus: String,
    net_surplus_last: String,
    net_surplus_kobo: Number,
    is_loss: Boolean,
    allocation: { type: Object, default: () => ({}) },
    hide_zeros: { type: Boolean, default: false },
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const tenant = computed(() => page.props.tenant);
const branchName = computed(() => props.branch?.name || page.props.branch?.name || 'All branches');
const admin = ref(((props.allocation.admin_to_reserve_kobo || 0) / 100).toString());
const hideZeros = ref(props.hide_zeros);

const visible = (rows) => hideZeros.value
    ? rows.filter((row) => row.this_year_kobo !== 0 || row.last_year_kobo !== 0)
    : rows;

const reload = (extra = {}) => {
    router.get(`${base}/reports/profit-loss`, {
        year: props.year,
        branch: props.branch?.uuid,
        hide_zeros: hideZeros.value ? 1 : undefined,
        admin_to_reserve: admin.value || undefined,
        ...extra,
    }, { preserveState: false });
};

const changeYear = (delta) => reload({ year: props.year + delta });
</script>

<template>
    <Head title="Profit & loss" />
    <PageHeader eyebrow="Reports" title="Income and expenditure" :subtitle="`${period} · ${branchName}`">
        <template #actions>
            <AppButton type="button" variant="secondary" @click="changeYear(-1)">Previous year</AppButton>
            <AppButton type="button" variant="secondary" @click="changeYear(1)">Next year</AppButton>
            <AppButton type="button" variant="gold" @click="printReport">Print</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 flex flex-wrap items-end gap-3 print:hidden">
        <FormField v-if="branches.length" label="Branch" class="min-w-52">
            <AppSelect
                :model-value="branch?.uuid || ''"
                :options="[{ uuid: '', name: 'All branches' }, ...branches]"
                option-value="uuid"
                option-label="name"
                @update:model-value="(uuid) => reload({ branch: uuid || undefined, year: year })"
            />
        </FormField>
        <label class="flex items-center gap-2 pb-2 text-sm text-slate-600">
            <input v-model="hideZeros" type="checkbox" class="rounded border-slate-300" @change="reload()">
            Hide zero lines
        </label>
    </div>

    <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5 print:rounded-none print:shadow-none print:ring-0">
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">{{ tenant?.name }}</p>
            <h2 class="mt-1 text-lg font-semibold">Profit &amp; loss — {{ branchName }}</h2>
            <p class="text-sm text-slate-500">{{ period }}</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-amber-100 text-left text-xs font-semibold uppercase tracking-wide text-amber-950">
                    <th class="px-5 py-3">Particulars</th>
                    <th class="px-5 py-3 text-right">{{ last_label }}</th>
                    <th class="px-5 py-3 text-right">{{ this_label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-amber-50 font-semibold uppercase tracking-wide text-amber-950">
                    <td class="px-5 py-2.5" colspan="3">Income</td>
                </tr>
                <tr v-for="row in visible(income)" :key="row.code" class="border-t border-slate-100">
                    <td class="px-5 py-2.5">{{ row.label }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums text-slate-500">{{ row.last_year }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ row.this_year }}</td>
                </tr>
                <tr class="border-t border-amber-200 bg-amber-100 font-semibold">
                    <td class="px-5 py-3">Total income</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ total_income_last }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ total_income }}</td>
                </tr>
                <tr class="bg-amber-50 font-semibold uppercase tracking-wide text-amber-950">
                    <td class="px-5 py-2.5" colspan="3">Expenditure</td>
                </tr>
                <tr v-for="row in visible(expenses)" :key="row.code" class="border-t border-slate-100">
                    <td class="px-5 py-2.5">{{ row.label }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums text-slate-500">{{ row.last_year }}</td>
                    <td class="px-5 py-2.5 text-right tabular-nums">{{ row.this_year }}</td>
                </tr>
                <tr class="border-t border-amber-200 bg-amber-100 font-semibold">
                    <td class="px-5 py-3">Sub total expenditure</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ total_expenditure_last }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ total_expenditure }}</td>
                </tr>
                <tr :class="is_loss ? 'bg-rose-600 text-white' : 'bg-teal-800 text-white'" class="font-semibold">
                    <td class="px-5 py-3">Net surplus / (loss)</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ net_surplus_last }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ net_surplus }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5 print:break-inside-avoid">
        <div class="border-b border-slate-100 bg-amber-50 px-5 py-4">
            <h3 class="font-semibold text-slate-900">Profit sharing</h3>
            <p class="mt-1 text-sm text-slate-500">Net surplus is split 60% profit share, 20% honorarium, 20% reserve fund.</p>
        </div>
        <table class="w-full text-sm">
            <tbody>
                <tr class="border-t border-slate-100">
                    <td class="px-5 py-3">Profit share (60%)</td>
                    <td class="px-5 py-3 text-right tabular-nums font-medium">{{ allocation.profit_share }}</td>
                </tr>
                <tr class="border-t border-slate-100">
                    <td class="px-5 py-3">Honorarium (20%)</td>
                    <td class="px-5 py-3 text-right tabular-nums font-medium">{{ allocation.honorarium }}</td>
                </tr>
                <tr class="border-t border-slate-100">
                    <td class="px-5 py-3">Reserve fund (20%)</td>
                    <td class="px-5 py-3 text-right tabular-nums font-medium">{{ allocation.reserve_fund }}</td>
                </tr>
                <tr class="border-t border-slate-100">
                    <td class="px-5 py-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span>Admin profit to reserve fund</span>
                            <AmountInput
                                v-model="admin"
                                class="w-32 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm print:hidden"
                                @change="reload()"
                            />
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right tabular-nums font-medium">{{ allocation.admin_to_reserve }}</td>
                </tr>
                <tr class="border-t border-amber-200 bg-amber-100 font-semibold">
                    <td class="px-5 py-3">Total profit</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ allocation.total }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
