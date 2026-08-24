<script setup>
import AppCard from '../../Components/AppCard.vue';
import CopyButton from '../../Components/CopyButton.vue';
import EmptyState from '../../Components/EmptyState.vue';
import MoneyAmount from '../../Components/MoneyAmount.vue';
import { ref } from 'vue';

const props = defineProps({
    wallet: String,
    virtual_account: { type: Object, default: null },
    activity: { type: Array, default: () => [] },
});

const flashed = ref(false);
let flashTimer = 0;

const flashNumber = () => {
    window.clearTimeout(flashTimer);
    flashed.value = true;
    flashTimer = window.setTimeout(() => {
        flashed.value = false;
    }, 700);
};
</script>

<template>
    <Head title="Wallet" />

    <div class="overflow-hidden rounded-3xl bg-ink text-white shadow-sm">
        <div class="px-5 py-6 sm:px-6">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gold">Wallet balance</p>
            <p class="mt-2 text-4xl font-semibold leading-none tracking-normal">
                <MoneyAmount :value="wallet" />
            </p>
            <p class="mt-2 text-sm text-white/60">Transfer to your virtual account to add money.</p>
        </div>

        <div class="border-t border-white/10 px-5 py-5 sm:px-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gold">Virtual account</p>
                    <template v-if="virtual_account?.account_number">
                        <p
                            class="mt-2 text-2xl font-semibold tabular-nums tracking-wide"
                            :class="flashed ? 'copy-flash' : ''"
                        >{{ virtual_account.account_number }}</p>
                        <p class="mt-1 text-sm text-white/70">{{ virtual_account.bank_name || 'Monipay' }}</p>
                        <p class="mt-0.5 truncate text-sm text-white/55">{{ virtual_account.account_name }}</p>
                    </template>
                    <p v-else class="mt-2 text-sm text-white/70">
                        Ask your cooperative to issue a virtual account so you can fund this wallet.
                    </p>
                </div>
                <CopyButton
                    v-if="virtual_account?.account_number"
                    :value="virtual_account.account_number"
                    @copied="flashNumber"
                />
            </div>
        </div>
    </div>

    <AppCard class="mt-6" title="Activity" :padded="false">
        <EmptyState
            v-if="!activity.length"
            compact
            icon="savings"
            title="No activity yet"
            body="Credits into this wallet and payments from it will show here."
        />
        <ul v-else class="divide-y divide-slate-100">
            <li v-for="row in activity" :key="row.uuid" class="flex items-center justify-between gap-4 px-5 py-4">
                <div class="min-w-0">
                    <div class="font-semibold text-slate-900">{{ row.type }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ row.date }}</div>
                </div>
                <div
                    class="shrink-0 font-semibold tabular-nums"
                    :class="row.inflow ? 'text-teal-800' : 'text-slate-900'"
                >
                    {{ row.inflow ? '+' : '−' }}{{ row.amount }}
                </div>
            </li>
        </ul>
    </AppCard>
</template>
