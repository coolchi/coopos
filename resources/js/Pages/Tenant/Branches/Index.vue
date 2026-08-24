<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import StatCard from '../../../Components/StatCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from '../../../toast';

const props = defineProps({
    branches: Array,
    staff: Array,
    states: Array,
    can_manage: Boolean,
    can_login_as: { type: Boolean, default: false },
    summary: { type: Object, default: () => ({}) },
});
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const addOpen = ref(false);
const query = ref('');
const loginAsBranch = ref(null);
const loginAsProcessing = ref(false);
const statusBranch = ref(null);
const statusProcessing = ref(false);
const form = useForm({
    name: '', code: '', phone: '', email: '', address: '', city: '', state: props.states?.[0] || 'Lagos', admin_user_uuid: '',
});
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();
    if (! term) {
        return props.branches;
    }

    return props.branches.filter((b) => [b.name, b.code, b.city, b.state, b.admin?.name].filter(Boolean).join(' ').toLowerCase().includes(term));
});

const save = () => form.post(`${base}/branches`, {
    onSuccess: () => {
        addOpen.value = false;
        form.reset();
        form.state = props.states?.[0] || 'Lagos';
    },
});

const requestLoginAs = (branch) => {
    if (! props.can_login_as || ! branch.can_login_as) {
        return;
    }
    loginAsBranch.value = branch;
};

const confirmLoginAs = () => {
    if (! loginAsBranch.value) {
        return;
    }
    loginAsProcessing.value = true;
    router.post(`${base}/branches/${loginAsBranch.value.uuid}/login-as`, {}, {
        onSuccess: () => {
            loginAsBranch.value = null;
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'Could not log in as that officer.');
        },
        onFinish: () => {
            loginAsProcessing.value = false;
        },
    });
};

const requestStatusChange = (branch) => {
    if (! props.can_manage || branch.is_headquarters) {
        return;
    }
    statusBranch.value = branch;
};

const confirmStatusChange = () => {
    if (! statusBranch.value) {
        return;
    }
    statusProcessing.value = true;
    router.post(`${base}/branches/${statusBranch.value.uuid}/status`, {
        is_active: statusBranch.value.is_active ? 0 : 1,
    }, {
        onFinish: () => {
            statusProcessing.value = false;
            statusBranch.value = null;
        },
    });
};
</script>

<template>
    <Head title="Branches" />
        <PageHeader
            eyebrow="Network"
            title="Branches"
        >
            <template v-if="can_manage" #actions>
                <AppButton type="button" variant="gold" @click="addOpen = true">Add branch</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Branches" :value="summary.branches ?? branches.length" />
            <StatCard label="Members" :value="summary.members ?? 0" />
            <StatCard label="Staff" :value="summary.staff ?? 0" />
            <StatCard label="Payments" :value="summary.payments_total || '₦0'" />
        </div>

        <form class="mt-6" @submit.prevent>
            <input v-model="query" type="search" placeholder="Search branch, code, city, or admin" class="w-full rounded-2xl border-0 bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-black/5">
        </form>

        <AppCard class="mt-4" :padded="false">
            <EmptyState v-if="!filtered.length" icon="branches" title="No branches match" body="Try another search, or add a location." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Branch</th>
                        <th class="px-5 py-3">Admin</th>
                        <th class="px-5 py-3">Staff</th>
                        <th class="px-5 py-3">Members</th>
                        <th class="px-5 py-3">Payments</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in filtered" :key="b.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">
                            <Link :href="`${base}/branches/${b.uuid}`" class="font-medium text-teal-800">{{ b.name }}</Link>
                            <div class="text-xs text-slate-500">{{ b.code }} · {{ [b.city, b.state].filter(Boolean).join(', ') || '—' }}</div>
                            <div v-if="b.phone" class="text-xs text-slate-400">{{ b.phone }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ b.admin?.name || 'Unassigned' }}</div>
                            <div v-if="b.admin?.email" class="text-xs text-slate-500">{{ b.admin.email }}</div>
                        </td>
                        <td class="px-5 py-4">{{ b.staff_count ?? 0 }}</td>
                        <td class="px-5 py-4">{{ b.members_count }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ b.payments_total }}</td>
                        <td class="px-5 py-4">
                            <StatusBadge v-if="b.is_headquarters" value="central branch" />
                            <StatusBadge v-else :value="b.is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <button
                                v-if="can_manage && !b.is_headquarters"
                                type="button"
                                class="font-medium"
                                :class="b.is_active ? 'text-rose-700 hover:text-rose-800' : 'text-teal-800 hover:text-teal-900'"
                                @click="requestStatusChange(b)"
                            >{{ b.is_active ? 'Deactivate' : 'Activate' }}</button>
                            <button
                                v-if="can_login_as && b.can_login_as"
                                type="button"
                                class="ml-3 font-medium text-[#071c1b] hover:text-teal-800"
                                @click="requestLoginAs(b)"
                            >Login as</button>
                            <span
                                v-else-if="can_login_as"
                                class="ml-3 text-slate-400"
                                title="Assign a branch admin first"
                            >Login as</span>
                            <Link :href="`${base}/branches/${b.uuid}`" class="ml-3 text-teal-800">Open</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <Modal :show="addOpen" title="Add branch" subtitle="Open a location and optionally assign a branch admin." wide @close="addOpen = false">
            <form class="space-y-5" @submit.prevent="save">
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Branch</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <FormField label="Name" :error="form.errors.name"><input v-model="form.name" :class="input" required></FormField>
                        <FormField label="Code" :error="form.errors.code"><input v-model="form.code" :class="input" required placeholder="IKEJA"></FormField>
                        <FormField label="City"><input v-model="form.city" :class="input"></FormField>
                        <FormField label="State">
                            <AppSelect v-model="form.state" :options="states" variant="outline" />
                        </FormField>
                        <FormField label="Phone"><input v-model="form.phone" :class="input"></FormField>
                        <FormField label="Email"><input v-model="form.email" type="email" :class="input"></FormField>
                        <FormField label="Address" class="sm:col-span-2"><input v-model="form.address" :class="input"></FormField>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Admin</h3>
                    <div class="mt-3">
                        <FormField label="Branch admin" :error="form.errors.admin_user_uuid">
                            <AppSelect v-model="form.admin_user_uuid" placeholder="Assign later" :options="staff" option-value="uuid" option-label="name" variant="outline" />
                        </FormField>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AppButton type="button" variant="secondary" @click="addOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing">Save branch</AppButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(loginAsBranch)"
            title="Login as branch admin"
            :message="loginAsBranch ? `You will see ${loginAsBranch.name} the way ${loginAsBranch.admin?.name || 'the branch admin'} does — only that branch’s members, payments, and work.` : ''"
            confirm-label="Login as"
            :processing="loginAsProcessing"
            @close="loginAsBranch = null"
            @confirm="confirmLoginAs"
        />
        <ConfirmModal
            :show="Boolean(statusBranch)"
            :title="statusBranch?.is_active ? 'Deactivate branch' : 'Activate branch'"
            :message="statusBranch
                ? (statusBranch.is_active
                    ? `${statusBranch.name} will close for new members and payments. Existing records stay in place.`
                    : `${statusBranch.name} will open again for members, staff, and payments.`)
                : ''"
            :confirm-label="statusBranch?.is_active ? 'Deactivate' : 'Activate'"
            :variant="statusBranch?.is_active ? 'danger' : 'gold'"
            :processing="statusProcessing"
            @close="statusBranch = null"
            @confirm="confirmStatusChange"
        />
</template>
