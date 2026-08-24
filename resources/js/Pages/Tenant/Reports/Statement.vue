<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { printReport } from '../../../print';

const props = defineProps({ statement: Object, members: Array, filters: Object });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const member_uuid = ref(props.filters?.member_uuid || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
const run = () => router.get(`${base}/reports/statement`, { member_uuid: member_uuid.value, from: from.value, to: to.value });
</script>

<template>
    <Head title="Member statement" />
    <PageHeader eyebrow="Reports" title="Member statement" subtitle="Opening and closing savings balances with a running ledger.">
        <template v-if="statement" #actions>
            <AppButton type="button" variant="gold" @click="printReport">Print</AppButton>
        </template>
    </PageHeader>
    <AppCard class="mt-8 print:hidden" title="Period">
        <form class="grid gap-3 md:grid-cols-4" @submit.prevent="run">
            <FormField label="Member">
                <AppSelect v-model="member_uuid" placeholder="Select member" :options="members" option-value="uuid" required />
            </FormField>
            <FormField label="From"><input v-model="from" type="date" :class="input" required></FormField>
            <FormField label="To"><input v-model="to" type="date" :class="input" required></FormField>
            <div class="flex items-end"><AppButton type="submit">Generate</AppButton></div>
        </form>
    </AppCard>
    <AppCard v-if="statement" class="mt-6 print:shadow-none print:ring-0" :title="`${statement.member} · ${statement.membership_number}`">
        <p class="text-sm text-slate-500">{{ statement.reference }} · {{ statement.period?.from }} – {{ statement.period?.to }}</p>
        <div class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
            <div>Opening <span class="font-semibold">{{ statement.opening }}</span></div>
            <div>Closing <span class="font-semibold">{{ statement.closing }}</span></div>
            <div>Savings <span class="font-semibold">{{ statement.savings_balance }}</span></div>
        </div>
        <table class="mt-6 w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="py-2">Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Running</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in statement.lines" :key="line.reference" class="border-t border-slate-100">
                    <td class="py-2">{{ line.date }}</td>
                    <td class="capitalize">{{ line.type?.replaceAll('_', ' ') }}</td>
                    <td>{{ line.amount }}</td>
                    <td>{{ line.running }}</td>
                </tr>
            </tbody>
        </table>
    </AppCard>
    <AppCard v-else class="mt-6 print:hidden">
        <EmptyState icon="statement" title="Choose a member" body="Pick a member and date range to print a savings statement." />
    </AppCard>
</template>
