<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import AppButton from './AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    branches: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    introducers: { type: Array, default: () => [] },
    locked_branch: { type: Boolean, default: false },
    default_branch_uuid: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const blank = () => ({
    first_name: '', last_name: '', middle_name: '', gender: '', date_of_birth: '',
    email: '', phone: '', password: 'Password1', occupation: '', employer: '',
    address: '', state: page.props.tenant?.state || 'Kano', lga: '', bvn: '', nin: '',
    next_of_kin_name: '', next_of_kin_relationship: 'Spouse', next_of_kin_phone: '', next_of_kin_address: '',
    branch_uuid: props.default_branch_uuid || props.branches[0]?.uuid || '',
    introducer_uuid: '',
    guarantor_uuid: '',
});

const form = useForm(blank());

watch(() => props.show, (open) => {
    if (! open) {
        return;
    }
    form.clearErrors();
    form.reset();
    form.branch_uuid = props.default_branch_uuid || props.branches[0]?.uuid || '';
    form.state = page.props.tenant?.state || 'Kano';
    form.password = 'Password1';
});

const introducerOptions = computed(() => (props.introducers || []).map((m) => ({
    uuid: m.uuid,
    name: `${m.first_name} ${m.last_name} (${m.membership_number})`,
})));

const save = () => form.post(`${base}/members`, {
    onSuccess: () => emit('close'),
});
</script>

<template>
    <Modal :show="show" title="Add member" subtitle="Identity, contact, KYC, and next of kin." wide @close="emit('close')">
        <form class="space-y-5" @submit.prevent="save">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Identity</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <FormField label="First name" :error="form.errors.first_name"><input v-model="form.first_name" :class="input" required></FormField>
                    <FormField label="Last name"><input v-model="form.last_name" :class="input" required></FormField>
                    <FormField label="Middle name"><input v-model="form.middle_name" :class="input"></FormField>
                    <FormField label="Gender">
                        <AppSelect v-model="form.gender" placeholder="Select" :options="[{ value: 'female', label: 'Female' }, { value: 'male', label: 'Male' }]" />
                    </FormField>
                    <FormField label="Date of birth"><input v-model="form.date_of_birth" type="date" :class="input"></FormField>
                    <FormField label="Occupation"><input v-model="form.occupation" :class="input"></FormField>
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Contact & portal</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <FormField label="Email" hint="Used for the member portal."><input v-model="form.email" type="email" :class="input"></FormField>
                    <FormField label="Phone"><input v-model="form.phone" :class="input"></FormField>
                    <FormField label="Portal password"><input v-model="form.password" :class="input"></FormField>
                    <FormField label="Employer"><input v-model="form.employer" :class="input"></FormField>
                    <FormField v-if="branches.length && !locked_branch" label="Branch">
                        <AppSelect v-model="form.branch_uuid" :options="branches" option-value="uuid" option-label="name" required />
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
            </section>
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">KYC</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <FormField label="BVN"><input v-model="form.bvn" maxlength="11" :class="input"></FormField>
                    <FormField label="NIN"><input v-model="form.nin" maxlength="11" :class="input"></FormField>
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">Next of kin</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <FormField label="Name"><input v-model="form.next_of_kin_name" :class="input"></FormField>
                    <FormField label="Relationship"><input v-model="form.next_of_kin_relationship" :class="input"></FormField>
                    <FormField label="Phone"><input v-model="form.next_of_kin_phone" :class="input"></FormField>
                    <FormField label="Address"><input v-model="form.next_of_kin_address" :class="input"></FormField>
                </div>
            </section>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton type="button" variant="secondary" @click="emit('close')">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="form.processing">Save member</AppButton>
            </div>
        </form>
    </Modal>
</template>
