<script setup>
import Pagination from '../../Components/Pagination.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ logs: Object, filters: Object });
const q = ref(props.filters?.q || '');
</script>

<template>
            <Head title="Audit log" />
        <h1 class="text-3xl font-semibold tracking-tight">Audit log</h1>
        <p class="mt-2 text-slate-500">Immutable trail of platform and cooperative changes.</p>
        <form class="mt-6" @submit.prevent="router.get('/platform/audit', { q: q || undefined }, { preserveState: true, replace: true })">
            <input v-model="q" type="search" placeholder="Search event or description" class="w-full max-w-xl rounded-2xl bg-white px-4 py-3 text-sm shadow-sm">
        </form>
        <div class="mt-4 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState v-if="!logs.data.length" icon="activity" title="No matching activity" body="Approvals, plan changes, and impersonation will appear here." />
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Actor</th>
                            <th class="px-5 py-3">Device</th>
                            <th class="px-5 py-3">Event</th>
                            <th class="px-5 py-3">Subject</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="border-t border-slate-100">
                            <td class="px-5 py-4 whitespace-nowrap">{{ log.created_at }}</td>
                            <td class="px-5 py-4">{{ log.causer }}</td>
                            <td class="px-5 py-4">
                                <div>{{ log.device || '—' }}</div>
                                <div v-if="log.ip" class="text-xs text-slate-400">{{ log.ip }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div>{{ log.description }}</div>
                                <div v-if="log.event" class="text-xs text-slate-400">{{ log.event }}</div>
                            </td>
                            <td class="px-5 py-4">{{ log.subject }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="logs.links" :from="logs.from" :to="logs.to" :total="logs.total" />
        </div>
</template>
