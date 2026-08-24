<script setup>
import StatCard from '../../Components/StatCard.vue';
import AppCard from '../../Components/AppCard.vue';
import AppButton from '../../Components/AppButton.vue';
import CopyButton from '../../Components/CopyButton.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import MemberContributeForm from '../../Components/MemberContributeForm.vue';
import MemberMandatesCard from '../../Components/MemberMandatesCard.vue';
import PortalApplyLoanModal from '../../Components/PortalApplyLoanModal.vue';
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    member: Object,
    savings: String,
    shares: { type: String, default: '₦0' },
    loan_balance: String,
    islamic_balance: { type: String, default: '₦0' },
    announcements: Array,
    recent: { type: Object, default: () => ({ data: [] }) },
    virtual_account: { type: Object, default: null },
    mandates: { type: Array, default: () => [] },
    wallet: { type: String, default: '₦0' },
    wallet_naira: { type: [Number, String], default: 0 },
    savings_products: { type: Array, default: () => [] },
    loan_products: { type: Array, default: () => [] },
    islamic_products: { type: Array, default: () => [] },
});

const page = usePage();
const base = computed(() => page.props.tenantBase ?? `/${page.props.tenant?.slug}`);
const tenant = computed(() => page.props.tenant);
const contributeOpen = ref(false);
const loanOpen = ref(false);
const flashed = ref(false);
let flashTimer = 0;

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) {
        return 'Good morning';
    }
    if (hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
});
const usesLoans = computed(() => Boolean(props.loan_products?.length));
const qardProducts = computed(() => (props.islamic_products ?? []).filter((p) => p.structure === 'qard_hasan'));
const canApply = computed(() => usesLoans.value || Boolean(qardProducts.value.length));

const flashNumber = () => {
    window.clearTimeout(flashTimer);
    flashed.value = true;
    flashTimer = window.setTimeout(() => {
        flashed.value = false;
    }, 700);
};
</script>

<template>
    <Head title="Home" />
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-teal-800">{{ greeting }}</p>
                <h1 class="mt-1 text-[1.7rem] font-semibold leading-[1.15] tracking-tight text-slate-900 sm:text-3xl">
                    {{ member.first_name || member.name }}
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">{{ member.membership_number }}</p>
            </div>
            <StatusBadge class="mt-5 shrink-0" :value="member.status" />
        </div>

        <div class="mt-6 overflow-hidden rounded-3xl bg-ink px-5 py-4 text-white shadow-sm sm:px-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gold">Virtual account</p>
                    <template v-if="virtual_account?.account_number">
                        <p
                            class="mt-1 text-2xl font-semibold tabular-nums tracking-wide"
                            :class="flashed ? 'copy-flash' : ''"
                        >{{ virtual_account.account_number }}</p>
                        <p class="mt-1 text-sm text-white/70">
                            {{ virtual_account.bank_name || 'Monipay' }}
                            <span class="text-white/40">·</span>
                            {{ virtual_account.account_name || member.name }}
                        </p>
                    </template>
                    <p v-else class="mt-1 text-sm text-white/70">
                        Ask your cooperative to issue a virtual account so you can pay by bank transfer.
                    </p>
                </div>
                <CopyButton
                    v-if="virtual_account?.account_number"
                    :value="virtual_account.account_number"
                    label="Copy number"
                    wide
                    class="sm:w-auto"
                    @copied="flashNumber"
                />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
            <StatCard spread class="col-span-2 sm:col-span-1" label="Wallet" :value="wallet" />
            <StatCard class="min-w-0" label="Savings" :value="savings" />
            <StatCard class="min-w-0" label="Shares" :value="shares" />
            <StatCard v-if="tenant?.offers_conventional" class="min-w-0" label="Loan balance" :value="loan_balance" />
            <StatCard
                v-if="tenant?.offers_islamic"
                class="min-w-0"
                :class="tenant?.offers_conventional ? '' : 'col-span-2 sm:col-span-1'"
                label="Financing balance"
                :value="islamic_balance"
            />
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <AppButton type="button" variant="gold" @click="contributeOpen = true">Contribute</AppButton>
            <AppButton v-if="canApply" type="button" variant="secondary" @click="loanOpen = true">Apply for loan</AppButton>
            <AppButton :href="`${base}/portal/statement`" variant="secondary">View statement</AppButton>
        </div>

        <MemberMandatesCard :mandates="mandates" :products="savings_products" />

        <AppCard class="mt-6" title="Announcements" :padded="false">
            <EmptyState v-if="!announcements?.length" icon="announcements" title="No announcements yet" body="Your cooperative has not posted a note yet. Check back after the next meeting." />
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="a in announcements" :key="a.uuid">
                    <Link :href="`${base}/portal/announcements`" class="block px-5 py-4 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span v-if="a.is_pinned" class="h-1.5 w-1.5 rounded-full bg-gold"></span>
                                    <h2 class="font-semibold text-slate-900">{{ a.title }}</h2>
                                    <StatusBadge v-if="a.is_pinned" value="pinned" />
                                    <span v-if="a.is_platform" class="text-[11px] uppercase tracking-wide text-slate-400">CoopOS</span>
                                </div>
                                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ a.body }}</p>
                            </div>
                            <time class="shrink-0 text-xs text-slate-400">{{ a.published_at }}</time>
                        </div>
                    </Link>
                </li>
            </ul>
        </AppCard>

        <AppCard class="mt-6" title="Recent contributions" :padded="false">
            <template #header>
                <Link v-if="recent?.total" :href="`${base}/portal/statement`" class="text-sm font-medium text-teal-800">Statement</Link>
            </template>
            <EmptyState v-if="!recent?.data?.length" icon="savings" title="No contributions yet" body="Your savings history will appear here after the first payment is recorded." />
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="c in recent.data" :key="c.uuid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-900">{{ c.product }}</div>
                        <div class="mt-0.5 text-xs capitalize text-slate-500">{{ c.channel }} · {{ c.paid_on }}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-semibold tabular-nums text-teal-800">+{{ c.amount }}</div>
                    </div>
                </li>
            </ul>
            <template v-if="recent?.total" #footer>
                <Pagination :links="recent.links" :from="recent.from" :to="recent.to" :total="recent.total" />
            </template>
        </AppCard>

        <Modal
            :show="contributeOpen"
            title="Contribute"
            subtitle="Post contribution for the month they belong to from wallet"
            wide
            @close="contributeOpen = false"
        >
            <MemberContributeForm
                v-if="contributeOpen && savings_products.length"
                :products="savings_products"
                :mandates="mandates"
                :wallet="wallet"
                :wallet-naira="wallet_naira"
                @success="contributeOpen = false"
                @cancel="contributeOpen = false"
            />
            <EmptyState v-else-if="!savings_products.length" icon="savings" title="No savings products yet" body="Ask an officer to publish a contribution plan." />
        </Modal>

        <PortalApplyLoanModal
            :show="loanOpen"
            :loan-products="loan_products"
            :islamic-products="islamic_products"
            @close="loanOpen = false"
        />
</template>
