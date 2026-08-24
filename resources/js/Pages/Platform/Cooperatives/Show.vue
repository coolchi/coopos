<script setup>
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    cooperative: Object,
    plans: Array,
    invoices: Array,
    tickets: Array,
    admins: Array,
});

const suspend = useForm({ reason: props.cooperative.suspension_reason || '' });
const planForm = useForm({ plan: props.cooperative.plan_slug });
const empty = useForm({});

const loginAsOpen = ref(false);

const requestLoginAs = () => {
    if (! props.cooperative.allow_impersonation) {
        return;
    }
    loginAsOpen.value = true;
};

const confirmLoginAs = () => {
    empty.post(`/platform/cooperatives/${props.cooperative.slug}/impersonate`);
};

const copyHost = async () => {
    await navigator.clipboard.writeText(props.cooperative.host);
};
</script>

<template>
            <Head :title="cooperative.name" />
        <Link href="/platform/cooperatives" class="text-sm text-teal-800">← All cooperatives</Link>
        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">{{ cooperative.host }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ cooperative.name }}</h1>
                <p class="mt-2 text-slate-500">{{ cooperative.type }} · {{ cooperative.mode }} · {{ cooperative.state || '—' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm" @click="copyHost">Copy host</button>
                <a :href="cooperative.url" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm" target="_blank" rel="noreferrer">Open workspace</a>
                <button
                    v-if="cooperative.allow_impersonation && cooperative.status === 'active'"
                    class="rounded-full bg-[#071c1b] px-4 py-2 text-sm text-white"
                    @click="requestLoginAs"
                >Login as</button>
            </div>
        </div>

        <div v-if="cooperative.suspension_reason && cooperative.status === 'suspended'" class="mt-6 rounded-3xl bg-rose-50 px-5 py-4 text-sm text-rose-950">
            Suspended: {{ cooperative.suspension_reason }}
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-4">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Status</div><div class="mt-1"><StatusBadge :value="cooperative.status" /></div></div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Members</div><div class="mt-1 text-lg font-semibold">{{ cooperative.members }}</div></div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Staff accounts</div><div class="mt-1 text-lg font-semibold">{{ cooperative.staff }}</div></div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5"><div class="text-xs text-slate-500">Billing</div><div class="mt-1 text-lg font-semibold">{{ cooperative.plan || '—' }} · {{ cooperative.billing }}</div></div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 lg:col-span-2">
                <h2 class="text-lg font-semibold">Society profile</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Legal name</dt><dd class="font-medium">{{ cooperative.legal_name }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ cooperative.email }}</dd></div>
                    <div><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ cooperative.phone || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Address</dt><dd class="font-medium">{{ cooperative.address || cooperative.city || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Approved</dt><dd class="font-medium">{{ cooperative.approved_at || 'Not yet' }}</dd></div>
                    <div><dt class="text-slate-500">Created</dt><dd class="font-medium">{{ cooperative.created_at }}</dd></div>
                    <div><dt class="text-slate-500">Trial ends</dt><dd class="font-medium">{{ cooperative.trial_ends_at || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Workspace</dt><dd class="font-medium">{{ cooperative.host }}</dd></div>
                </dl>
            </section>

            <section class="space-y-4">
                <form class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="planForm.post(`/platform/cooperatives/${cooperative.slug}/plan`)">
                    <h2 class="font-semibold">Plan</h2>
                    <AppSelect v-model="planForm.plan" :options="plans" option-value="slug" option-label="name" class="mt-3" />
                    <button class="mt-3 w-full rounded-2xl bg-[#071c1b] py-2.5 text-sm text-white" :disabled="planForm.processing">Update plan</button>
                </form>
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                    <h2 class="font-semibold">Lifecycle</h2>
                    <div class="mt-3 flex flex-col gap-2">
                        <button
                            v-if="cooperative.status === 'pending'"
                            class="rounded-2xl bg-teal-700 py-2.5 text-sm text-white"
                            @click="empty.post(`/platform/cooperatives/${cooperative.slug}/approve`)"
                        >Approve</button>
                        <button
                            v-if="cooperative.status === 'suspended' || cooperative.status === 'inactive'"
                            class="rounded-2xl bg-slate-800 py-2.5 text-sm text-white"
                            @click="empty.post(`/platform/cooperatives/${cooperative.slug}/reactivate`)"
                        >Reactivate</button>
                    </div>
                    <form v-if="cooperative.status === 'active'" class="mt-4" @submit.prevent="suspend.post(`/platform/cooperatives/${cooperative.slug}/suspend`)">
                        <textarea v-model="suspend.reason" rows="2" required placeholder="Suspension reason" class="w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm"></textarea>
                        <p v-if="suspend.errors.reason" class="mt-1 text-xs text-rose-700">{{ suspend.errors.reason }}</p>
                        <button class="mt-2 w-full rounded-2xl bg-rose-700 py-2.5 text-sm text-white">Suspend</button>
                    </form>
                </div>
            </section>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="font-semibold">Administrators</h2>
                <EmptyState v-if="!admins.length" icon="staff" title="No staff yet" body="This cooperative has not invited officers yet." />
                <ul v-else class="mt-4 space-y-3 text-sm">
                    <li v-for="admin in admins" :key="admin.email">
                        <div class="font-medium">{{ admin.name }}</div>
                        <div class="text-xs text-slate-500">{{ admin.email }} · {{ admin.last_login_at || 'Never signed in' }}</div>
                    </li>
                </ul>
            </section>
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="font-semibold">Invoices</h2>
                <EmptyState v-if="!invoices.length" icon="billing" title="No invoices" body="Subscription invoices for this society will appear here." />
                <ul v-else class="mt-4 space-y-3 text-sm">
                    <li v-for="invoice in invoices" :key="invoice.number" class="flex items-center justify-between gap-3">
                        <span>{{ invoice.number }}</span>
                        <span class="flex items-center gap-2">{{ invoice.amount }} <StatusBadge :value="invoice.status" /></span>
                    </li>
                </ul>
                <Link href="/platform/invoices" class="mt-4 inline-block text-xs text-teal-800">All invoices</Link>
            </section>
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="font-semibold">Tickets</h2>
                <EmptyState v-if="!tickets.length" icon="support" title="No tickets" body="Support conversations from this cooperative will land here." />
                <ul v-else class="mt-4 space-y-3 text-sm">
                    <li v-for="ticket in tickets" :key="ticket.uuid">
                        <Link :href="`/platform/tickets/${ticket.uuid}`" class="hover:text-teal-800">{{ ticket.subject }}</Link>
                        <span class="text-slate-400"> · {{ ticket.status }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <ConfirmModal
            :show="loginAsOpen"
            title="Login as administrator"
            :message="`You will sign in as the administrator of ${cooperative.name} and leave the platform workspace.`"
            confirm-label="Login as"
            :processing="empty.processing"
            @close="loginAsOpen = false"
            @confirm="confirmLoginAs"
        />
</template>
