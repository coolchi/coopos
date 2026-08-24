<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import RepaymentModal from '../../../Components/RepaymentModal.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({ loan: Object, repayments: Array });
const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const repayOpen = ref(false);
</script>

<template>
    <Head :title="loan.account_number" />
        <PageHeader eyebrow="Financing" :title="loan.account_number" :subtitle="`${loan.member} · ${loan.product}`">
            <template #actions>
                <StatusBadge :value="loan.status" />
            </template>
        </PageHeader>
        <AppCard class="mt-8" title="Record repayment">
            <p class="text-sm text-slate-500">
                Outstanding {{ loan.outstanding }}
                <span v-if="loan.next_due"> · Next due {{ loan.next_due }}{{ loan.next_amount ? ` · ${loan.next_amount}` : '' }}</span>
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Monthly collections can also be posted from
                <Link :href="`${base}/contributions`" class="font-medium text-teal-800 hover:underline">Contributions → Record payment</Link>.
            </p>
            <div class="mt-4">
                <AppButton v-if="can('loans.manage')" type="button" variant="gold" @click="repayOpen = true">Record repayment</AppButton>
            </div>
        </AppCard>
        <RepaymentModal
            :show="repayOpen"
            :title="`Repay ${loan.product || 'loan'}`"
            :subtitle="`${loan.member} · ${loan.account_number}`"
            :action="`${base}/loans/${loan.uuid}/repay`"
            :product="loan.product || 'Loan'"
            :amount="loan.next_amount_naira || ''"
            :outstanding="loan.outstanding || ''"
            @close="repayOpen = false"
        />
        <AppCard class="mt-6" title="Schedule" :padded="false">
            <EmptyState v-if="!repayments.length" icon="loans" title="No installments yet" body="The repayment schedule will appear here after this loan is disbursed." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">#</th>
                        <th class="px-5 py-3">Due</th>
                        <th class="px-5 py-3">Due amount</th>
                        <th class="px-5 py-3">Paid</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in repayments" :key="r.id" class="border-t border-slate-100">
                        <td class="px-5 py-4">{{ r.installment_number }}</td>
                        <td class="px-5 py-4">{{ r.due_on }}</td>
                        <td class="px-5 py-4">{{ r.amount_due }}</td>
                        <td class="px-5 py-4">{{ r.amount_paid }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="r.status" /></td>
                    </tr>
                </tbody>
            </table>
        </AppCard>
</template>
