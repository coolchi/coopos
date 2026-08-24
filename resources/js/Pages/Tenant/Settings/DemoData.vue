<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import ConfirmModal from '../../../Components/ConfirmModal.vue';
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    app_mode: { type: String, default: 'demo' },
    is_demo: { type: Boolean, default: true },
    seeded_total: { type: Number, default: 0 },
    features: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
const confirmReset = ref(false);
const confirmResetAll = ref(false);

const modeForm = useForm({ app_mode: props.app_mode });
const seedForm = useForm({
    features: props.features.filter((f) => f.available).map((f) => f.key),
});

const selected = computed(() => seedForm.features);
const available = computed(() => props.features.filter((f) => f.available));
const allSelected = computed(() => available.value.length > 0 && available.value.every((f) => selected.value.includes(f.key)));
const canRun = computed(() => props.is_demo && selected.value.length > 0 && !seedForm.processing && !modeForm.processing);

const toggle = (key) => {
    if (selected.value.includes(key)) {
        seedForm.features = selected.value.filter((item) => item !== key);
        return;
    }
    seedForm.features = [...selected.value, key];
};

const toggleAll = () => {
    seedForm.features = allSelected.value ? [] : available.value.map((f) => f.key);
};

const saveMode = () => modeForm.post(`${base}/settings/demo/mode`);

const seedSelected = () => {
    if (!canRun.value) {
        return;
    }
    seedForm.post(`${base}/settings/demo/seed`);
};

const resetSelected = () => {
    confirmReset.value = false;
    seedForm.post(`${base}/settings/demo/reset`);
};

const resetAll = () => {
    confirmResetAll.value = false;
    seedForm.features = available.value.map((f) => f.key);
    seedForm.post(`${base}/settings/demo/reset`);
};
</script>

<template>
    <Head title="Demo data" />
    <PageHeader eyebrow="Office" title="Demo data" subtitle="Load Nigerian sample records to try every module, then clear them before you go live." />
    <SettingsNav current="demo" />

    <div class="mt-6 space-y-6">
        <AppCard title="App mode">
            <p class="text-sm text-slate-600">Live is for real cooperative books. Demo lets you seed and reset sample data. Switch to Live before members start paying in.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4"
                    :class="modeForm.app_mode === 'demo' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50'"
                >
                    <input v-model="modeForm.app_mode" type="radio" value="demo" class="mt-1">
                    <span>
                        <span class="block font-semibold text-slate-900">Demo</span>
                        <span class="mt-1 block text-sm text-slate-600">Seed, retry, and clear sample members, savings, loans, and books.</span>
                    </span>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4"
                    :class="modeForm.app_mode === 'live' ? 'border-teal-700 bg-teal-50' : 'border-slate-200 bg-slate-50'"
                >
                    <input v-model="modeForm.app_mode" type="radio" value="live" class="mt-1">
                    <span>
                        <span class="block font-semibold text-slate-900">Live</span>
                        <span class="mt-1 block text-sm text-slate-600">Seeding is locked. Use this once the cooperative is operating for real.</span>
                    </span>
                </label>
            </div>
            <div class="mt-4">
                <AppButton type="button" :disabled="modeForm.processing || modeForm.app_mode === app_mode" @click="saveMode">Save mode</AppButton>
            </div>
        </AppCard>

        <AppCard title="Modules">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    {{ seeded_total }} sample record{{ seeded_total === 1 ? '' : 's' }} currently tracked.
                    Names are Nigerian (Yoruba, Igbo, and Hausa).
                </p>
                <button type="button" class="text-sm font-medium text-teal-800" @click="toggleAll">
                    {{ allSelected ? 'Clear selection' : 'Select all' }}
                </button>
            </div>

            <p v-if="!is_demo" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800">
                Seeding is disabled in Live mode. Switch to Demo to load or clear sample data.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label
                    v-for="feature in features"
                    :key="feature.key"
                    class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4"
                    :class="!feature.available || !is_demo ? 'opacity-50' : 'bg-white'"
                >
                    <input
                        type="checkbox"
                        class="mt-1 rounded"
                        :checked="selected.includes(feature.key)"
                        :disabled="!feature.available || !is_demo"
                        @change="toggle(feature.key)"
                    >
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-slate-900">
                            {{ feature.label }}
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ feature.count }}</span>
                        </span>
                        <span class="mt-1 block text-sm text-slate-600">{{ feature.description }}</span>
                        <span v-if="!feature.available" class="mt-1 block text-xs text-slate-500">Not available for this cooperative mode.</span>
                    </span>
                </label>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <AppButton type="button" variant="gold" :disabled="!canRun" @click="seedSelected">Seed selected</AppButton>
                <AppButton type="button" variant="secondary" :disabled="!canRun" @click="confirmReset = true">Reset selected</AppButton>
                <AppButton type="button" variant="danger" :disabled="!is_demo || seeded_total === 0 || seedForm.processing" @click="confirmResetAll = true">Reset all seeded data</AppButton>
            </div>
        </AppCard>
    </div>

    <ConfirmModal
        :show="confirmReset"
        title="Reset selected modules"
        message="This removes only the sample records created by the seeder for the modules you ticked. Live (non-seeded) records stay."
        confirm-label="Reset selected"
        variant="danger"
        :processing="seedForm.processing"
        @close="confirmReset = false"
        @confirm="resetSelected"
    />
    <ConfirmModal
        :show="confirmResetAll"
        title="Reset all seeded data"
        message="This clears every sample record this seeder created in this cooperative. It does not delete your real members, products, or settings."
        confirm-label="Reset all"
        variant="danger"
        :processing="seedForm.processing"
        @close="confirmResetAll = false"
        @confirm="resetAll"
    />
</template>
