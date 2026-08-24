<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import AppSelect from '../../../Components/AppSelect.vue';
import StatCard from '../../../Components/StatCard.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tickets: Array,
    counts: { type: Object, default: () => ({}) },
});
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const openCreate = ref(false);
const filter = ref('all');
const query = ref('');
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const templates = [
    { label: 'Login issue', subject: 'Cannot sign in', category: 'account', body: 'A staff or member account cannot sign in. Email address and what they see on the login page:' },
    { label: 'Payment', subject: 'Payment not reflecting', category: 'payment', body: 'A contribution or payout is missing. Member number, amount, date, and channel:' },
    { label: 'Billing', subject: 'Subscription / billing question', category: 'billing', body: 'Please help with our CoopOS subscription. What we need:' },
];

const form = useForm({
    subject: '',
    category: 'general',
    priority: 'normal',
    body: '',
});

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return props.tickets.filter((t) => {
        if (filter.value === 'open' && ! ['open', 'pending'].includes(t.status)) {
            return false;
        }
        if (filter.value === 'resolved' && ! ['resolved', 'closed'].includes(t.status)) {
            return false;
        }
        if (term && ! `${t.subject} ${t.preview || ''} ${t.category}`.toLowerCase().includes(term)) {
            return false;
        }

        return true;
    });
});

const applyTemplate = (tpl) => {
    form.subject = tpl.subject;
    form.category = tpl.category;
    form.body = tpl.body;
};

const save = () => form.post(`${base}/support`, {
    onSuccess: () => {
        openCreate.value = false;
        form.reset();
        form.category = 'general';
        form.priority = 'normal';
    },
});
</script>

<template>
    <Head title="Support" />
        <PageHeader eyebrow="Office" title="Support">
            <template #actions>
                <AppButton type="button" variant="gold" @click="openCreate = true">Open a ticket</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <StatCard label="All" :value="counts.all ?? tickets.length" />
            <StatCard label="Open" :value="counts.open ?? 0" />
            <StatCard label="Resolved" :value="counts.resolved ?? 0" />
        </div>

        <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex flex-wrap gap-2 text-xs">
                <button class="rounded-full px-3 py-1.5" :class="filter === 'all' ? 'bg-[#071c1b] text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'all'">All</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'open' ? 'bg-amber-600 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'open'">Open</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'resolved' ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'resolved'">Resolved</button>
            </div>
            <input
                v-model="query"
                type="search"
                placeholder="Search tickets"
                class="w-full rounded-2xl border-0 bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-black/5 lg:ml-auto lg:max-w-md"
            >
        </div>

        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!filtered.length" icon="support" title="No tickets here" body="Open a ticket for billing, payments, access, or a technical issue. CoopOS replies in the thread." />
            <div v-else class="divide-y divide-slate-100">
                <Link
                    v-for="t in filtered"
                    :key="t.uuid"
                    :href="`${base}/support/${t.uuid}`"
                    class="block px-5 py-4 transition hover:bg-slate-50"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900">{{ t.subject }}</div>
                            <p v-if="t.preview" class="mt-1 line-clamp-2 text-sm text-slate-600">{{ t.preview }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                <span class="capitalize">{{ t.category }}</span>
                                · {{ t.messages_count }} {{ t.messages_count === 1 ? 'message' : 'messages' }}
                                · {{ t.updated_at }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <StatusBadge :value="t.priority" />
                            <StatusBadge :value="t.status" />
                        </div>
                    </div>
                </Link>
            </div>
        </AppCard>

        <Modal :show="openCreate" title="Open a ticket" wide @close="openCreate = false">
            <form class="space-y-5" @submit.prevent="save">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tpl in templates"
                        :key="tpl.label"
                        type="button"
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200"
                        @click="applyTemplate(tpl)"
                    >{{ tpl.label }}</button>
                </div>
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-4">
                        <FormField label="Subject" :error="form.errors.subject">
                            <input v-model="form.subject" maxlength="160" :class="input" required>
                        </FormField>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField label="Category" :error="form.errors.category">
                                <AppSelect
                                    v-model="form.category"
                                    variant="outline"
                                    :options="[
                                        { value: 'general', label: 'General' },
                                        { value: 'technical', label: 'Technical' },
                                        { value: 'payment', label: 'Payment' },
                                        { value: 'account', label: 'Account' },
                                        { value: 'billing', label: 'Billing' },
                                    ]"
                                />
                            </FormField>
                            <FormField label="Priority" :error="form.errors.priority">
                                <AppSelect
                                    v-model="form.priority"
                                    variant="outline"
                                    :options="[
                                        { value: 'low', label: 'Low' },
                                        { value: 'normal', label: 'Normal' },
                                        { value: 'high', label: 'High' },
                                    ]"
                                />
                            </FormField>
                        </div>
                        <FormField label="Details" :error="form.errors.body">
                            <textarea v-model="form.body" rows="6" maxlength="5000" :class="input" required></textarea>
                            <p class="mt-1 text-right text-xs text-slate-500">{{ form.body.length }} / 5000</p>
                        </FormField>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="openCreate = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing">Open a ticket</AppButton>
                </div>
            </form>
        </Modal>
</template>
