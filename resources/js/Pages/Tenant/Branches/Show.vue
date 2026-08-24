<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import StatCard from '../../../Components/StatCard.vue';
import RecordPaymentModal from '../../../Components/RecordPaymentModal.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import Modal from '../../../Components/Modal.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from '../../../toast';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    branch: Object,
    members: Array,
    payments: Array,
    withdrawals: { type: Array, default: () => [] },
    staff_members: { type: Array, default: () => [] },
    kpis: { type: Object, default: () => ({}) },
    staff: Array,
    assignable_staff: { type: Array, default: () => [] },
    other_branches: { type: Array, default: () => [] },
    states: Array,
    can_manage: Boolean,
    can_login_as: { type: Boolean, default: false },
    products: { type: Array, default: () => [] },
    required_products: { type: Array, default: () => [] },
    member_plans: { type: Object, default: () => ({}) },
    member_paid: { type: Object, default: () => ({}) },
    member_facilities: { type: Object, default: () => ({}) },
    member_options: { type: Array, default: () => [] },
});
const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const payOpen = ref(false);
const staffOpen = ref(false);
const loginAsUser = ref(null);
const loginAsProcessing = ref(false);
const movingMember = ref(null);
const statusOpen = ref(false);
const statusProcessing = ref(false);
const staffForm = useForm({ staff_user_uuid: '' });
const moveForm = useForm({ member_uuid: '', destination_uuid: '' });
const form = useForm({
    name: props.branch.name,
    code: props.branch.code,
    phone: props.branch.phone || '',
    email: props.branch.email || '',
    address: props.branch.address || '',
    city: props.branch.city || '',
    state: props.branch.state || '',
    is_active: props.branch.is_active,
    admin_user_uuid: props.branch.admin?.uuid || '',
});
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const loginAsMessage = computed(() => {
    const name = loginAsUser.value?.name || props.branch.admin?.name;
    return name
        ? `You will see ${props.branch.name} the way ${name} does — only this branch’s members, payments, and work.`
        : '';
});

const requestLoginAs = (user = null) => {
    if (! props.can_login_as) {
        return;
    }
    loginAsUser.value = user || props.branch.admin || props.staff_members[0] || { name: 'branch staff' };
};

const confirmLoginAs = () => {
    loginAsProcessing.value = true;
    router.post(`${base}/branches/${props.branch.uuid}/login-as`, {
        user_uuid: loginAsUser.value?.uuid || undefined,
    }, {
        onSuccess: () => {
            loginAsUser.value = null;
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'Could not log in as that officer.');
        },
        onFinish: () => {
            loginAsProcessing.value = false;
        },
    });
};

const assignStaff = () => staffForm.post(`${base}/branches/${props.branch.uuid}/staff`, {
    onSuccess: () => {
        staffOpen.value = false;
        staffForm.reset();
    },
});

const removeStaff = (user) => {
    router.post(`${base}/branches/${props.branch.uuid}/staff/${user.uuid}/remove`);
};

const openMove = (member) => {
    movingMember.value = member;
    moveForm.member_uuid = member.uuid;
    moveForm.destination_uuid = props.other_branches[0]?.uuid || '';
};

const transferMember = () => moveForm.post(`${base}/branches/${props.branch.uuid}/transfer-member`, {
    onSuccess: () => {
        movingMember.value = null;
        moveForm.reset();
    },
});

const requestStatusChange = () => {
    if (! props.can_manage || props.branch.is_headquarters) {
        return;
    }
    statusOpen.value = true;
};

const confirmStatusChange = () => {
    statusProcessing.value = true;
    router.post(`${base}/branches/${props.branch.uuid}/status`, {
        is_active: props.branch.is_active ? 0 : 1,
    }, {
        onFinish: () => {
            statusProcessing.value = false;
            statusOpen.value = false;
        },
    });
};
</script>

<template>
    <Head :title="branch.name" />
        <Link :href="`${base}/branches`" class="text-sm text-teal-800">Back to branches</Link>
        <PageHeader
            class="mt-3"
            eyebrow="Network"
            :title="branch.name"
            :subtitle="`${branch.code} · ${[branch.city, branch.state].filter(Boolean).join(', ') || 'Location not set'}`"
        >
            <template #actions>
                <StatusBadge :value="branch.is_headquarters ? 'central branch' : (branch.is_active ? 'active' : 'inactive')" />
                <AppButton
                    v-if="can_manage && !branch.is_headquarters"
                    type="button"
                    :variant="branch.is_active ? 'danger' : 'gold'"
                    @click="requestStatusChange"
                >{{ branch.is_active ? 'Deactivate' : 'Activate' }}</AppButton>
                <AppButton v-if="can('members.create')" :href="`${base}/members/create?branch=${branch.uuid}`" variant="secondary">Add member</AppButton>
                <AppButton :href="`${base}/members?branch=${branch.uuid}`" variant="secondary">View members</AppButton>
                <AppButton v-if="can_login_as && (branch.admin || staff_members.length)" type="button" variant="secondary" @click="requestLoginAs()">Login as</AppButton>
                <AppButton v-if="page.props.allow_manual_payments && can('contributions.record')" type="button" variant="gold" @click="payOpen = true">Record payment</AppButton>
            </template>
        </PageHeader>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Members" :value="kpis.members ?? 0" :hint="`${kpis.active_members ?? 0} active`" />
            <StatCard label="Savings" :value="kpis.payments_total || branch.payments_total" />
            <StatCard label="Staff" :value="kpis.staff ?? staff_members.length" />
            <StatCard label="Pending withdrawals" :value="kpis.pending_withdrawals ?? 0" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <AppCard title="Contact">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Admin</dt>
                        <dd class="mt-1 font-medium">{{ branch.admin?.name || 'Unassigned' }}</dd>
                        <dd class="text-slate-500">{{ branch.admin?.email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-1 font-medium">{{ branch.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 font-medium">{{ branch.email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-1 font-medium">{{ branch.address || '—' }}</dd>
                    </div>
                </dl>
            </AppCard>

            <form v-if="can_manage" @submit.prevent="form.post(`${base}/branches/${branch.uuid}`)">
                <AppCard title="Branch details">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Name" :error="form.errors.name"><input v-model="form.name" :class="input" required></FormField>
                        <FormField label="Code" :error="form.errors.code"><input v-model="form.code" :class="input" required></FormField>
                        <FormField label="City"><input v-model="form.city" :class="input"></FormField>
                        <FormField label="State">
                            <AppSelect v-model="form.state" :options="states" variant="outline" />
                        </FormField>
                        <FormField label="Phone"><input v-model="form.phone" :class="input"></FormField>
                        <FormField label="Email"><input v-model="form.email" type="email" :class="input"></FormField>
                        <FormField label="Address" class="sm:col-span-2"><input v-model="form.address" :class="input"></FormField>
                        <FormField label="Branch admin" class="sm:col-span-2" :error="form.errors.admin_user_uuid">
                            <AppSelect v-model="form.admin_user_uuid" placeholder="Unassigned" :options="staff" option-value="uuid" option-label="name" variant="outline" />
                        </FormField>
                        <p v-if="branch.is_headquarters" class="text-sm text-slate-500 sm:col-span-2">The central branch stays active.</p>
                        <p v-else class="text-sm text-slate-500 sm:col-span-2">
                            This branch is {{ branch.is_active ? 'open' : 'closed' }}. Use Activate or Deactivate in the header to change that.
                        </p>
                    </div>
                    <div class="mt-4"><AppButton type="submit" :disabled="form.processing">Save branch</AppButton></div>
                </AppCard>
            </form>
        </div>

        <AppCard class="mt-6" title="Staff at this branch" :padded="false">
            <template v-if="can_manage" #header>
                <button type="button" class="text-sm text-teal-800" @click="staffOpen = true">Assign staff</button>
            </template>
            <EmptyState v-if="!staff_members.length" icon="staff" title="No staff assigned" body="Assign a branch admin above, or add an officer to this location." />
            <table v-else class="w-full text-sm">
                <tbody>
                    <tr v-for="u in staff_members" :key="u.uuid" class="border-t border-slate-100 first:border-0">
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ u.name }}</div>
                            <div class="text-xs text-slate-500">{{ u.email }}</div>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500">
                            <span v-if="u.is_admin || branch.admin?.uuid === u.uuid">Branch admin</span>
                            <span v-else>{{ (u.roles || []).join(', ') || 'Staff' }}</span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-sm">
                            <button
                                v-if="can_login_as"
                                type="button"
                                class="font-medium text-[#071c1b] hover:text-teal-800"
                                @click="requestLoginAs(u)"
                            >Login as</button>
                            <button
                                v-if="can_manage"
                                type="button"
                                class="ml-3 text-rose-700 hover:text-rose-800"
                                @click="removeStaff(u)"
                            >Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <AppCard title="Members" :padded="false">
                <template #header>
                    <Link :href="`${base}/members?branch=${branch.uuid}`" class="text-sm text-teal-800">View all</Link>
                </template>
                <EmptyState v-if="!members.length" icon="members" title="No members in this branch" body="Assign members to this location when you register or edit their profile." />
                <table v-else class="w-full text-sm">
                    <tbody>
                        <tr v-for="m in members" :key="m.uuid" class="border-t border-slate-100 first:border-0">
                            <td class="px-5 py-3">
                                <Link :href="`${base}/members/${m.uuid}`" class="font-medium text-teal-800">{{ m.name }}</Link>
                                <div class="text-xs text-slate-500">{{ m.membership_number }} · {{ m.phone || 'No phone' }}</div>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button
                                    v-if="can_manage && other_branches.length"
                                    type="button"
                                    class="mr-3 text-sm text-teal-800"
                                    @click="openMove(m)"
                                >Move</button>
                                <StatusBadge :value="m.status" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
            <AppCard title="Recent payments" :padded="false">
                <template #header>
                    <Link :href="`${base}/contributions`" class="text-sm text-teal-800">Savings</Link>
                </template>
                <EmptyState v-if="!payments.length" icon="savings" title="No payments recorded" body="Savings posted for members of this branch will land here." />
                <table v-else class="w-full text-sm">
                    <tbody>
                        <tr v-for="p in payments" :key="p.uuid" class="border-t border-slate-100 first:border-0">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ p.member }}</div>
                                <div class="text-xs text-slate-500">{{ p.product }} · {{ p.channel }} · {{ p.paid_on }}</div>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ p.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
        </div>

        <AppCard class="mt-6" title="Withdrawals" :padded="false">
            <template #header>
                <Link :href="`${base}/withdrawals`" class="text-sm text-teal-800">View all</Link>
            </template>
            <EmptyState v-if="!withdrawals.length" icon="withdrawals" title="No withdrawal requests" body="Payout requests from this branch wait here until an officer approves them." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Member</th>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="w in withdrawals" :key="w.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4">{{ w.member }}</td>
                        <td class="px-5 py-4">{{ w.product || '—' }}</td>
                        <td class="px-5 py-4 tabular-nums">{{ w.amount }}</td>
                        <td class="px-5 py-4"><StatusBadge :value="String(w.status)" /></td>
                    </tr>
                </tbody>
            </table>
        </AppCard>

        <RecordPaymentModal
            :show="payOpen"
            :members="member_options"
            :products="products"
            :required_products="required_products"
            :member_plans="member_plans"
            :member_paid="member_paid"
            :member_facilities="member_facilities"
            @close="payOpen = false"
        />

        <Modal :show="staffOpen" title="Assign staff" subtitle="Move an existing officer onto this branch." @close="staffOpen = false">
            <form class="space-y-4" @submit.prevent="assignStaff">
                <FormField label="Officer" :error="staffForm.errors.staff_user_uuid">
                    <AppSelect
                        v-model="staffForm.staff_user_uuid"
                        placeholder="Select staff"
                        :options="assignable_staff"
                        option-value="uuid"
                        option-label="name"
                        variant="outline"
                    />
                </FormField>
                <p v-if="!assignable_staff.length" class="text-sm text-slate-500">Invite staff from Settings → Users, then assign them here.</p>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="staffOpen = false">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="staffForm.processing || !staffForm.staff_user_uuid">Assign</AppButton>
                </div>
            </form>
        </Modal>

        <Modal :show="Boolean(movingMember)" title="Move member" :subtitle="movingMember ? `Send ${movingMember.name} to another branch.` : ''" @close="movingMember = null">
            <form class="space-y-4" @submit.prevent="transferMember">
                <FormField label="Destination" :error="moveForm.errors.destination_uuid">
                    <AppSelect
                        v-model="moveForm.destination_uuid"
                        :options="other_branches"
                        option-value="uuid"
                        option-label="name"
                        variant="outline"
                    />
                </FormField>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="movingMember = null">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="moveForm.processing">Move member</AppButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(loginAsUser)"
            title="Login as branch staff"
            :message="loginAsMessage"
            confirm-label="Login as"
            :processing="loginAsProcessing"
            @close="loginAsUser = null"
            @confirm="confirmLoginAs"
        />
        <ConfirmModal
            :show="statusOpen"
            :title="branch.is_active ? 'Deactivate branch' : 'Activate branch'"
            :message="branch.is_active
                ? `${branch.name} will close for new members and payments. Existing records stay in place.`
                : `${branch.name} will open again for members, staff, and payments.`"
            :confirm-label="branch.is_active ? 'Deactivate' : 'Activate'"
            :variant="branch.is_active ? 'danger' : 'gold'"
            :processing="statusProcessing"
            @close="statusOpen = false"
            @confirm="confirmStatusChange"
        />
</template>
