<script setup>
import Pagination from '../../../Components/Pagination.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ tickets: Object, filters: Object });
const status = ref(props.filters?.status || '');
const priority = ref(props.filters?.priority || '');
const filter = () => router.get('/platform/tickets', { status: status.value || undefined, priority: priority.value || undefined }, { preserveState: true, replace: true });
</script>

<template>
            <Head title="Support" />
        <h1 class="text-3xl font-semibold tracking-tight">Support tickets</h1>
        <div class="mt-6 flex flex-wrap gap-3">
            <AppSelect
                v-model="status"
                class="min-w-44"
                variant="white"
                placeholder="All statuses"
                :options="[{ value: 'open', label: 'Open' }, { value: 'pending', label: 'Pending' }, { value: 'resolved', label: 'Resolved' }, { value: 'closed', label: 'Closed' }]"
                @change="filter"
            />
            <AppSelect
                v-model="priority"
                class="min-w-44"
                variant="white"
                placeholder="All priorities"
                :options="[{ value: 'high', label: 'High' }, { value: 'normal', label: 'Normal' }, { value: 'low', label: 'Low' }]"
                @change="filter"
            />
        </div>
        <div class="mt-4 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState v-if="!tickets.data.length" icon="inbox" title="No tickets in this queue" body="Open, pending, and closed tickets filter here. The inbox is clear for this view." />
            <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Subject</th>
                        <th class="px-5 py-3">Cooperative</th>
                        <th class="px-5 py-3">Priority</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Opened</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ticket in tickets.data" :key="ticket.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <Link :href="`/platform/tickets/${ticket.uuid}`" class="font-medium hover:text-teal-800">{{ ticket.subject }}</Link>
                            <div class="text-xs text-slate-500">{{ ticket.category }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <Link v-if="ticket.slug" :href="`/platform/cooperatives/${ticket.slug}`" class="hover:text-teal-800">{{ ticket.cooperative }}</Link>
                        </td>
                        <td class="px-5 py-4"><StatusBadge :value="ticket.priority" /></td>
                        <td class="px-5 py-4"><StatusBadge :value="ticket.status" /></td>
                        <td class="px-5 py-4">{{ ticket.created_at }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
            <Pagination :links="tickets.links" :from="tickets.from" :to="tickets.to" :total="tickets.total" />
        </div>
</template>
