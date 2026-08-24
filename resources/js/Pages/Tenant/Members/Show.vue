<script setup>
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import StatCard from '../../../Components/StatCard.vue';
import RecordPaymentModal from '../../../Components/RecordPaymentModal.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    member: Object,
    states: Array,
    branches: Array,
    locked_branch: Boolean,
    products: { type: Array, default: () => [] },
    required_products: { type: Array, default: () => [] },
    member_plans: { type: Object, default: () => ({}) },
    member_paid: { type: Object, default: () => ({}) },
    member_facilities: { type: Object, default: () => ({}) },
    kpis: { type: Object, default: () => ({}) },
    introducers: { type: Array, default: () => [] },
    monipay_configured: { type: Boolean, default: false },
});
const payOpen = ref(false);
const page = usePage();
const { can } = useCan();
const tenant = computed(() => page.props.tenant);
const base = computed(() => page.props.tenantBase ?? `/${tenant.value?.slug || ''}`);
const form = useForm({
    first_name: props.member.first_name,
    middle_name: props.member.middle_name || '',
    last_name: props.member.last_name,
    gender: props.member.gender || '',
    date_of_birth: props.member.date_of_birth || '',
    email: props.member.email || '',
    phone: props.member.phone || '',
    occupation: props.member.occupation || '',
    employer: props.member.employer || '',
    address: props.member.address || '',
    state: props.member.state || '',
    lga: props.member.lga || '',
    next_of_kin_name: props.member.next_of_kin_name || '',
    next_of_kin_relationship: props.member.next_of_kin_relationship || '',
    next_of_kin_phone: props.member.next_of_kin_phone || '',
    next_of_kin_address: props.member.next_of_kin_address || '',
    branch_uuid: props.member.branch_uuid || '',
    introducer_uuid: props.member.introducer_uuid || '',
    nin: props.member.nin || '',
});
const approve = useForm({});
const issueVa = useForm({});
const guarantorForm = useForm({ guarantor_uuid: '' });
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const initials = computed(() => {
    const parts = (props.member.name || '').trim().split(/\s+/).filter(Boolean);

    return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'M';
});

const hasFinancing = computed(() => Boolean(
    props.member.loans?.length || props.member.qard?.length || props.member.murabaha?.length,
));

const dash = (value) => value || '—';
const introducerOptions = computed(() => (props.introducers || []).map((m) => ({
    uuid: m.uuid,
    name: `${m.first_name} ${m.last_name} (${m.membership_number})`,
})));

const assignedGuarantorUuids = computed(() => (props.member.guarantors || []).map((g) => g.member_uuid));

const guarantorOptions = computed(() => introducerOptions.value.filter(
    (m) => ! assignedGuarantorUuids.value.includes(m.uuid),
));

const addGuarantor = () => {
    if (! guarantorForm.guarantor_uuid) {
        return;
    }
    guarantorForm.post(`${base.value}/members/${props.member.uuid}/guarantors`, {
        preserveScroll: true,
        onSuccess: () => guarantorForm.reset(),
    });
};

const removeGuarantor = (uuid) => {
    router.post(`${base.value}/members/${props.member.uuid}/guarantors/${uuid}/remove`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="member.name" />
        <Link :href="`${base}/members`" class="text-sm font-medium text-teal-800">Back to members</Link>

        <div class="mt-4 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[#071c1b] text-xl font-semibold text-[#d4b06a]">
                        {{ initials }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ member.name }}</h1>
                            <StatusBadge :value="member.status" />
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ member.membership_number }}
                            <span class="text-slate-300">·</span>
                            {{ member.branch || 'No branch' }}
                            <span class="text-slate-300">·</span>
                            Joined {{ member.joined_on || '—' }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm">
                            <a v-if="member.phone" :href="`tel:${member.phone}`" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-slate-200">{{ member.phone }}</a>
                            <a v-if="member.email" :href="`mailto:${member.email}`" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-slate-200">{{ member.email }}</a>
                            <span v-if="member.occupation" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ member.occupation }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                    <AppButton :href="`${base}/reports/statement?member_uuid=${member.uuid}`" variant="secondary">Statement</AppButton>
                    <AppButton v-if="page.props.allow_manual_payments && can('contributions.record')" type="button" variant="secondary" @click="payOpen = true">Record payment</AppButton>
                    <AppButton v-if="member.status === 'pending' && can('members.approve')" type="button" variant="gold" @click="approve.post(`${base}/members/${member.uuid}/approve`)">Activate</AppButton>
                </div>
            </div>

            <div class="flex flex-col gap-4 border-t border-slate-100 bg-[#071c1b] px-5 py-4 text-white sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#d4b06a]">Virtual account</p>
                    <template v-if="member.virtual_account?.account_number">
                        <p class="mt-1 text-2xl font-semibold tabular-nums tracking-wide">{{ member.virtual_account.account_number }}</p>
                        <p class="mt-1 text-sm text-white/70">
                            {{ member.virtual_account.bank_name || 'Monipay' }}
                            <span class="text-white/40">·</span>
                            {{ member.virtual_account.account_name }}
                        </p>
                    </template>
                    <p v-else class="mt-1 text-sm text-white/70">
                        {{ monipay_configured
                            ? 'Issue a Monipay NUBAN so this member can pay by transfer.'
                            : 'Add a Monipay secret key in Settings to issue virtual accounts.' }}
                    </p>
                </div>
                <AppButton
                    v-if="monipay_configured && can('members.update')"
                    type="button"
                    variant="gold"
                    class="shrink-0"
                    :disabled="issueVa.processing"
                    @click="issueVa.post(`${base}/members/${member.uuid}/virtual-account`)"
                >
                    {{ member.virtual_account?.account_number ? 'Refresh' : 'Issue account' }}
                </AppButton>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Savings" :value="kpis.savings || '₦0'" />
            <StatCard label="Contributions" :value="kpis.contributions_total || '₦0'" hint="Lifetime recorded" />
            <StatCard label="Outstanding" :value="kpis.outstanding || '₦0'" hint="Loans" />
            <StatCard label="Savings plans" :value="kpis.plans ?? 0" hint="Active mandates" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,1fr)]">
            <div class="space-y-6">
            <form @submit.prevent="form.post(`${base}/members/${member.uuid}`)">
                <AppCard title="Profile">
                    <fieldset :disabled="!can('members.update')" class="min-w-0 border-0 p-0">
                    <div class="space-y-6">
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Identity</h3>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <FormField label="First name" :error="form.errors.first_name"><input v-model="form.first_name" :class="input" required></FormField>
                                <FormField label="Last name" :error="form.errors.last_name"><input v-model="form.last_name" :class="input" required></FormField>
                                <FormField label="Middle name"><input v-model="form.middle_name" :class="input"></FormField>
                                <FormField label="Gender">
                                    <AppSelect v-model="form.gender" placeholder="Select" :options="[{ value: 'female', label: 'Female' }, { value: 'male', label: 'Male' }]" variant="outline" />
                                </FormField>
                                <FormField label="Date of birth"><input v-model="form.date_of_birth" type="date" :class="input"></FormField>
                                <FormField label="Branch">
                                    <AppSelect v-model="form.branch_uuid" :options="branches" option-value="uuid" option-label="name" variant="outline" :disabled="locked_branch" />
                                </FormField>
                                <FormField label="Introducer">
                                    <AppSelect v-model="form.introducer_uuid" :options="introducerOptions" option-value="uuid" option-label="name" placeholder="None" variant="outline" />
                                </FormField>
                                <FormField label="NIN" :error="form.errors.nin">
                                    <input v-model="form.nin" maxlength="11" inputmode="numeric" autocomplete="off" placeholder="11 digits" :class="input">
                                </FormField>
                            </div>
                        </section>
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Contact & work</h3>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <FormField label="Email" :error="form.errors.email"><input v-model="form.email" type="email" :class="input"></FormField>
                                <FormField label="Phone"><input v-model="form.phone" :class="input"></FormField>
                                <FormField label="Occupation"><input v-model="form.occupation" :class="input"></FormField>
                                <FormField label="Employer"><input v-model="form.employer" :class="input"></FormField>
                            </div>
                        </section>
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Address</h3>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <FormField label="Address" class="sm:col-span-2"><input v-model="form.address" :class="input"></FormField>
                                <FormField label="State">
                                    <AppSelect v-model="form.state" :options="states" variant="outline" />
                                </FormField>
                                <FormField label="LGA"><input v-model="form.lga" :class="input"></FormField>
                            </div>
                        </section>
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Next of kin</h3>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <FormField label="Name"><input v-model="form.next_of_kin_name" :class="input"></FormField>
                                <FormField label="Relationship"><input v-model="form.next_of_kin_relationship" :class="input"></FormField>
                                <FormField label="Phone"><input v-model="form.next_of_kin_phone" :class="input"></FormField>
                                <FormField label="Address"><input v-model="form.next_of_kin_address" :class="input"></FormField>
                            </div>
                        </section>
                    </div>
                    <div v-if="can('members.update')" class="mt-6 flex justify-end">
                        <AppButton type="submit" variant="gold" :disabled="form.processing">Save changes</AppButton>
                    </div>
                    </fieldset>
                </AppCard>
            </form>
            </div>

            <div class="space-y-6">
                <AppCard title="At a glance">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Membership no.</dt>
                            <dd class="font-medium text-slate-900">{{ member.membership_number }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">NIN</dt>
                            <dd class="font-medium text-slate-900">{{ dash(member.nin) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Branch</dt>
                            <dd class="font-medium text-slate-900">{{ dash(member.branch) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Gender</dt>
                            <dd class="font-medium capitalize text-slate-900">{{ dash(member.gender) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Date of birth</dt>
                            <dd class="font-medium text-slate-900">{{ dash(member.date_of_birth) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Employer</dt>
                            <dd class="text-right font-medium text-slate-900">{{ dash(member.employer) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Guarantor</dt>
                            <dd class="text-right font-medium text-slate-900">
                                {{ member.guarantors?.length ? member.guarantors.map((g) => g.name).join(', ') : '—' }}
                            </dd>
                        </div>
                    </dl>
                </AppCard>

                <AppCard title="Mandates" :padded="false">
                    <template #header>
                        <Link :href="`${base}/contributions/enrollments`" class="text-sm text-teal-800">Manage</Link>
                    </template>
                    <EmptyState v-if="!member.enrollments?.length" icon="savings" title="No mandates" body="Add a mandate on a savings product from Mandates." />
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="plan in member.enrollments" :key="plan.uuid" class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <div class="font-medium">{{ plan.product || 'Savings plan' }}</div>
                                <div class="text-xs text-slate-500">From {{ plan.starts_on || '—' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="tabular-nums">{{ plan.amount }}</div>
                                <StatusBadge :value="plan.status" />
                            </div>
                        </li>
                    </ul>
                </AppCard>

                <AppCard title="Guarantors" description="Must be an existing member.">
                    <p v-if="!member.guarantors?.length" class="text-sm text-slate-500">No guarantor yet.</p>
                    <ul v-else class="divide-y divide-slate-100 rounded-2xl ring-1 ring-slate-100">
                        <li
                            v-for="row in member.guarantors"
                            :key="row.uuid"
                            class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm"
                        >
                            <div class="min-w-0">
                                <Link :href="`${base}/members/${row.member_uuid}`" class="font-medium text-teal-800 hover:underline">{{ row.name }}</Link>
                                <div class="text-xs text-slate-500">{{ row.membership_number }}</div>
                            </div>
                            <button
                                v-if="can('members.update')"
                                type="button"
                                class="text-xs font-medium text-slate-500 hover:text-rose-700"
                                :disabled="guarantorForm.processing"
                                @click="removeGuarantor(row.uuid)"
                            >Remove</button>
                        </li>
                    </ul>
                    <form v-if="can('members.update')" class="mt-3 space-y-2" @submit.prevent="addGuarantor">
                        <FormField label="Add guarantor" :error="guarantorForm.errors.guarantor_uuid">
                            <AppSelect
                                v-model="guarantorForm.guarantor_uuid"
                                :options="guarantorOptions"
                                option-value="uuid"
                                option-label="name"
                                placeholder="Choose a member"
                                variant="outline"
                            />
                        </FormField>
                        <AppButton type="submit" variant="secondary" class="w-full" :disabled="guarantorForm.processing || !guarantorForm.guarantor_uuid">
                            Add
                        </AppButton>
                    </form>
                </AppCard>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <AppCard title="Recent contributions" :padded="false">
                <template #header>
                    <Link :href="`${base}/contributions`" class="text-sm text-teal-800">View all</Link>
                </template>
                <EmptyState v-if="!member.contributions?.data?.length" icon="savings" title="No contributions yet" body="Record a savings payment to start this member’s ledger." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in member.contributions.data" :key="c.uuid" class="border-t border-slate-100">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ c.product || 'Contribution' }}</div>
                                <div class="text-xs capitalize text-slate-500">{{ c.channel || 'manual' }}</div>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ c.amount }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ c.paid_on }}</td>
                        </tr>
                    </tbody>
                </table>
                <template v-if="member.contributions?.total" #footer>
                    <Pagination
                        :links="member.contributions.links"
                        :from="member.contributions.from"
                        :to="member.contributions.to"
                        :total="member.contributions.total"
                    />
                </template>
            </AppCard>

            <AppCard title="Financing" :padded="false">
                <template #header>
                    <Link v-if="tenant?.offers_conventional" :href="`${base}/loans`" class="text-sm text-teal-800">Loans</Link>
                    <Link v-else-if="tenant?.offers_islamic" :href="`${base}/loan`" class="text-sm text-teal-800">Loans</Link>
                </template>
                <EmptyState v-if="!hasFinancing" icon="loans" title="No loans yet" body="Loans will appear here once this member is funded." />
                <table v-else class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Facility</th>
                            <th class="px-5 py-3">Outstanding</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in member.loans" :key="l.uuid" class="border-t border-slate-100">
                            <td class="px-5 py-3">
                                <Link :href="`${base}/loans/${l.uuid}`" class="font-medium text-teal-800">{{ l.account || 'Loan' }}</Link>
                                <div class="text-xs text-slate-500">Principal {{ l.principal }}</div>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ l.outstanding }}</td>
                            <td class="px-5 py-3"><StatusBadge :value="l.status" /></td>
                        </tr>
                        <tr v-for="f in member.qard" :key="f.uuid" class="border-t border-slate-100">
                            <td class="px-5 py-3">
                                <div class="font-medium">Qard Hasan</div>
                                <div class="text-xs text-slate-500">Principal {{ f.principal }}</div>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ f.outstanding }}</td>
                            <td class="px-5 py-3"><StatusBadge :value="f.status" /></td>
                        </tr>
                        <tr v-for="f in member.murabaha" :key="'m'+f.uuid" class="border-t border-slate-100">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ f.asset || 'Murabaha' }}</div>
                                <div class="text-xs text-slate-500">Sale {{ f.sale_price }}</div>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ f.outstanding }}</td>
                            <td class="px-5 py-3"><StatusBadge :value="f.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>
        </div>

        <RecordPaymentModal
            :show="payOpen"
            :products="products"
            :required_products="required_products"
            :member_plans="member_plans"
            :member_paid="member_paid"
            :member_facilities="member_facilities"
            :member-uuid="member.uuid"
            :member-name="member.name"
            @close="payOpen = false"
        />
</template>
