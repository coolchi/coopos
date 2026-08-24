<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    states: Array,
    branches: Array,
    locked_branch: Boolean,
    default_branch_uuid: { type: String, default: '' },
    introducers: { type: Array, default: () => [] },
});
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const form = useForm({
    first_name: '', last_name: '', middle_name: '', gender: '', date_of_birth: '',
    email: '', phone: '', password: 'Password1', occupation: '', employer: '',
    address: '', state: 'Kano', lga: '', bvn: '', nin: '',
    next_of_kin_name: '', next_of_kin_relationship: 'Spouse', next_of_kin_phone: '', next_of_kin_address: '',
    branch_uuid: props.default_branch_uuid || page.props.branch?.uuid || '',
    introducer_uuid: '',
    guarantor_uuid: '',
});
const introducerOptions = computed(() => (props.introducers || []).map((m) => ({
    uuid: m.uuid,
    name: `${m.first_name} ${m.last_name} (${m.membership_number})`,
})));
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
</script>

<template>
    <Head title="Add member" />
        <PageHeader eyebrow="Membership" title="Add member" subtitle="Identity, contact, KYC, and next of kin." />
        <form class="mt-8 space-y-6" @submit.prevent="form.post(`${base}/members`)">
            <AppCard title="Identity">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="First name" :error="form.errors.first_name"><input v-model="form.first_name" :class="input" required></FormField>
                    <FormField label="Last name"><input v-model="form.last_name" :class="input" required></FormField>
                    <FormField label="Middle name"><input v-model="form.middle_name" :class="input"></FormField>
                    <FormField label="Gender">
                        <AppSelect v-model="form.gender" placeholder="Select" :options="[{ value: 'female', label: 'Female' }, { value: 'male', label: 'Male' }]" />
                    </FormField>
                    <FormField label="Date of birth"><input v-model="form.date_of_birth" type="date" :class="input"></FormField>
                    <FormField label="Occupation"><input v-model="form.occupation" :class="input"></FormField>
                </div>
            </AppCard>
            <AppCard title="Contact & portal access">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Email" hint="Used for the member portal."><input v-model="form.email" type="email" :class="input"></FormField>
                    <FormField label="Phone"><input v-model="form.phone" :class="input"></FormField>
                    <FormField label="Portal password"><input v-model="form.password" :class="input"></FormField>
                    <FormField label="Employer"><input v-model="form.employer" :class="input"></FormField>
                    <FormField label="Branch">
                        <AppSelect v-model="form.branch_uuid" placeholder="Select branch" :options="branches" option-value="uuid" option-label="name" required :disabled="locked_branch" />
                    </FormField>
                    <FormField label="Introducer">
                        <AppSelect v-model="form.introducer_uuid" :options="introducerOptions" option-value="uuid" option-label="name" placeholder="None" />
                    </FormField>
                    <FormField label="Guarantor">
                        <AppSelect v-model="form.guarantor_uuid" :options="introducerOptions" option-value="uuid" option-label="name" placeholder="None" />
                    </FormField>
                    <FormField label="Address" class="sm:col-span-2"><input v-model="form.address" :class="input"></FormField>
                    <FormField label="State">
                        <AppSelect v-model="form.state" :options="states" />
                    </FormField>
                    <FormField label="LGA"><input v-model="form.lga" :class="input"></FormField>
                </div>
            </AppCard>
            <AppCard title="KYC">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="BVN"><input v-model="form.bvn" maxlength="11" :class="input"></FormField>
                    <FormField label="NIN"><input v-model="form.nin" maxlength="11" :class="input"></FormField>
                </div>
            </AppCard>
            <AppCard title="Next of kin">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Name"><input v-model="form.next_of_kin_name" :class="input"></FormField>
                    <FormField label="Relationship"><input v-model="form.next_of_kin_relationship" :class="input"></FormField>
                    <FormField label="Phone"><input v-model="form.next_of_kin_phone" :class="input"></FormField>
                    <FormField label="Address"><input v-model="form.next_of_kin_address" :class="input"></FormField>
                </div>
            </AppCard>
            <AppButton type="submit" variant="gold" :disabled="form.processing">Save member</AppButton>
        </form>
</template>
