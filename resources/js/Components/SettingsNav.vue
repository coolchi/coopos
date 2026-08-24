<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    current: { type: String, required: true },
});

const page = usePage();
const base = computed(() => page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`);

const items = computed(() => [
    { id: 'organization', href: `${base.value}/settings`, label: 'Organization' },
    { id: 'policies', href: `${base.value}/settings/policies`, label: 'Policies' },
    { id: 'users', href: `${base.value}/settings/users`, label: 'Staff' },
    { id: 'access', href: `${base.value}/settings/access`, label: 'Access control' },
    { id: 'workflows', href: `${base.value}/settings/workflows`, label: 'Approvals' },
    { id: 'activity', href: `${base.value}/settings/activity`, label: 'Activity log' },
    { id: 'demo', href: `${base.value}/settings/demo`, label: 'Demo data' },
]);
</script>

<template>
    <nav class="mt-6 overflow-x-auto pb-0.5">
        <div class="inline-flex min-w-full gap-1 rounded-2xl bg-slate-100 p-1 sm:min-w-0">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="item.href"
                class="shrink-0 rounded-xl px-3.5 py-2 text-sm font-medium transition"
                :class="current === item.id
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'"
            >
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
