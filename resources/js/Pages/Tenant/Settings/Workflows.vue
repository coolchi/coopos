<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    workflows: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const filter = ref('all');
const editing = ref(null);
const deleting = ref(null);
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';

const form = useForm({
    name: '',
    applies_to: 'withdrawal',
    min_amount: 0,
    max_amount: '',
    is_active: true,
    steps: [{ name: '', role_name: 'Treasurer' }],
});

const typeOptions = computed(() => props.types.length
    ? props.types
    : [
        { value: 'withdrawal', label: 'Withdrawals' },
        { value: 'loan', label: 'Loans' },
        { value: 'islamic_financing', label: 'Islamic financing' },
    ]);

const roleOptions = computed(() => (props.roles || []).map((role) => ({
    value: role,
    label: role,
})));

const defaultRole = computed(() => props.roles.includes('Treasurer')
    ? 'Treasurer'
    : (props.roles[0] || 'Administrator'));

const filters = computed(() => [
    { id: 'all', label: 'All', count: props.workflows.length },
    ...typeOptions.value.map((type) => ({
        id: type.value,
        label: type.label,
        count: props.workflows.filter((w) => w.applies_to === type.value).length,
    })),
]);

const visible = computed(() => {
    if (filter.value === 'all') {
        return props.workflows;
    }

    return props.workflows.filter((w) => w.applies_to === filter.value);
});

const groups = computed(() => {
    const order = typeOptions.value.map((t) => t.value);
    const leftover = [...new Set(visible.value.map((w) => w.applies_to).filter((id) => ! order.includes(id)))];

    return [...order, ...leftover]
        .map((id) => {
            const items = visible.value.filter((w) => w.applies_to === id);
            const type = typeOptions.value.find((t) => t.value === id);

            return {
                id,
                label: type?.label || items[0]?.applies_to_label || id.replaceAll('_', ' '),
                items,
            };
        })
        .filter((group) => group.items.length);
});

const blankStep = () => ({ name: '', role_name: defaultRole.value });

const applyForm = (payload) => {
    form.clearErrors();
    form.name = payload.name;
    form.applies_to = payload.applies_to;
    form.min_amount = payload.min_amount;
    form.max_amount = payload.max_amount;
    form.is_active = payload.is_active;
    form.steps = payload.steps.map((step) => ({ name: step.name, role_name: step.role_name }));
};

const openCreate = () => {
    editing.value = { uuid: null, can_delete: false };
    applyForm({
        name: '',
        applies_to: typeOptions.value[0]?.value || 'withdrawal',
        min_amount: 0,
        max_amount: '',
        is_active: true,
        steps: [blankStep()],
    });
};

const openEdit = (workflow) => {
    editing.value = workflow;
    applyForm({
        name: workflow.name,
        applies_to: workflow.applies_to,
        min_amount: workflow.min_amount,
        max_amount: workflow.max_amount ?? '',
        is_active: workflow.is_active,
        steps: workflow.steps.length ? workflow.steps : [blankStep()],
    });
};

const closeEditor = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
};

const addStep = () => {
    form.steps = [...form.steps, blankStep()];
};

const removeStep = (index) => {
    if (form.steps.length === 1) {
        return;
    }
    form.steps = form.steps.filter((_, i) => i !== index);
};

const moveStep = (index, direction) => {
    const next = index + direction;
    if (next < 0 || next >= form.steps.length) {
        return;
    }
    const steps = [...form.steps];
    [steps[index], steps[next]] = [steps[next], steps[index]];
    form.steps = steps;
};

const payload = () => ({
    name: form.name,
    applies_to: form.applies_to,
    min_amount: form.min_amount,
    max_amount: form.max_amount === '' || form.max_amount === null ? null : form.max_amount,
    is_active: form.is_active,
    steps: form.steps,
});

const save = () => {
    const data = payload();
    if (editing.value?.uuid) {
        form.transform(() => data).put(`${base}/settings/workflows/${editing.value.uuid}`, {
            preserveScroll: true,
            onSuccess: () => closeEditor(),
        });
        return;
    }

    form.transform(() => data).post(`${base}/settings/workflows`, {
        preserveScroll: true,
        onSuccess: () => closeEditor(),
    });
};

const toggle = (workflow) => useForm({}).post(`${base}/settings/workflows/${workflow.uuid}`, { preserveScroll: true });

const confirmDelete = () => {
    if (! deleting.value) {
        return;
    }
    useForm({}).delete(`${base}/settings/workflows/${deleting.value.uuid}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            closeEditor();
        },
    });
};

const stepError = (index, field) => form.errors[`steps.${index}.${field}`] || '';
</script>

<template>
    <Head title="Approval workflows" />
    <PageHeader
        eyebrow="Office"
        title="Approval workflows"
        subtitle="Set who signs off, in what order, and from which amount. Inactive paths are skipped."
    >
        <template #actions>
            <AppButton type="button" variant="gold" @click="openCreate">New workflow</AppButton>
        </template>
    </PageHeader>
    <SettingsNav current="workflows" />

    <div class="mt-6 flex gap-2 overflow-x-auto pb-1">
        <button
            v-for="tab in filters"
            :key="tab.id"
            type="button"
            class="shrink-0 rounded-full px-3 py-1.5 text-sm"
            :class="filter === tab.id ? 'bg-[#071c1b] text-white' : 'bg-white text-slate-600 ring-1 ring-black/5'"
            @click="filter = tab.id"
        >
            {{ tab.label }} <span class="text-xs opacity-70">{{ tab.count }}</span>
        </button>
    </div>

    <AppCard v-if="!visible.length" class="mt-6" :padded="false">
        <EmptyState
            icon="approvals"
            title="No workflows in this view"
            body="Create an amount-banded path so withdrawals and financing wait for the right officers."
        >
            <AppButton type="button" variant="gold" @click="openCreate">New workflow</AppButton>
        </EmptyState>
    </AppCard>

    <div v-else class="mt-6 space-y-8">
        <section v-for="group in groups" :key="group.id">
            <h2 class="px-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ group.label }}</h2>
            <div class="mt-3 space-y-4">
                <AppCard v-for="w in group.items" :key="w.uuid" :class="w.is_active ? '' : 'opacity-80'">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-900">{{ w.name }}</h3>
                                <StatusBadge :value="w.is_active ? 'active' : 'inactive'" />
                                <span v-if="w.pending_count" class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-800">
                                    {{ w.pending_count }} pending
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ w.min }} – {{ w.max }}</p>

                            <ol class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                <template v-for="(step, index) in w.steps" :key="step.id || index">
                                    <li class="flex min-w-0 items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#071c1b] text-xs font-semibold text-[#d4b06a]">
                                            {{ index + 1 }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-slate-900">{{ step.name }}</span>
                                            <span class="block truncate text-xs text-slate-500">{{ step.role_name }}</span>
                                        </span>
                                    </li>
                                    <span
                                        v-if="index < w.steps.length - 1"
                                        class="hidden h-px w-8 bg-slate-200 sm:block"
                                        aria-hidden="true"
                                    />
                                </template>
                            </ol>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <AppButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs" @click="toggle(w)">
                                {{ w.is_active ? 'Disable' : 'Enable' }}
                            </AppButton>
                            <AppButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs" @click="openEdit(w)">Edit</AppButton>
                            <AppButton
                                v-if="w.can_delete"
                                type="button"
                                variant="danger"
                                class="!px-3 !py-1.5 text-xs"
                                @click="deleting = w"
                            >
                                Delete
                            </AppButton>
                        </div>
                    </div>
                </AppCard>
            </div>
        </section>
    </div>

    <Modal
        :show="!!editing"
        :title="editing?.uuid ? 'Edit workflow' : 'New workflow'"
        :subtitle="editing?.uuid ? 'Changes apply to new requests. In-flight approvals keep their current step.' : 'Match an amount band to the officers who must sign off.'"
        wide
        @close="closeEditor"
    >
        <form class="space-y-5" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Name" :error="form.errors.name">
                    <input v-model="form.name" :class="input" placeholder="Loans above ₦500,000" required>
                </FormField>
                <FormField label="Applies to" :error="form.errors.applies_to">
                    <AppSelect v-model="form.applies_to" :options="typeOptions" option-value="value" option-label="label" required />
                </FormField>
                <FormField label="From (₦)" :error="form.errors.min_amount" hint="Inclusive lower bound.">
                    <AmountInput v-model="form.min_amount" :class="input" required />
                </FormField>
                <FormField label="Up to (₦)" :error="form.errors.max_amount" hint="Leave blank for no ceiling.">
                    <AmountInput v-model="form.max_amount" :class="input" placeholder="No ceiling" />
                </FormField>
            </div>
            <label class="flex items-start gap-3 text-sm">
                <input v-model="form.is_active" type="checkbox" class="mt-1 rounded">
                <span>
                    <span class="font-medium text-slate-900">Active</span>
                    <span class="mt-0.5 block text-slate-500">Only active workflows are used when a request is submitted.</span>
                </span>
            </label>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900">Approval steps</h3>
                    <AppButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs" @click="addStep">Add step</AppButton>
                </div>
                <p v-if="form.errors.steps" class="mt-1 text-xs font-medium text-rose-700">{{ form.errors.steps }}</p>
                <div class="mt-3 space-y-3">
                    <div
                        v-for="(step, index) in form.steps"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Step {{ index + 1 }}</span>
                            <div class="flex gap-1">
                                <button type="button" class="rounded-lg px-2 py-1 text-xs text-slate-600 hover:bg-white disabled:opacity-40" :disabled="index === 0" @click="moveStep(index, -1)">Up</button>
                                <button type="button" class="rounded-lg px-2 py-1 text-xs text-slate-600 hover:bg-white disabled:opacity-40" :disabled="index === form.steps.length - 1" @click="moveStep(index, 1)">Down</button>
                                <button type="button" class="rounded-lg px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-40" :disabled="form.steps.length === 1" @click="removeStep(index)">Remove</button>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <FormField label="Step name" :error="stepError(index, 'name')">
                                <input v-model="step.name" :class="input" placeholder="Treasurer approval" required>
                            </FormField>
                            <FormField label="Approver role" :error="stepError(index, 'role_name')">
                                <AppSelect v-model="step.role_name" :options="roleOptions" required />
                            </FormField>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <AppButton
                    v-if="editing?.uuid && editing?.can_delete"
                    type="button"
                    variant="danger"
                    :disabled="form.processing"
                    @click="deleting = editing"
                >
                    Delete
                </AppButton>
                <p v-else-if="editing?.uuid && !editing?.can_delete" class="text-xs text-slate-500">
                    This path has approval history, so it can be disabled but not deleted.
                </p>
                <span v-else></span>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" :disabled="form.processing" @click="closeEditor">Cancel</AppButton>
                    <AppButton type="submit" variant="gold" :disabled="form.processing">
                        {{ editing?.uuid ? 'Save workflow' : 'Create workflow' }}
                    </AppButton>
                </div>
            </div>
        </form>
    </Modal>

    <ConfirmModal
        :show="!!deleting"
        title="Delete workflow"
        :message="`Delete ${deleting?.name || 'this workflow'}? New requests will no longer use this path.`"
        confirm-label="Delete"
        variant="danger"
        @close="deleting = null"
        @confirm="confirmDelete"
    />
</template>
