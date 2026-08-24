<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import Modal from '../../../Components/Modal.vue';
import FormField from '../../../Components/FormField.vue';
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    requests: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const filter = ref('all');
const decision = ref(null);
const comment = ref('');
const error = ref('');
const processing = ref(false);

const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const filtered = computed(() => {
    if (filter.value === 'all') {
        return props.requests;
    }

    return props.requests.filter((row) => row.category === filter.value);
});

const filters = computed(() => {
    const items = [
        { value: 'all', label: 'All', count: props.counts.all ?? props.requests.length },
        { value: 'loan', label: 'Loans', count: props.counts.loan ?? 0 },
        { value: 'withdrawal', label: 'Withdrawals', count: props.counts.withdrawal ?? 0 },
    ];

    return items.filter((item) => item.value === 'all' || item.count > 0 || item.value === 'loan');
});

const active = computed(() => decision.value?.request || null);
const isReject = computed(() => decision.value?.action === 'rejected');
const modalTitle = computed(() => (isReject.value ? 'Reject request' : 'Approve request'));
const confirmLabel = computed(() => (isReject.value ? 'Reject' : 'Approve'));

const openDecision = (request, action) => {
    decision.value = { request, action };
    comment.value = '';
    error.value = '';
};

const closeDecision = () => {
    if (processing.value) {
        return;
    }
    decision.value = null;
    comment.value = '';
    error.value = '';
};

const submitDecision = () => {
    if (! decision.value) {
        return;
    }

    const note = comment.value.trim();
    error.value = '';

    if (isReject.value && ! note) {
        error.value = 'Add a short reason when rejecting.';
        return;
    }

    processing.value = true;
    useForm({
        decision: decision.value.action,
        comment: note,
    }).post(`${base}/approvals/${decision.value.request.uuid}`, {
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            decision.value = null;
            comment.value = '';
            error.value = '';
        },
        onError: (bag) => {
            error.value = bag.comment || Object.values(bag)[0] || 'Could not save decision.';
        },
    });
};
</script>

<template>
    <Head title="Approvals" />
    <PageHeader title="Approvals">
        <template #actions>
            <AppButton :href="`${base}/settings/workflows`" variant="secondary">Workflows</AppButton>
        </template>
    </PageHeader>

    <div class="mt-5 flex flex-wrap gap-2 text-xs">
        <button
            v-for="item in filters"
            :key="item.value"
            class="rounded-full px-3 py-1.5"
            :class="filter === item.value ? 'bg-[#071c1b] text-white' : 'bg-white shadow-sm ring-1 ring-black/5'"
            @click="filter = item.value"
        >
            {{ item.label }}
            <span class="ml-1 opacity-70">{{ item.count }}</span>
        </button>
    </div>

    <AppCard class="mt-4" :padded="false">
        <EmptyState
            v-if="!filtered.length"
            icon="approvals"
            title="Nothing waiting"
            body="New withdrawal and loan requests will land here for your decision."
        />
        <table v-else class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 font-medium sm:px-5">Member</th>
                    <th class="hidden px-4 py-2.5 font-medium md:table-cell">Details</th>
                    <th class="px-4 py-2.5 font-medium text-right">Amount</th>
                    <th class="hidden px-4 py-2.5 font-medium lg:table-cell">Step</th>
                    <th class="px-4 py-2.5 font-medium sm:px-5"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in filtered" :key="r.uuid" class="border-t border-slate-100 align-top">
                    <td class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <StatusBadge :value="r.status" />
                            <span class="text-xs text-slate-500">{{ r.type_label }}</span>
                        </div>
                        <Link
                            v-if="r.member_href"
                            :href="r.member_href"
                            class="mt-1 block font-medium text-slate-900 hover:text-teal-800"
                        >
                            {{ r.member || 'Unknown member' }}
                        </Link>
                        <div v-else class="mt-1 font-medium text-slate-900">{{ r.member || 'Unknown member' }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            <template v-if="r.member_number">{{ r.member_number }}</template>
                            <template v-if="r.product"> · {{ r.product }}</template>
                            <template v-if="r.branch"> · {{ r.branch }}</template>
                        </div>
                        <div class="mt-1 text-xs text-slate-600 md:hidden">
                            <span v-if="r.purpose">{{ r.purpose }}</span>
                            <span v-if="r.meta?.length" class="text-slate-500"> · {{ r.meta.join(' · ') }}</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500 lg:hidden">
                            {{ r.step_name }}
                            <template v-if="r.steps_total"> · {{ r.step }}/{{ r.steps_total }}</template>
                        </div>
                    </td>
                    <td class="hidden px-4 py-3 md:table-cell">
                        <div class="max-w-md text-slate-800">{{ r.purpose || '—' }}</div>
                        <div v-if="r.meta?.length" class="mt-0.5 text-xs text-slate-500">{{ r.meta.join(' · ') }}</div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="font-semibold tabular-nums text-slate-900">{{ r.amount || '—' }}</div>
                        <div class="text-[11px] text-slate-500">{{ r.amount_label }}</div>
                    </td>
                    <td class="hidden px-4 py-3 lg:table-cell">
                        <div class="text-slate-800">{{ r.step_name }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            <template v-if="r.steps_total">{{ r.step }}/{{ r.steps_total }}</template>
                            <template v-if="r.step_role"> · {{ r.step_role }}</template>
                            <template v-if="r.requested_at"> · {{ r.requested_at }}</template>
                        </div>
                    </td>
                    <td class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap justify-end gap-1.5">
                            <AppButton type="button" variant="gold" @click="openDecision(r, 'approved')">
                                Approve
                            </AppButton>
                            <AppButton type="button" variant="danger" @click="openDecision(r, 'rejected')">
                                Reject
                            </AppButton>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </AppCard>

    <Modal :show="Boolean(decision)" :title="modalTitle" @close="closeDecision">
        <div v-if="active" class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-medium text-slate-500">{{ active.type_label }}</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ active.member || 'Unknown member' }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            <template v-if="active.member_number">{{ active.member_number }}</template>
                            <template v-if="active.product"> · {{ active.product }}</template>
                            <template v-if="active.branch"> · {{ active.branch }}</template>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold tabular-nums text-slate-900">{{ active.amount || '—' }}</div>
                        <div class="text-[11px] text-slate-500">{{ active.amount_label }}</div>
                    </div>
                </div>
                <div v-if="active.purpose" class="mt-3 text-sm text-slate-700">{{ active.purpose }}</div>
                <div v-if="active.meta?.length" class="mt-1 text-xs text-slate-500">{{ active.meta.join(' · ') }}</div>
                <div class="mt-3 text-xs text-slate-500">
                    {{ active.step_name }}
                    <template v-if="active.steps_total"> · step {{ active.step }}/{{ active.steps_total }}</template>
                    <template v-if="active.step_role"> · needs {{ active.step_role }}</template>
                </div>
            </div>

            <FormField :label="isReject ? 'Reason' : 'Comment (optional)'" :error="error">
                <textarea
                    v-model="comment"
                    rows="3"
                    :placeholder="isReject ? 'Why are you rejecting this request?' : 'Optional note for the audit trail'"
                    :class="input"
                    :required="isReject"
                />
            </FormField>

            <div v-if="active.history?.length" class="rounded-2xl border border-slate-100 px-4 py-3">
                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Earlier decisions</div>
                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                    <li v-for="(item, index) in active.history" :key="index">
                        <span class="capitalize">{{ item.decision }}</span> · {{ item.step }} · {{ item.by }}
                        <span v-if="item.comment"> — “{{ item.comment }}”</span>
                    </li>
                </ul>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton type="button" variant="secondary" :disabled="processing" @click="closeDecision">Cancel</AppButton>
                <AppButton
                    type="button"
                    :variant="isReject ? 'danger' : 'gold'"
                    :disabled="processing"
                    @click="submitDecision"
                >
                    {{ confirmLabel }}
                </AppButton>
            </div>
        </div>
    </Modal>
</template>
