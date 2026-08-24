<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCan } from '../../../composables/useCan';

defineProps({ loans: Object });
const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
</script>

<template>
    <Head title="Loans" />
        <PageHeader eyebrow="Financing" title="Active loans" subtitle="Outstanding conventional facilities.">
            <template #actions>
                <AppButton :href="`${base}/loans/applications`" variant="secondary">Applications</AppButton>
                <AppButton v-if="can('products.view')" :href="`${base}/products`" variant="gold">Products</AppButton>
            </template>
        </PageHeader>
        <AppCard class="mt-8" :padded="false">
            <EmptyState v-if="!loans.data.length" icon="loans" title="No loans yet" body="Disbursed conventional loans will list here with outstanding balances." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3">Member</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Principal</th>
                        <th class="px-5 py-3">Outstanding</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in loans.data" :key="l.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <Link :href="`${base}/loans/${l.uuid}`" class="font-medium text-teal-800">{{ l.account_number }}</Link>
                        </td>
                        <td class="px-5 py-4">{{ l.member }}</td>
                        <td class="px-5 py-4">{{ l.product || '—' }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ l.principal }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ l.outstanding }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="l.status" /></td>
                    </tr>
                </tbody>
            </table>
            <template #footer>
                <Pagination :links="loans.links" :from="loans.from" :to="loans.to" :total="loans.total" />
            </template>
        </AppCard>
</template>
