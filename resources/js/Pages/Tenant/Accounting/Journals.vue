<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    journals: { type: Object, default: () => ({ data: [] }) },
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';
const form = useForm({
    q: props.filters.q || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    branch: props.filters.branch || '',
});

const reload = () => {
    router.get(`${base}/accounts/journals`, {
        q: form.q || undefined,
        from: form.from || undefined,
        to: form.to || undefined,
        branch: form.branch || undefined,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Journals" />
    <PageHeader eyebrow="Accounts" title="Journals" subtitle="Every posted entry is balanced. Open a row to see the debit and credit lines." />

    <form class="mt-6 grid gap-3 sm:grid-cols-4" @submit.prevent="reload">
        <FormField label="Search"><input v-model="form.q" type="search" :class="input" placeholder="Reference or narration" @change="reload"></FormField>
        <FormField label="From"><input v-model="form.from" type="date" :class="input" @change="reload"></FormField>
        <FormField label="To"><input v-model="form.to" type="date" :class="input" @change="reload"></FormField>
        <FormField v-if="branches.length" label="Branch">
            <AppSelect v-model="form.branch" :options="[{ uuid: '', name: 'All branches' }, ...branches]" option-value="uuid" option-label="name" @change="reload" />
        </FormField>
    </form>

    <AppCard class="mt-6" :padded="false">
        <EmptyState v-if="!journals.data?.length" icon="statement" title="No journals yet" body="Contributions, financing, and expense vouchers all post here." />
        <table v-else class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Reference</th>
                    <th class="px-5 py-3">Narration</th>
                    <th class="px-5 py-3 text-right">Debit</th>
                    <th class="px-5 py-3 text-right">Credit</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in journals.data" :key="row.uuid" class="border-t border-slate-100">
                    <td class="px-5 py-3 tabular-nums text-slate-500">{{ row.posted_at }}</td>
                    <td class="px-5 py-3">
                        <Link :href="`${base}/accounts/journals/${row.uuid}`" class="font-medium text-teal-800">{{ row.reference }}</Link>
                    </td>
                    <td class="px-5 py-3">
                        <div>{{ row.description }}</div>
                        <div class="text-xs text-slate-500">{{ row.lines }} lines<template v-if="row.branch"> · {{ row.branch }}</template></div>
                    </td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ row.debit }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ row.credit }}</td>
                    <td class="px-5 py-3"><StatusBadge :value="row.status" /></td>
                </tr>
            </tbody>
        </table>
        <Pagination v-if="journals.total" :links="journals.links" :from="journals.from" :to="journals.to" :total="journals.total" />
    </AppCard>
</template>
