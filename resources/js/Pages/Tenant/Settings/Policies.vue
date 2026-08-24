<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object,
    contribution_products: { type: Array, default: () => [] },
});
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const form = useForm({
    require_membership_approval: Boolean(props.settings.require_membership_approval),
    require_verified_bank_for_payout: Boolean(props.settings.require_verified_bank_for_payout),
    guarantor_exposure_cap: (props.settings.guarantor_exposure_cap_kobo || 0) / 100,
    max_active_loans: props.settings.max_active_loans ?? 1,
    max_debt_to_savings_percent: (props.settings.max_debt_to_savings_bps || 20000) / 100,
    loan_min_savings_percent: props.settings.loan_min_savings_percent ?? 50,
    loan_min_shares_percent: props.settings.loan_min_shares_percent ?? 10,
    loan_required_product_uuids: [...(props.settings.loan_required_product_uuids || [])],
    loan_check_attendance: props.settings.loan_check_attendance !== false,
    loan_max_absences_per_year: props.settings.loan_max_absences_per_year ?? 3,
    email_notifications: Boolean(props.settings.email_notifications),
    sms_notifications: Boolean(props.settings.sms_notifications),
});
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
</script>

<template>
    <Head title="Policies" />
        <PageHeader eyebrow="Office" title="Operating policies" subtitle="Membership gates, loan conditions, credit limits, and how members are notified." />
        <SettingsNav current="policies" />
        <form class="mt-6 space-y-6" @submit.prevent="form.post(`${base}/settings/policies`)">
            <AppCard title="Membership">
                <label class="flex items-start gap-3 text-sm">
                    <input v-model="form.require_membership_approval" type="checkbox" class="mt-1 rounded">
                    <span>
                        <span class="font-medium">Approve new members before they can transact</span>
                        <span class="mt-1 block text-slate-500">Pending members cannot save or take financing until an officer activates them.</span>
                    </span>
                </label>
                <label class="mt-4 flex items-start gap-3 text-sm">
                    <input v-model="form.require_verified_bank_for_payout" type="checkbox" class="mt-1 rounded">
                    <span>
                        <span class="font-medium">Require a verified bank account for payouts</span>
                        <span class="mt-1 block text-slate-500">Withdrawals and disbursements wait until the member bank account is verified.</span>
                    </span>
                </label>
            </AppCard>
            <AppCard title="Credit limits">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Guarantor exposure cap (₦)" hint="Maximum a member can guarantee for others.">
                        <AmountInput v-model="form.guarantor_exposure_cap" :class="input" />
                    </FormField>
                    <FormField label="Max active facilities" hint="How many live loans or Islamic facilities a member may hold.">
                        <input v-model="form.max_active_loans" type="number" min="0" :class="input">
                    </FormField>
                    <FormField label="Max debt vs savings (%)" hint="200 means outstanding financing cannot exceed twice savings.">
                        <input v-model="form.max_debt_to_savings_percent" type="number" min="0" :class="input">
                    </FormField>
                </div>
            </AppCard>
            <AppCard title="Loan eligibility">
                <p class="mb-4 text-sm text-slate-500">These checks run when a member applies. Set a percentage to 0 to turn that balance check off.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Minimum savings (% of loan)" hint="Member savings must be at least this share of the requested amount. Default 50%.">
                        <input v-model="form.loan_min_savings_percent" type="number" min="0" max="100" :class="input">
                    </FormField>
                    <FormField label="Minimum shares (% of loan)" hint="Member shares must be at least this share of the requested amount. Default 10%.">
                        <input v-model="form.loan_min_shares_percent" type="number" min="0" max="100" :class="input">
                    </FormField>
                    <FormField label="Fail at this many absences" hint="Counted in the current financial year. Permitted absences do not count. Default 3.">
                        <input v-model="form.loan_max_absences_per_year" type="number" min="1" max="50" :class="input">
                    </FormField>
                </div>
                <div class="mt-5 space-y-3 text-sm">
                    <p class="font-medium text-slate-900">Required payments</p>
                    <p class="text-slate-500">Tick the contribution products members must have paid before they can take a loan. This list comes from this cooperative’s products, so each society can set its own rules.</p>
                    <p v-if="!contribution_products.length" class="rounded-2xl bg-slate-50 px-4 py-3 text-slate-600">
                        No contribution products yet.
                        <Link :href="`${base}/products`" class="font-medium text-teal-800">Add products</Link>
                        first, then choose which ones are required here.
                    </p>
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="product in contribution_products"
                            :key="product.uuid"
                            class="flex items-start gap-3"
                        >
                            <input v-model="form.loan_required_product_uuids" type="checkbox" :value="product.uuid" class="mt-1 rounded">
                            <span>
                                <span class="font-medium">{{ product.name }}</span>
                                <span class="mt-1 block text-slate-500">{{ product.hint }} · {{ product.frequency }}</span>
                            </span>
                        </label>
                    </div>
                    <label class="flex items-start gap-3 pt-2">
                        <input v-model="form.loan_check_attendance" type="checkbox" class="mt-1 rounded">
                        <span>
                            <span class="font-medium">Check meeting attendance</span>
                            <span class="mt-1 block text-slate-500">A member who reaches the absence limit this year cannot take a loan.</span>
                        </span>
                    </label>
                </div>
            </AppCard>
            <AppCard title="Notifications">
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.email_notifications" type="checkbox" class="rounded">
                    Email receipts and reminders
                </label>
                <label class="mt-3 flex items-center gap-3 text-sm">
                    <input v-model="form.sms_notifications" type="checkbox" class="rounded">
                    SMS reminders
                </label>
            </AppCard>
            <AppButton type="submit" :disabled="form.processing">Save policies</AppButton>
        </form>
</template>
