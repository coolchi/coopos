<script setup>
import Pagination from '../../Components/Pagination.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ users: Object, filters: Object });
const q = ref(props.filters?.q || '');
const role = ref(props.filters?.role || '');
const create = useForm({ name: '', email: '', password: 'Password1', role: 'agent' });

const filter = () => router.get('/platform/users', { q: q.value || undefined, role: role.value || undefined }, { preserveState: true, replace: true });
</script>

<template>
            <Head title="Operators" />
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h1 class="text-3xl font-semibold tracking-tight">Operators</h1>
                <p class="mt-2 text-sm text-slate-500">Admins run the control plane. Agents handle support without tenant staff or members listed here.</p>
                <form class="mt-6 flex flex-wrap gap-3" @submit.prevent="filter">
                    <input v-model="q" type="search" placeholder="Search name or email" class="min-w-56 flex-1 rounded-2xl bg-white px-4 py-3 text-sm shadow-sm">
                    <AppSelect
                        v-model="role"
                        class="min-w-40"
                        variant="white"
                        placeholder="All roles"
                        :options="[{ value: 'admin', label: 'Admin' }, { value: 'agent', label: 'Agent' }]"
                        @change="filter"
                    />
                    <button class="rounded-2xl bg-[#071c1b] px-4 py-3 text-sm text-white">Search</button>
                </form>
                <div class="mt-4 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
                    <EmptyState v-if="!users.data.length" icon="search" title="No operators match" body="Try another role or search term." />
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Name</th>
                                    <th class="px-5 py-3">Role</th>
                                    <th class="px-5 py-3">Last login</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.uuid" class="border-t border-slate-100">
                                    <td class="px-5 py-4">
                                        <div class="font-medium">{{ user.name }}</div>
                                        <div class="text-xs text-slate-500">{{ user.email }}</div>
                                    </td>
                                    <td class="px-5 py-4 capitalize">{{ user.role }}</td>
                                    <td class="px-5 py-4">{{ user.last_login_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="users.links" :from="users.from" :to="users.to" :total="users.total" />
                </div>
            </div>
            <form class="h-fit rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="create.post('/platform/users', { onSuccess: () => create.reset('name', 'email') })">
                <h2 class="text-lg font-semibold">Add operator</h2>
                <p class="mt-1 text-sm text-slate-500">Invite an admin or a support agent.</p>
                <input v-model="create.name" required placeholder="Name" class="mt-4 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                <p v-if="create.errors.name" class="mt-1 text-xs text-rose-700">{{ create.errors.name }}</p>
                <input v-model="create.email" type="email" required placeholder="Email" class="mt-3 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                <p v-if="create.errors.email" class="mt-1 text-xs text-rose-700">{{ create.errors.email }}</p>
                <input v-model="create.password" type="password" required minlength="8" placeholder="Password (min 8)" class="mt-3 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                <p v-if="create.errors.password" class="mt-1 text-xs text-rose-700">{{ create.errors.password }}</p>
                <AppSelect
                    v-model="create.role"
                    class="mt-3 w-full"
                    variant="white"
                    :options="[{ value: 'admin', label: 'Admin' }, { value: 'agent', label: 'Agent' }]"
                />
                <p v-if="create.errors.role" class="mt-1 text-xs text-rose-700">{{ create.errors.role }}</p>
                <button class="mt-4 w-full rounded-2xl bg-[#071c1b] py-2.5 text-sm text-white" :disabled="create.processing">Create operator</button>
            </form>
        </div>
</template>
