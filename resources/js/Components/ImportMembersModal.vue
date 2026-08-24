<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import AppButton from './AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    errors: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`;
const dragOver = ref(false);
const fileInput = ref(null);
const localErrors = ref([]);

const form = useForm({ file: null });

const fileName = computed(() => form.file?.name || '');
const fileSize = computed(() => {
    if (! form.file) {
        return '';
    }
    const kb = form.file.size / 1024;
    return kb < 1024 ? `${kb.toFixed(0)} KB` : `${(kb / 1024).toFixed(1)} MB`;
});

const requiredColumns = [
    { key: 'first_name', note: 'Given name' },
    { key: 'last_name', note: 'Family name' },
];

const optionalColumns = [
    { key: 'email', note: 'Login email if they have one' },
    { key: 'phone', note: 'e.g. 08012345678' },
    { key: 'state', note: 'Nigeria state name' },
    { key: 'occupation', note: 'Work or trade' },
    { key: 'branch', note: 'Branch code, e.g. HQ' },
    { key: 'introducer', note: 'Membership number' },
];

watch(() => props.show, (open) => {
    if (! open) {
        return;
    }
    form.clearErrors();
    form.reset();
    form.file = null;
    localErrors.value = [...(props.errors || [])];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
});

watch(() => props.errors, (rows) => {
    localErrors.value = [...(rows || [])];
});

const pickFile = (file) => {
    if (! file) {
        return;
    }
    const name = file.name.toLowerCase();
    if (! name.endsWith('.csv') && file.type !== 'text/csv' && file.type !== 'text/plain') {
        localErrors.value = ['Please choose a .csv file.'];
        return;
    }
    form.file = file;
    localErrors.value = [];
    form.clearErrors();
};

const onDrop = (event) => {
    dragOver.value = false;
    pickFile(event.dataTransfer?.files?.[0]);
};

const clearFile = () => {
    form.file = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const close = () => emit('close');

const submit = () => {
    if (! form.file) {
        return;
    }
    form.post(`${base}/members/import`, {
        forceFormData: true,
        onSuccess: (pageResult) => {
            const failed = pageResult.props.flash?.import_errors || [];
            if (failed.length) {
                localErrors.value = failed;
                form.file = null;
                if (fileInput.value) {
                    fileInput.value.value = '';
                }
                return;
            }
            close();
            form.reset();
            form.file = null;
        },
    });
};
</script>

<template>
    <Modal
        :show="show"
        title="Import members"
        subtitle="Bring in an existing membership list from a spreadsheet."
        wide
        @close="close"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">CSV format</h3>
                        <p class="mt-1 text-sm text-slate-600">First row must be column headers. Empty rows are skipped.</p>
                    </div>
                    <a
                        :href="`${base}/members/import/template`"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download template
                    </a>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Required</p>
                        <ul class="mt-2 space-y-1.5">
                            <li v-for="col in requiredColumns" :key="col.key" class="flex gap-2 text-sm text-slate-800">
                                <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs text-teal-900 ring-1 ring-slate-200">{{ col.key }}</code>
                                <span class="text-slate-600">{{ col.note }}</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Optional</p>
                        <ul class="mt-2 space-y-1.5">
                            <li v-for="col in optionalColumns" :key="col.key" class="flex gap-2 text-sm text-slate-800">
                                <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs text-slate-700 ring-1 ring-slate-200">{{ col.key }}</code>
                                <span class="text-slate-600">{{ col.note }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <FormField label="CSV file" :error="form.errors.file">
                <div
                    class="relative rounded-2xl border border-dashed px-4 py-8 text-center transition"
                    :class="dragOver
                        ? 'border-teal-700 bg-teal-50/60'
                        : form.file
                            ? 'border-slate-300 bg-white'
                            : 'border-slate-300 bg-slate-50 hover:border-slate-400'"
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <input
                        v-if="!form.file"
                        ref="fileInput"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="absolute inset-0 cursor-pointer opacity-0"
                        @change="pickFile($event.target.files?.[0])"
                    >
                    <template v-if="!form.file">
                        <p class="text-sm font-medium text-slate-900">Drop your CSV here, or click to browse</p>
                        <p class="mt-1 text-xs text-slate-500">Up to 2 MB · .csv only</p>
                    </template>
                    <div v-else class="flex items-center justify-center gap-3">
                        <div class="min-w-0 text-left">
                            <p class="truncate text-sm font-medium text-slate-900">{{ fileName }}</p>
                            <p class="text-xs text-slate-500">{{ fileSize }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-100"
                            @click.prevent="clearFile"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </FormField>

            <div v-if="localErrors.length" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                <p class="text-sm font-semibold text-rose-900">
                    {{ localErrors.length === 1 ? '1 row could not be imported' : `${localErrors.length} rows could not be imported` }}
                </p>
                <ul class="mt-2 max-h-36 space-y-1 overflow-y-auto text-sm text-rose-800">
                    <li v-for="(err, i) in localErrors.slice(0, 20)" :key="i">{{ err }}</li>
                </ul>
                <p v-if="localErrors.length > 20" class="mt-2 text-xs text-rose-700">Showing first 20 errors.</p>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AppButton type="button" variant="secondary" @click="close">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="form.processing || !form.file">
                    {{ form.processing ? 'Importing…' : 'Import members' }}
                </AppButton>
            </div>
        </form>
    </Modal>
</template>
