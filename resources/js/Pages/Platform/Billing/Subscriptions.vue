<script setup>
import Pagination from '../../../Components/Pagination.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ subscriptions: Object, filters: Object, summary: Object });
const status = ref(props.filters?.status || '');
const cancelling = ref(null);
const cancelProcessing = ref(false);
const cancelMessage = computed(() => (
    cancelling.value
        ? `Cancel ${cancelling.value.cooperative}'s ${cancelling.value.plan} subscription?`
        : ''
));

const setStatus = (sub, next) => {
    if (next === 'cancelled') {
        cancelling.value = sub;
        return;
    }
    router.post(`/platform/subscriptions/${sub.uuid}/status`, { status: next });
};

const confirmCancel = () => {
    if (! cancelling.value) {
        return;
    }
    cancelProcessing.value = true;
    router.post(`/platform/subscriptions/${cancelling.value.uuid}/status`, { status: 'cancelled' }, {
        onFinish: () => {
            cancelProcessing.value = false;
            cancelling.value = null;
        },
    });
};
</script>

<template>
            <Head title="Subscriptions" />
        <h1 class="text-3xl font-semibold tracking-tight">Subscriptions</h1>
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Active</div><div class="mt-1 text-xl font-semibold">{{ summary.active }}</div></div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Trialing</div><div class="mt-1 text-xl font-semibold">{{ summary.trialing }}</div></div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Past due</div><div class="mt-1 text-xl font-semibold">{{ summary.past_due }}</div></div>
        </div>
        <AppSelect
            v-model="status"
            class="mt-6 max-w-xs"
            variant="white"
            placeholder="All statuses"
            :options="[{ value: 'trialing', label: 'Trialing' }, { value: 'active', label: 'Active' }, { value: 'past_due', label: 'Past due' }, { value: 'cancelled', label: 'Cancelled' }]"
            @change="router.get('/platform/subscriptions', { status: status || undefined }, { preserveState: true, replace: true })"
        />
        <div class="mt-4 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState v-if="!subscriptions.data.length" icon="search" title="No subscriptions match" body="Try another plan or status — new cooperatives appear here after onboarding." />
            <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Cooperative</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Price</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Started</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sub in subscriptions.data" :key="sub.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <Link v-if="sub.slug" :href="`/platform/cooperatives/${sub.slug}`" class="font-medium hover:text-teal-800">{{ sub.cooperative }}</Link>
                        </td>
                        <td class="px-5 py-4">{{ sub.plan }} · {{ sub.interval }}</td>
                        <td class="px-5 py-4">{{ sub.price }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="sub.status" /></td>
                        <td class="px-5 py-4">{{ sub.starts_at }}</td>
                        <td class="px-5 py-4 text-right">
                            <button v-if="sub.status !== 'active'" class="text-teal-800" @click="setStatus(sub, 'active')">Activate</button>
                            <button v-if="sub.status !== 'cancelled'" class="ml-3 text-rose-700" @click="setStatus(sub, 'cancelled')">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <Pagination :links="subscriptions.links" :from="subscriptions.from" :to="subscriptions.to" :total="subscriptions.total" />
        </div>

        <ConfirmModal
            :show="Boolean(cancelling)"
            title="Cancel subscription"
            :message="cancelMessage"
            confirm-label="Cancel subscription"
            variant="danger"
            :processing="cancelProcessing"
            @close="cancelling = null"
            @confirm="confirmCancel"
        />
</template>
