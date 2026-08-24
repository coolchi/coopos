<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ ticket: Object });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const reply = useForm({ body: '' });
const closeForm = useForm({});

const send = () => reply.post(`${base}/support/${props.ticket.uuid}/reply`, {
    onSuccess: () => reply.reset(),
});
</script>

<template>
    <Head :title="ticket.subject" />
        <Link :href="`${base}/support`" class="text-sm text-teal-800">Back to support</Link>
        <PageHeader class="mt-3" eyebrow="Support" :title="ticket.subject" :subtitle="`${ticket.requester || 'Staff'} · ${ticket.created_at}`">
            <template #actions>
                <StatusBadge :value="ticket.priority" />
                <StatusBadge :value="ticket.status" />
                <AppButton
                    v-if="ticket.is_open"
                    type="button"
                    variant="secondary"
                    :disabled="closeForm.processing"
                    @click="closeForm.post(`${base}/support/${ticket.uuid}/close`)"
                >Close ticket</AppButton>
            </template>
        </PageHeader>

        <p class="mt-2 text-sm capitalize text-slate-500">{{ ticket.category }}</p>

        <div class="mt-8 space-y-4">
            <EmptyState v-if="!ticket.messages.length" icon="support" title="No messages yet" body="Add a reply below to continue this conversation." />
            <div
                v-for="(message, i) in ticket.messages"
                :key="i"
                class="max-w-2xl rounded-3xl p-5 shadow-sm ring-1 ring-black/5"
                :class="message.is_platform ? 'bg-white' : 'ml-auto bg-[#071c1b] text-white'"
            >
                <div class="text-xs" :class="message.is_platform ? 'text-slate-500' : 'text-teal-100/70'">
                    {{ message.is_platform ? 'CoopOS' : message.author }} · {{ message.created_at }}
                </div>
                <p class="mt-2 whitespace-pre-wrap text-sm" :class="message.is_platform ? 'text-slate-800' : 'text-white'">{{ message.body }}</p>
            </div>
        </div>

        <AppCard class="mt-8 max-w-2xl" :title="ticket.is_open ? 'Reply' : 'Ticket closed'">
            <p v-if="!ticket.is_open" class="mb-4 text-sm text-slate-600">
                Closed {{ ticket.resolved_at || '' }}. Sending a message will reopen it for the CoopOS team.
            </p>
            <form class="space-y-3" @submit.prevent="send">
                <textarea
                    v-model="reply.body"
                    rows="5"
                    required
                    maxlength="5000"
                    placeholder="Write a reply"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20"
                ></textarea>
                <p v-if="reply.errors.body" class="text-xs font-medium text-rose-700">{{ reply.errors.body }}</p>
                <AppButton type="submit" variant="gold" :disabled="reply.processing">
                    {{ ticket.is_open ? 'Send reply' : 'Reopen with reply' }}
                </AppButton>
            </form>
        </AppCard>
</template>
