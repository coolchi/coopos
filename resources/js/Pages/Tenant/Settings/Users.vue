<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ users: Array, roles: Array, branches: { type: Array, default: () => [] } });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const form = useForm({
    name: '',
    email: '',
    password: 'Password1',
    role: props.roles?.[0] || 'Secretary',
    branch_uuid: '',
});
const input = 'w-full rounded-2xl border-0 bg-slate-50 px-4 py-3 text-sm';
const setRole = (user, role) => useForm({ role, branch_uuid: user.branch_uuid || '' }).post(`${base}/settings/users/${user.uuid}/role`);
const setBranch = (user, branch_uuid) => useForm({ role: user.roles[0], branch_uuid }).post(`${base}/settings/users/${user.uuid}/role`);
</script>

<template>
    <Head title="Staff" />
        <PageHeader eyebrow="Office" title="Staff" subtitle="Invite officers and assign a working role." />
        <SettingsNav current="users" />
        <AppCard class="mt-6" title="Invite staff">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="form.post(`${base}/settings/users`)">
                <FormField label="Name"><input v-model="form.name" :class="input" required></FormField>
                <FormField label="Email"><input v-model="form.email" type="email" :class="input" required></FormField>
                <FormField label="Temporary password"><input v-model="form.password" :class="input" required></FormField>
                <FormField label="Role">
                    <AppSelect v-model="form.role" :options="roles" />
                </FormField>
                <FormField v-if="form.role === 'Branch Admin'" label="Branch">
                    <AppSelect v-model="form.branch_uuid" placeholder="Select branch" :options="branches" option-value="uuid" option-label="name" required />
                </FormField>
                <div class="sm:col-span-2"><AppButton type="submit">Invite</AppButton></div>
            </form>
            <p v-if="form.errors.email" class="mt-2 text-sm text-rose-600">{{ form.errors.email }}</p>
        </AppCard>
        <AppCard class="mt-6" :padded="false">
            <EmptyState v-if="!users.length" icon="staff" title="No staff yet" body="Invite a secretary, treasurer, or branch admin to help run the cooperative." />
            <table v-else class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Branch</th>
                        <th class="px-5 py-3">Last login</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.uuid" class="border-t border-slate-100">
                        <td class="px-5 py-4 font-medium">{{ u.name }}</td>
                        <td class="px-5 py-4">{{ u.email }}</td>
                        <td class="px-5 py-4">
                            <AppSelect
                                size="sm"
                                :model-value="u.roles[0]"
                                :options="roles"
                                @update:model-value="setRole(u, $event)"
                            />
                        </td>
                        <td class="px-5 py-4">
                            <AppSelect
                                size="sm"
                                :model-value="u.branch_uuid || ''"
                                placeholder="CENTRAL BRANCH"
                                :options="branches"
                                option-value="uuid"
                                option-label="name"
                                @update:model-value="setBranch(u, $event)"
                            />
                        </td>
                        <td class="px-5 py-4 text-slate-500">{{ u.last_login_at || 'Never' }}</td>
                    </tr>
                </tbody>
            </table>
        </AppCard>
</template>
