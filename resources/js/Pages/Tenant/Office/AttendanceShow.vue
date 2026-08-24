<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import StatCard from '../../../Components/StatCard.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    meeting: Object,
    members: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const query = ref('');
const view = ref('all');
const present = ref(props.members.filter((m) => m.present).map((m) => m.uuid));
const form = useForm({ present: present.value });
const savedSnapshot = ref([...present.value].sort().join(','));

watch(() => props.members, (members) => {
    present.value = members.filter((m) => m.present).map((m) => m.uuid);
    savedSnapshot.value = [...present.value].sort().join(',');
});

const dirty = computed(() => [...present.value].sort().join(',') !== savedSnapshot.value);

const liveStats = computed(() => {
    const total = props.members.length;
    const presentCount = present.value.length;
    const absent = Math.max(0, total - presentCount);

    return {
        total,
        present: presentCount,
        absent,
        rate: total > 0 ? Math.round((presentCount / total) * 100) : 0,
    };
});

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return props.members.filter((m) => {
        const isPresent = present.value.includes(m.uuid);
        if (view.value === 'present' && ! isPresent) {
            return false;
        }
        if (view.value === 'absent' && isPresent) {
            return false;
        }
        if (term && ! `${m.name} ${m.number} ${m.branch || ''}`.toLowerCase().includes(term)) {
            return false;
        }

        return true;
    });
});

const allPresent = computed(() => props.members.length > 0 && present.value.length === props.members.length);

const toggle = (uuid) => {
    if (present.value.includes(uuid)) {
        present.value = present.value.filter((id) => id !== uuid);
        return;
    }
    present.value = [...present.value, uuid];
};

const markAll = () => {
    present.value = allPresent.value ? [] : props.members.map((m) => m.uuid);
};

const save = () => {
    form.present = present.value;
    form.post(`${base}/attendance/${props.meeting.uuid}`, {
        onSuccess: () => {
            savedSnapshot.value = [...present.value].sort().join(',');
        },
    });
};
</script>

<template>
    <Head :title="meeting.title" />
    <div class="mb-3">
        <Link :href="`${base}/attendance`" class="text-sm font-medium text-teal-800 hover:underline">← Attendance</Link>
    </div>
    <PageHeader :title="meeting.title">
        <template #actions>
            <AppButton v-if="can('attendance.manage')" type="button" variant="secondary" @click="markAll">
                {{ allPresent ? 'Clear all' : 'Mark all present' }}
            </AppButton>
            <AppButton v-if="can('attendance.manage')" type="button" variant="gold" :disabled="form.processing || !dirty" @click="save">
                {{ dirty ? 'Save roll' : 'Saved' }}
            </AppButton>
        </template>
    </PageHeader>
    <p class="mt-1 text-sm text-slate-500">
        {{ meeting.kind_label }} · {{ meeting.held_on }} · {{ meeting.branch || 'All branches' }}
        <span v-if="meeting.notes"> · {{ meeting.notes }}</span>
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Present" :value="liveStats.present" />
        <StatCard label="Absent" :value="liveStats.absent" />
        <StatCard label="Members" :value="liveStats.total" />
        <StatCard label="Attendance" :value="`${liveStats.rate}%`" />
    </div>

    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex flex-wrap gap-2 text-xs">
            <button
                class="rounded-full px-3 py-1.5"
                :class="view === 'all' ? 'bg-[#071c1b] text-white' : 'bg-white shadow-sm ring-1 ring-black/5'"
                @click="view = 'all'"
            >
                All {{ liveStats.total }}
            </button>
            <button
                class="rounded-full px-3 py-1.5"
                :class="view === 'present' ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'"
                @click="view = 'present'"
            >
                Present {{ liveStats.present }}
            </button>
            <button
                class="rounded-full px-3 py-1.5"
                :class="view === 'absent' ? 'bg-rose-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'"
                @click="view = 'absent'"
            >
                Absent {{ liveStats.absent }}
            </button>
        </div>
        <input
            v-model="query"
            type="search"
            placeholder="Search members"
            class="w-full rounded-2xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-black/5 lg:ml-auto lg:max-w-sm"
        >
    </div>

    <AppCard class="mt-4" :padded="false">
        <EmptyState v-if="!members.length" icon="members" title="No members to mark" body="Activate members for this branch first." />
        <EmptyState
            v-else-if="!filtered.length"
            icon="members"
            title="No matches"
            body="Try another search or filter."
        />
        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="m in filtered"
                :key="m.uuid"
                class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 transition hover:bg-slate-50 sm:px-5"
                @click="toggle(m.uuid)"
            >
                <div class="min-w-0">
                    <div class="font-medium text-slate-900">{{ m.name }}</div>
                    <div class="text-xs text-slate-500">
                        {{ m.number }}
                        <template v-if="m.branch && !meeting.branch"> · {{ m.branch }}</template>
                    </div>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="present.includes(m.uuid)
                        ? 'bg-teal-100 text-teal-800'
                        : 'bg-slate-100 text-slate-600'"
                    @click.stop="toggle(m.uuid)"
                >
                    {{ present.includes(m.uuid) ? 'Present' : 'Absent' }}
                </button>
            </li>
        </ul>
    </AppCard>

    <div
        v-if="dirty"
        class="sticky bottom-4 z-10 mt-4 flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-lg sm:px-5"
    >
        <span>You have unsaved attendance changes.</span>
        <AppButton v-if="can('attendance.manage')" type="button" variant="gold" :disabled="form.processing" @click="save">Save roll</AppButton>
    </div>
</template>
