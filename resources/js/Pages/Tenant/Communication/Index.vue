<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import StatCard from '../../../Components/StatCard.vue';
import AppSelect from '../../../Components/AppSelect.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    announcements: Array,
    platform: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const composeOpen = ref(false);
const ticketOpen = ref(false);
const editing = ref(null);
const removing = ref(null);
const filter = ref('all');
const query = ref('');
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const templates = [
    { label: 'AGM', title: 'Annual general meeting', body: 'Members are invited to the AGM. Date, time, and venue will be confirmed. Please come with your membership number.' },
    { label: 'Contribution reminder', title: 'Contribution reminder', body: 'Kindly pay this month’s savings on or before the due date. You can contribute from the member portal or at your branch.' },
    { label: 'Holiday hours', title: 'Office hours update', body: 'Our branches will run reduced hours this week. Contact your branch admin if you need to make a payment or withdrawal.' },
];

const ticketTemplates = [
    { label: 'Login issue', subject: 'Cannot sign in', category: 'account', body: 'A staff or member account cannot sign in. Email address and what they see on the login page:' },
    { label: 'Payment', subject: 'Payment not reflecting', category: 'payment', body: 'A contribution or payout is missing. Member number, amount, date, and channel:' },
    { label: 'Billing', subject: 'Subscription / billing question', category: 'billing', body: 'Please help with our CoopOS subscription. What we need:' },
];

const form = useForm({
    title: '',
    body: '',
    audience: 'all',
    branch_uuid: '',
    is_pinned: false,
    publish: true,
});

const ticket = useForm({
    subject: '',
    category: 'general',
    priority: 'normal',
    body: '',
});

const audienceLabel = (item) => {
    if (item.audience === 'active') {
        return 'Active members';
    }
    if (item.audience === 'branch') {
        return item.branch ? `Branch · ${item.branch}` : 'One branch';
    }

    return 'All members';
};

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return props.announcements.filter((a) => {
        if (filter.value === 'published' && a.is_draft) {
            return false;
        }
        if (filter.value === 'drafts' && ! a.is_draft) {
            return false;
        }
        if (filter.value === 'pinned' && ! a.is_pinned) {
            return false;
        }
        if (term && ! `${a.title} ${a.body}`.toLowerCase().includes(term)) {
            return false;
        }

        return true;
    });
});

const counts = computed(() => ({
    all: props.announcements.length,
    published: props.announcements.filter((a) => ! a.is_draft).length,
    drafts: props.announcements.filter((a) => a.is_draft).length,
    pinned: props.announcements.filter((a) => a.is_pinned).length,
}));

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.audience = 'all';
    form.branch_uuid = props.branches[0]?.uuid || '';
    form.is_pinned = false;
    form.publish = true;
    editing.value = null;
};

const openCreate = () => {
    resetForm();
    composeOpen.value = true;
};

const applyTemplate = (tpl) => {
    form.title = tpl.title;
    form.body = tpl.body;
};

const applyTicketTemplate = (tpl) => {
    ticket.subject = tpl.subject;
    ticket.category = tpl.category;
    ticket.body = tpl.body;
};

const openEdit = (item) => {
    editing.value = item;
    form.title = item.title;
    form.body = item.body;
    form.audience = item.audience || 'all';
    form.branch_uuid = item.branch_uuid || props.branches[0]?.uuid || '';
    form.is_pinned = item.is_pinned;
    form.publish = ! item.is_draft;
    form.clearErrors();
    composeOpen.value = true;
};

const save = (publish) => {
    form.publish = publish;
    const options = {
        onSuccess: () => {
            composeOpen.value = false;
            resetForm();
        },
    };
    if (editing.value) {
        form.post(`${base}/communication/${editing.value.uuid}`, options);
        return;
    }
    form.post(`${base}/communication`, options);
};

const openTicket = () => {
    ticket.reset();
    ticket.clearErrors();
    ticket.category = 'general';
    ticket.priority = 'normal';
    ticketOpen.value = true;
};

const saveTicket = () => ticket.post(`${base}/support`, {
    onSuccess: () => {
        ticketOpen.value = false;
        ticket.reset();
        ticket.category = 'general';
        ticket.priority = 'normal';
    },
});

const pin = (item) => router.post(`${base}/communication/${item.uuid}/pin`);
const publish = (item) => router.post(`${base}/communication/${item.uuid}/publish`);
const confirmRemove = () => {
    if (! removing.value) {
        return;
    }
    router.delete(`${base}/communication/${removing.value.uuid}`, {
        onFinish: () => { removing.value = null; },
    });
};
</script>

<template>
    <Head title="Announcements" />
        <PageHeader eyebrow="Office" title="Announcements">
            <template #actions>
                <AppButton type="button" variant="secondary" @click="openTicket">Open a ticket</AppButton>
                <AppButton type="button" variant="gold" @click="openCreate">Publish announcement</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="All" :value="counts.all" />
            <StatCard label="Published" :value="counts.published" />
            <StatCard label="Drafts" :value="counts.drafts" />
            <StatCard label="Pinned" :value="counts.pinned" />
        </div>

        <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex flex-wrap gap-2 text-xs">
                <button class="rounded-full px-3 py-1.5" :class="filter === 'all' ? 'bg-[#071c1b] text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'all'">All</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'published' ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'published'">Published</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'drafts' ? 'bg-amber-600 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'drafts'">Drafts</button>
                <button class="rounded-full px-3 py-1.5" :class="filter === 'pinned' ? 'bg-[#d4b06a] text-[#071c1b]' : 'bg-white shadow-sm ring-1 ring-black/5'" @click="filter = 'pinned'">Pinned</button>
            </div>
            <input
                v-model="query"
                type="search"
                placeholder="Search title or message"
                class="w-full rounded-2xl border-0 bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-black/5 lg:ml-auto lg:max-w-md"
            >
        </div>

        <AppCard v-if="platform.length" class="mt-6" title="From CoopOS">
            <ul class="space-y-3">
                <li v-for="item in platform" :key="item.uuid" class="text-sm">
                    <div class="font-medium text-slate-900">{{ item.title }}</div>
                    <p class="mt-1 line-clamp-3 whitespace-pre-wrap text-slate-600">{{ item.body }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ item.published_at }}</p>
                </li>
            </ul>
        </AppCard>

        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!filtered.length" icon="announcements" title="No announcements here" body="Share an AGM date, a contribution reminder, or a branch update." />
            <div v-else class="divide-y divide-slate-100">
                <article v-for="item in filtered" :key="item.uuid" class="px-5 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-slate-900">{{ item.title }}</h2>
                                <StatusBadge v-if="item.is_draft" value="draft" />
                                <StatusBadge v-if="item.is_pinned" value="pinned" />
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ audienceLabel(item) }}
                                · {{ item.is_draft ? `Saved ${item.created_at}` : item.published_at }}
                                <span v-if="item.author"> · {{ item.author }}</span>
                            </p>
                            <p class="mt-3 whitespace-pre-wrap text-sm text-slate-600">{{ item.body }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton v-if="item.is_draft" type="button" variant="gold" @click="publish(item)">Publish</AppButton>
                            <AppButton type="button" variant="secondary" @click="pin(item)">{{ item.is_pinned ? 'Unpin' : 'Pin' }}</AppButton>
                            <AppButton type="button" variant="secondary" @click="openEdit(item)">Edit</AppButton>
                            <AppButton type="button" variant="danger" @click="removing = item">Remove</AppButton>
                        </div>
                    </div>
                </article>
            </div>
        </AppCard>

        <Modal
            :show="composeOpen"
            :title="editing ? 'Edit announcement' : 'Publish announcement'"
            wide
            @close="composeOpen = false"
        >
            <form class="space-y-5" @submit.prevent="save(true)">
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
                        <FormField label="Title" :error="form.errors.title">
                            <input v-model="form.title" maxlength="160" :class="input" required>
                        </FormField>
                        <FormField label="Message" :error="form.errors.body">
                            <textarea v-model="form.body" rows="6" maxlength="5000" :class="input" required></textarea>
                            <p class="mt-1 text-right text-xs text-slate-500">{{ form.body.length }} / 5000</p>
                        </FormField>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Audience</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <FormField label="Who should see this" :error="form.errors.audience">
                            <select v-model="form.audience" :class="input">
                                <option value="all">All members</option>
                                <option value="active">Active members only</option>
                                <option value="branch" :disabled="!branches.length">One branch</option>
                            </select>
                        </FormField>
                        <FormField v-if="form.audience === 'branch'" label="Branch" :error="form.errors.branch_uuid">
                            <select v-model="form.branch_uuid" :class="input" required>
                                <option v-for="b in branches" :key="b.uuid" :value="b.uuid">{{ b.name }} · {{ b.code }}</option>
                            </select>
                        </FormField>
                    </div>
                    <label class="mt-4 flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input v-model="form.is_pinned" type="checkbox" class="rounded border-slate-300">
                        Pin to the top
                    </label>
                </section>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="composeOpen = false">Cancel</AppButton>
                    <AppButton v-if="!editing || editing.is_draft" type="button" variant="secondary" :disabled="form.processing" @click="save(false)">Save draft</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing">
                        {{ editing && !editing.is_draft ? 'Save changes' : 'Publish announcement' }}
                    </AppButton>
                </div>
            </form>
        </Modal>

        <Modal :show="ticketOpen" title="Open a ticket" wide @close="ticketOpen = false">
            <form class="space-y-5" @submit.prevent="saveTicket">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tpl in ticketTemplates"
                        :key="tpl.label"
                        type="button"
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200"
                        @click="applyTicketTemplate(tpl)"
                    >{{ tpl.label }}</button>
                </div>
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-4">
                        <FormField label="Subject" :error="ticket.errors.subject">
                            <input v-model="ticket.subject" maxlength="160" :class="input" required>
                        </FormField>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField label="Category" :error="ticket.errors.category">
                                <AppSelect
                                    v-model="ticket.category"
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
                            <FormField label="Priority" :error="ticket.errors.priority">
                                <AppSelect
                                    v-model="ticket.priority"
                                    variant="outline"
                                    :options="[
                                        { value: 'low', label: 'Low' },
                                        { value: 'normal', label: 'Normal' },
                                        { value: 'high', label: 'High' },
                                    ]"
                                />
                            </FormField>
                        </div>
                        <FormField label="Details" :error="ticket.errors.body">
                            <textarea v-model="ticket.body" rows="6" maxlength="5000" :class="input" required></textarea>
                            <p class="mt-1 text-right text-xs text-slate-500">{{ ticket.body.length }} / 5000</p>
                        </FormField>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="ticketOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="ticket.processing">Open a ticket</AppButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(removing)"
            title="Remove announcement"
            :message="removing ? `Remove “${removing.title}”? Members will no longer see it.` : ''"
            confirm-label="Remove"
            variant="danger"
            @close="removing = null"
            @confirm="confirmRemove"
        />
</template>
