<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ roles: Array, permissions: Array, groups: Array });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const selectedId = ref(props.roles[0]?.id || null);
const selected = () => props.roles.find((r) => r.id === selectedId.value) || props.roles[0];
const checked = ref([...(selected()?.permissions || [])]);

watch(selectedId, () => {
    checked.value = [...(selected()?.permissions || [])];
});

const toggle = (name) => {
    if (checked.value.includes(name)) {
        checked.value = checked.value.filter((p) => p !== name);
    } else {
        checked.value = [...checked.value, name];
    }
};

const save = () => useForm({ permissions: checked.value }).post(`${base}/settings/access/${selectedId.value}`);
</script>

<template>
    <Head title="Access control" />
        <PageHeader eyebrow="Office" title="Access control" subtitle="Each role is a bundle of permissions. Staff inherit the role you assign them." />
        <SettingsNav current="access" />
        <div class="mt-6 grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <AppCard title="Roles" :padded="false">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    type="button"
                    class="flex w-full items-center justify-between border-t border-slate-100 px-5 py-3 text-left text-sm first:border-0"
                    :class="role.id === selectedId ? 'bg-slate-50 font-medium' : 'hover:bg-slate-50'"
                    @click="selectedId = role.id"
                >
                    <span>{{ role.name }}</span>
                    <span class="text-xs text-slate-400">{{ role.permissions.length }}</span>
                </button>
            </AppCard>
            <AppCard :title="selected()?.name">
                <p class="text-sm text-slate-500">Tick what this role may do. Administrators always keep settings and user management.</p>
                <div class="mt-6 space-y-6">
                    <section v-for="group in groups" :key="group.label">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</h3>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label v-for="perm in group.items" :key="perm" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                <input type="checkbox" class="rounded" :checked="checked.includes(perm)" @change="toggle(perm)">
                                <span>{{ perm.replaceAll('.', ' · ') }}</span>
                            </label>
                        </div>
                    </section>
                </div>
                <div class="mt-6">
                    <AppButton type="button" @click="save">Save {{ selected()?.name }}</AppButton>
                </div>
            </AppCard>
        </div>
</template>
