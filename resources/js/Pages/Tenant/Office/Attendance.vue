<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import StatCard from '../../../Components/StatCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    meetings: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    roll: { type: Object, default: null },
});

const page = usePage();
const { can } = useCan();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const open = ref(false);
const query = ref('');
const kindFilter = ref('all');
const rollQuery = ref('');
const rollView = ref('all');
const marks = ref({});
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const kinds = [
    { value: 'general', label: 'General meeting' },
    { value: 'agm', label: 'AGM' },
    { value: 'committee', label: 'Committee' },
];

const statuses = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'permitted', label: 'Permitted' },
];

const form = useForm({
    title: '',
    held_on: new Date().toISOString().slice(0, 10),
    kind: 'general',
    branch_uuid: '',
    notes: '',
});

const rollForm = useForm({ attendance: {} });

const defaultTitle = () => {
    const kind = kinds.find((k) => k.value === form.kind)?.label || 'Meeting';
    const date = form.held_on
        ? new Date(`${form.held_on}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        : '';

    return date ? `${kind} · ${date}` : kind;
};

watch([() => form.kind, () => form.held_on], () => {
    if (! form.title || form.title === form.__autofill) {
        const next = defaultTitle();
        form.title = next;
        form.__autofill = next;
    }
});

watch(() => props.roll, (roll) => {
    marks.value = Object.fromEntries((roll?.members || []).map((m) => [m.uuid, m.status || 'absent']));
    rollQuery.value = '';
    rollView.value = 'all';
}, { immediate: true });

const openCreate = () => {
    form.reset();
    form.clearErrors();
    form.held_on = new Date().toISOString().slice(0, 10);
    form.kind = 'general';
    form.branch_uuid = '';
    const next = defaultTitle();
    form.title = next;
    form.__autofill = next;
    open.value = true;
};

const submit = () => form.post(`${base}/attendance`, {
    onSuccess: () => {
        open.value = false;
        form.reset();
    },
});

const openRoll = (meeting) => {
    router.get(`${base}/attendance`, { roll: meeting.uuid }, {
        preserveState: true,
        preserveScroll: true,
        only: ['roll', 'meetings', 'counts'],
    });
};

const closeRoll = () => {
    router.get(`${base}/attendance`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['roll'],
    });
};

const setMark = (uuid, status) => {
    marks.value = { ...marks.value, [uuid]: status };
};

const markAllPresent = () => {
    marks.value = Object.fromEntries((props.roll?.members || []).map((m) => [m.uuid, 'present']));
};

const liveStats = computed(() => {
    const members = props.roll?.members || [];
    const total = members.length;
    const present = members.filter((m) => (marks.value[m.uuid] || 'absent') === 'present').length;
    const permitted = members.filter((m) => marks.value[m.uuid] === 'permitted').length;
    const absent = Math.max(0, total - present - permitted);

    return {
        total,
        present,
        permitted,
        absent,
        rate: total > 0 ? Math.round((present / total) * 100) : 0,
    };
});

const savedSnapshot = computed(() => (props.roll?.members || [])
    .map((m) => `${m.uuid}:${m.status || 'absent'}`)
    .sort()
    .join('|'));

const dirty = computed(() => (props.roll?.members || [])
    .map((m) => `${m.uuid}:${marks.value[m.uuid] || 'absent'}`)
    .sort()
    .join('|') !== savedSnapshot.value);

const filteredRoll = computed(() => {
    const term = rollQuery.value.trim().toLowerCase();

    return (props.roll?.members || []).filter((m) => {
        const status = marks.value[m.uuid] || 'absent';
        if (rollView.value !== 'all' && status !== rollView.value) {
            return false;
        }
        if (term && ! `${m.name} ${m.number} ${m.branch || ''}`.toLowerCase().includes(term)) {
            return false;
        }

        return true;
    });
});

const saveRoll = () => {
    if (! props.roll?.meeting?.uuid) {
        return;
    }
    rollForm.attendance = { ...marks.value };
    rollForm.post(`${base}/attendance/${props.roll.meeting.uuid}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return props.meetings.filter((m) => {
        if (kindFilter.value !== 'all' && m.kind !== kindFilter.value) {
            return false;
        }
        if (term && ! `${m.title} ${m.branch || ''} ${m.kind_label}`.toLowerCase().includes(term)) {
            return false;
        }

        return true;
    });
});

const avgRate = computed(() => {
    const value = props.counts.avg_rate;
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }

    return `${Math.round(Number(value))}%`;
});

const statusLabel = (status) => ({
    not_started: 'Not started',
    in_progress: 'In progress',
    complete: 'Complete',
}[status] || status);

const markClass = (active, tone) => {
    if (! active) {
        return 'text-slate-500 hover:bg-white';
    }

    return {
        present: 'bg-teal-700 text-white',
        absent: 'bg-slate-700 text-white',
        permitted: 'bg-sky-700 text-white',
    }[tone];
};
</script>

<template>
    <Head title="Attendance" />
    <PageHeader title="Attendance">
        <template #actions>
            <AppButton v-if="can('attendance.manage')" type="button" variant="gold" @click="openCreate">New meeting</AppButton>
        </template>
    </PageHeader>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Meetings" :value="counts.meetings ?? meetings.length" />
        <StatCard label="This month" :value="counts.this_month ?? 0" />
        <StatCard label="Avg attendance" :value="avgRate" />
        <StatCard label="Active members" :value="counts.members ?? 0" />
    </div>

    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex flex-wrap gap-2 text-xs">
            <button
                class="rounded-full px-3 py-1.5"
                :class="kindFilter === 'all' ? 'bg-[#071c1b] text-white' : 'bg-white shadow-sm ring-1 ring-black/5'"
                @click="kindFilter = 'all'"
            >
                All
            </button>
            <button
                v-for="k in kinds"
                :key="k.value"
                class="rounded-full px-3 py-1.5"
                :class="kindFilter === k.value ? 'bg-teal-700 text-white' : 'bg-white shadow-sm ring-1 ring-black/5'"
                @click="kindFilter = k.value"
            >
                {{ k.label }}
            </button>
        </div>
        <input
            v-model="query"
            type="search"
            placeholder="Search meetings"
            class="w-full rounded-2xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-black/5 lg:ml-auto lg:max-w-sm"
        >
    </div>

    <AppCard class="mt-4" :padded="false">
        <EmptyState
            v-if="!filtered.length"
            icon="members"
            title="No meetings yet"
            body="Create a meeting to take the attendance roll for a branch or the whole cooperative."
        />
        <table v-else class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Meeting</th>
                    <th class="px-5 py-3">Date</th>
                    <th class="hidden px-5 py-3 sm:table-cell">Branch</th>
                    <th class="px-5 py-3">Attendance</th>
                    <th class="hidden px-5 py-3 md:table-cell">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="m in filtered" :key="m.uuid" class="border-t border-slate-100">
                    <td class="px-5 py-3.5">
                        <button type="button" class="text-left font-medium text-slate-900 hover:text-teal-800" @click="openRoll(m)">
                            {{ m.title }}
                        </button>
                        <div class="text-xs text-slate-500">{{ m.kind_label }}</div>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ m.held_on }}</td>
                    <td class="hidden px-5 py-3.5 sm:table-cell">{{ m.branch || 'All branches' }}</td>
                    <td class="px-5 py-3.5">
                        <div class="font-medium tabular-nums text-slate-900">{{ m.present }} / {{ m.eligible }}</div>
                        <div class="text-xs text-slate-500">
                            {{ m.rate }}% present
                            <template v-if="m.permitted"> · {{ m.permitted }} permitted</template>
                        </div>
                    </td>
                    <td class="hidden px-5 py-3.5 md:table-cell">
                        <StatusBadge :value="m.status" />
                        <span class="sr-only">{{ statusLabel(m.status) }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <AppButton type="button" variant="secondary" @click="openRoll(m)">Take roll</AppButton>
                    </td>
                </tr>
            </tbody>
        </table>
    </AppCard>

    <Modal :show="open" title="New meeting" @close="open = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Title" :error="form.errors.title">
                <input v-model="form.title" :class="input" required>
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Date" :error="form.errors.held_on">
                    <input v-model="form.held_on" type="date" :class="input" required>
                </FormField>
                <FormField label="Type">
                    <AppSelect v-model="form.kind" :options="kinds" variant="outline" />
                </FormField>
            </div>
            <FormField v-if="branches.length" label="Branch">
                <AppSelect
                    v-model="form.branch_uuid"
                    :options="[{ uuid: '', name: 'All branches' }, ...branches]"
                    option-value="uuid"
                    option-label="name"
                    variant="outline"
                />
            </FormField>
            <FormField label="Notes" :error="form.errors.notes">
                <textarea v-model="form.notes" rows="2" :class="input" placeholder="Optional agenda or venue"></textarea>
            </FormField>
            <div class="flex justify-end gap-2">
                <AppButton type="button" variant="secondary" @click="open = false">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="form.processing">Create & take roll</AppButton>
            </div>
        </form>
    </Modal>

    <Modal
        :show="!!roll"
        :title="roll?.meeting?.title || 'Take roll'"
        :subtitle="roll ? `${roll.meeting.kind_label} · ${roll.meeting.held_on} · ${roll.meeting.branch || 'All branches'}` : ''"
        wide
        @close="closeRoll"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Present</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums">{{ liveStats.present }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Absent</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums">{{ liveStats.absent }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Permitted</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums">{{ liveStats.permitted }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Attendance</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums">{{ liveStats.rate }}%</div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="flex flex-wrap gap-2 text-xs">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5"
                        :class="rollView === 'all' ? 'bg-[#071c1b] text-white' : 'bg-slate-100 text-slate-600'"
                        @click="rollView = 'all'"
                    >
                        All {{ liveStats.total }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5"
                        :class="rollView === 'present' ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600'"
                        @click="rollView = 'present'"
                    >
                        Present {{ liveStats.present }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5"
                        :class="rollView === 'absent' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-600'"
                        @click="rollView = 'absent'"
                    >
                        Absent {{ liveStats.absent }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5"
                        :class="rollView === 'permitted' ? 'bg-sky-700 text-white' : 'bg-slate-100 text-slate-600'"
                        @click="rollView = 'permitted'"
                    >
                        Permitted {{ liveStats.permitted }}
                    </button>
                </div>
                <input
                    v-model="rollQuery"
                    type="search"
                    placeholder="Search members"
                    class="w-full rounded-2xl border-0 bg-slate-50 px-4 py-2 text-sm ring-1 ring-slate-200 sm:ml-auto sm:max-w-xs"
                >
            </div>

            <EmptyState v-if="!roll?.members?.length" icon="members" title="No members to mark" body="Activate members for this branch first." />
            <EmptyState v-else-if="!filteredRoll.length" icon="members" title="No matches" body="Try another search or filter." />
            <ul v-else class="-mx-1 max-h-[46vh] divide-y divide-slate-100 overflow-y-auto rounded-2xl ring-1 ring-slate-100">
                <li
                    v-for="m in filteredRoll"
                    :key="m.uuid"
                    class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="font-medium text-slate-900">{{ m.name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ m.number }}
                            <template v-if="m.branch && !roll.meeting.branch"> · {{ m.branch }}</template>
                        </div>
                    </div>
                    <div class="flex shrink-0 rounded-full bg-slate-100 p-0.5 text-xs font-semibold">
                        <button
                            v-for="option in statuses"
                            :key="option.value"
                            type="button"
                            class="rounded-full px-2.5 py-1.5"
                            :class="markClass((marks[m.uuid] || 'absent') === option.value, option.value)"
                            @click="setMark(m.uuid, option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </li>
            </ul>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <AppButton v-if="can('attendance.manage')" type="button" variant="secondary" :disabled="!roll?.members?.length" @click="markAllPresent">
                    Mark all present
                </AppButton>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="closeRoll">Close</AppButton>
                    <AppButton v-if="can('attendance.manage')" type="button" variant="gold" :disabled="rollForm.processing || !dirty" @click="saveRoll">
                        {{ dirty ? 'Save roll' : 'Saved' }}
                    </AppButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
