<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ logs: Object, filters: Object });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const q = ref(props.filters?.q || '');
</script>

<template>
    <Head title="Activity log" />
        <PageHeader eyebrow="Office" title="Activity log" subtitle="Who changed members, settings, and other records in this cooperative." />
        <SettingsNav current="activity" />
        <form class="mt-6" @submit.prevent="router.get(`${base}/settings/activity`, { q: q || undefined }, { preserveState: true, replace: true })">
            <input v-model="q" type="search" placeholder="Search event or description" class="w-full rounded-2xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-black/5 sm:max-w-xl">
        </form>
        <div class="mt-4 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState v-if="!logs.data.length" icon="activity" title="No matching activity" body="Edits to members, staff, and the cooperative profile appear here." />
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
                            <td class="whitespace-nowrap px-5 py-4">{{ log.created_at }}</td>
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
