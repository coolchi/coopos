<script setup>
import Pagination from '../../../Components/Pagination.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tenants: Object,
    filters: Object,
    counts: Object,
    allow_impersonation: { type: Boolean, default: false },
});

const q = ref(props.filters?.q || '');
const status = ref(props.filters?.status || '');
const mode = ref(props.filters?.mode || '');

const search = () => router.get('/platform/cooperatives', { q: q.value || undefined, status: status.value || undefined, mode: mode.value || undefined }, { preserveState: true, replace: true });

const loginAsTenant = ref(null);
const loginAsProcessing = ref(false);
const loginAsMessage = computed(() => (
    loginAsTenant.value
        ? `You will sign in as the administrator of ${loginAsTenant.value.name} and leave the platform workspace.`
        : ''
));

const requestLoginAs = (tenant) => {
    if (! props.allow_impersonation || tenant.status !== 'active') {
        return;
    }
    loginAsTenant.value = tenant;
};

const confirmLoginAs = () => {
    if (! loginAsTenant.value) {
        return;
    }
    loginAsProcessing.value = true;
    router.post(`/platform/cooperatives/${loginAsTenant.value.slug}/impersonate`, {}, {
        onFinish: () => {
            loginAsProcessing.value = false;
        },
    });
};
</script>

<template>
            <Head title="Cooperatives" />
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">Tenants</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Cooperatives</h1>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <button class="rounded-full px-3 py-1" :class="!status ? 'bg-[#071c1b] text-white' : 'bg-white'" @click="status = ''; search()">All {{ counts.all }}</button>
                <button class="rounded-full px-3 py-1" :class="status === 'pending' ? 'bg-amber-600 text-white' : 'bg-white'" @click="status = 'pending'; search()">Pending {{ counts.pending }}</button>
                <button class="rounded-full px-3 py-1" :class="status === 'active' ? 'bg-teal-700 text-white' : 'bg-white'" @click="status = 'active'; search()">Active {{ counts.active }}</button>
                <button class="rounded-full px-3 py-1" :class="status === 'suspended' ? 'bg-rose-700 text-white' : 'bg-white'" @click="status = 'suspended'; search()">Suspended {{ counts.suspended }}</button>
            </div>
        </div>

        <form class="mt-6 grid gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-black/5 md:grid-cols-4" @submit.prevent="search">
            <input v-model="q" type="search" placeholder="Search name, slug, state" class="rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm">
            <AppSelect
                v-model="status"
                class="min-w-44"
                variant="white"
                placeholder="All statuses"
                :options="[{ value: 'active', label: 'Active' }, { value: 'pending', label: 'Pending' }, { value: 'suspended', label: 'Suspended' }, { value: 'inactive', label: 'Inactive' }]"
                @change="search"
            />
            <AppSelect
                v-model="mode"
                class="min-w-44"
                variant="white"
                placeholder="All modes"
                :options="[{ value: 'general', label: 'General' }, { value: 'islamic', label: 'Islamic' }, { value: 'hybrid', label: 'Hybrid' }]"
                @change="search"
            />
            <button class="rounded-2xl bg-[#071c1b] px-4 py-3 text-sm font-medium text-white">Filter</button>
        </form>

        <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState v-if="!tenants.data.length" icon="search" title="No cooperatives match" body="Try another status, mode, or search term." />
            <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Cooperative</th>
                        <th class="px-5 py-3">Mode</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Members</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tenants.data" :key="t.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <Link :href="`/platform/cooperatives/${t.slug}`" class="font-medium hover:text-teal-800">{{ t.name }}</Link>
                            <div class="text-xs text-slate-500">{{ t.host }} · {{ t.state || '—' }}</div>
                        </td>
                        <td class="px-5 py-4">{{ t.mode }}</td>
                        <td class="px-5 py-4">{{ t.plan || '—' }} <span class="text-slate-400">{{ t.billing }}</span></td>
                        <td class="px-5 py-4">{{ t.members_count }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="t.status" /></td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <button
                                v-if="allow_impersonation && t.status === 'active'"
                                type="button"
                                class="text-[#071c1b] font-medium hover:text-teal-800"
                                @click="requestLoginAs(t)"
                            >Login as</button>
                            <Link :href="`/platform/cooperatives/${t.slug}`" class="ml-3 text-teal-800">Open</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <Pagination :links="tenants.links" :from="tenants.from" :to="tenants.to" :total="tenants.total" />
        </div>

        <ConfirmModal
            :show="Boolean(loginAsTenant)"
            title="Login as administrator"
            :message="loginAsMessage"
            confirm-label="Login as"
            :processing="loginAsProcessing"
            @close="loginAsTenant = null"
            @confirm="confirmLoginAs"
        />
</template>
