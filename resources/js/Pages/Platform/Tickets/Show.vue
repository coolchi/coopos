<script setup>
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ ticket: Object });
const form = useForm({ body: '', status: props.ticket.status });
</script>

<template>
            <Head :title="ticket.subject" />
        <Link href="/platform/tickets" class="text-sm text-teal-800">← Inbox</Link>
        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">
            <Link v-if="ticket.slug" :href="`/platform/cooperatives/${ticket.slug}`">{{ ticket.cooperative }}</Link>
            <span v-else>{{ ticket.cooperative }}</span>
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-semibold tracking-tight">{{ ticket.subject }}</h1>
            <StatusBadge :value="ticket.status" />
            <StatusBadge :value="ticket.priority" />
        </div>
        <p class="mt-2 text-sm text-slate-500">{{ ticket.requester }} · {{ ticket.category }} · {{ ticket.created_at }}</p>

        <EmptyState v-if="!ticket.messages.length" icon="support" title="No messages yet" body="The conversation is empty. Send the first reply to the cooperative." class="mt-8 rounded-3xl bg-white" />
        <div v-else class="mt-8 space-y-4">
            <div v-for="(message, i) in ticket.messages" :key="i" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5">
                <div class="text-xs text-slate-500">{{ message.author }} · {{ message.created_at }}</div>
                <p class="mt-2 whitespace-pre-wrap text-sm">{{ message.body }}</p>
            </div>
        </div>

        <form class="mt-8 max-w-2xl rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="form.post(`/platform/tickets/${ticket.uuid}`, { onSuccess: () => form.reset('body') })">
            <textarea v-model="form.body" rows="5" required placeholder="Reply to the cooperative" class="w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm"></textarea>
            <p v-if="form.errors.body" class="mt-1 text-xs text-rose-700">{{ form.errors.body }}</p>
            <AppSelect
                v-model="form.status"
                class="mt-3"
                :options="[{ value: 'open', label: 'Open' }, { value: 'pending', label: 'Pending' }, { value: 'resolved', label: 'Resolved' }, { value: 'closed', label: 'Closed' }]"
            />
            <button class="mt-3 rounded-2xl bg-[#071c1b] px-5 py-2.5 text-sm text-white" :disabled="form.processing">Send reply</button>
        </form>
</template>
