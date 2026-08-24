<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import SettingsNav from '../../../Components/SettingsNav.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import AmountInput from '../../../Components/AmountInput.vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ tenant: Object, settings: Object, states: Array, themes: { type: Array, default: () => [] } });
const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const form = useForm({
    name: props.tenant.name,
    legal_name: props.tenant.legal_name || '',
    slogan: props.tenant.slogan || '',
    registration_number: props.tenant.registration_number || '',
    phone: props.tenant.phone || '',
    email: props.tenant.email || '',
    address: props.tenant.address || '',
    city: props.tenant.city || '',
    state: props.tenant.state || '',
    lga: props.tenant.lga || '',
    theme: props.tenant.theme || 'teal',
    logo: null,
    remove_logo: false,
    membership_number_prefix: props.settings?.membership_number_prefix || 'M',
    membership_fee: (props.settings?.membership_fee_kobo || 0) / 100,
    currency: props.settings?.currency || 'NGN',
    fiscal_year_start_month: props.settings?.fiscal_year_start_month || 1,
    fiscal_year_one_start_year: props.settings?.fiscal_year_one_start_year || new Date().getFullYear(),
    monipay_secret: '',
    monipay_public: props.settings?.monipay_public || '',
    transaction_access_code: '',
    allow_manual_payments: Boolean(props.settings?.allow_manual_payments ?? true),
});

const input = 'w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-700/50 focus:ring-2 focus:ring-teal-800/15 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';
const logoPreview = ref(props.tenant.logo_url || '');
const logoInput = ref(null);
const logoDragging = ref(false);
let objectUrl = null;

const showLogo = computed(() => Boolean(logoPreview.value) && !form.remove_logo);
const logoInitial = computed(() => (form.name || 'C').charAt(0).toUpperCase());
const selectedTheme = computed(() => (props.themes || []).find((item) => item.key === form.theme));
const themeBrand = computed(() => selectedTheme.value?.brand || props.tenant.brand_color || '#0f766e');

const revokePreview = () => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
};

const acceptLogo = (file) => {
    if (!file || !file.type.startsWith('image/')) {
        return;
    }
    form.logo = file;
    form.remove_logo = false;
    revokePreview();
    objectUrl = URL.createObjectURL(file);
    logoPreview.value = objectUrl;
};

const onLogoChange = (event) => {
    acceptLogo(event.target.files?.[0] || null);
};

const onLogoDrop = (event) => {
    logoDragging.value = false;
    acceptLogo(event.dataTransfer?.files?.[0] || null);
};

const pickLogo = () => logoInput.value?.click();

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    revokePreview();
    logoPreview.value = '';
    if (logoInput.value) {
        logoInput.value.value = '';
    }
};

onBeforeUnmount(revokePreview);

const save = () => form.post(`${base}/settings`, { forceFormData: true });

const monthName = (m) => months.find((row) => row.value === Number(m))?.label || 'Month';

const endMonth = computed(() => {
    const start = Number(form.fiscal_year_start_month) || 1;
    return start === 1 ? 12 : start - 1;
});

const yearOnePreview = computed(() => {
    const start = Number(form.fiscal_year_start_month) || 1;
    const year = Number(form.fiscal_year_one_start_year) || new Date().getFullYear();
    const endYear = start === 1 ? year : year + 1;
    const label = start === 1 ? String(year) : `${year}/${endYear}`;
    return `Year 1 · ${monthName(start)} – ${monthName(endMonth.value)} · ${label}`;
});

const currentPreview = computed(() => {
    const start = Number(form.fiscal_year_start_month) || 1;
    const yearOne = Number(form.fiscal_year_one_start_year) || new Date().getFullYear();
    const now = new Date();
    const currentStartYear = now.getMonth() + 1 >= start ? now.getFullYear() : now.getFullYear() - 1;
    const yearNumber = Math.max(1, currentStartYear - yearOne + 1);
    const endYear = start === 1 ? currentStartYear : currentStartYear + 1;
    const label = start === 1 ? String(currentStartYear) : `${currentStartYear}/${endYear}`;
    return `Year ${yearNumber} ${label}`;
});
</script>

<template>
    <Head title="Organization" />
    <PageHeader eyebrow="Office" title="Organization" subtitle="Identity, contact, numbering, and how this cooperative is known.">
        <template #actions>
            <AppButton type="submit" form="org-settings" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save' }}
            </AppButton>
        </template>
    </PageHeader>
    <SettingsNav current="organization" />

    <form id="org-settings" class="mt-6 space-y-6" @submit.prevent="save">
        <AppCard title="Identity" description="Name, logo, and colour used in the menu, portal, and sign-in.">
            <div class="space-y-5">
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div>
                        <input
                            ref="logoInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                            class="sr-only"
                            @change="onLogoChange"
                        >
                        <button
                            type="button"
                            class="flex w-full items-center gap-4 rounded-2xl border-2 border-dashed px-4 py-3 text-left transition"
                            :class="logoDragging
                                ? 'border-teal-600 bg-teal-50'
                                : (form.errors.logo ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-white')"
                            @click="pickLogo"
                            @dragover.prevent="logoDragging = true"
                            @dragleave.prevent="logoDragging = false"
                            @drop.prevent="onLogoDrop"
                        >
                            <div
                                class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5"
                                :style="showLogo ? {} : { backgroundColor: themeBrand }"
                            >
                                <img v-if="showLogo" :src="logoPreview" alt="" class="h-full w-full object-contain">
                                <span v-else class="text-xl font-semibold text-white">{{ logoInitial }}</span>
                            </div>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800">{{ showLogo ? 'Replace logo' : 'Upload logo' }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">PNG, JPG or WebP. Drag in or click to choose.</span>
                            </span>
                        </button>
                        <p v-if="form.errors.logo" class="mt-2 text-xs font-medium text-rose-700">{{ form.errors.logo }}</p>
                        <button
                            v-if="showLogo"
                            type="button"
                            class="mt-2 text-xs font-medium text-slate-500 hover:text-rose-700"
                            @click="removeLogo"
                        >Remove logo</button>
                    </div>
                    <FormField
                        label="Slogan"
                        hint="Shown under the name in the menu. Leave blank to keep the cooperative type."
                    >
                        <input
                            v-model="form.slogan"
                            :class="input"
                            maxlength="80"
                            :placeholder="page.props.tenant?.type_label || 'Islamic Cooperative Society'"
                        >
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Trading name">
                        <input v-model="form.name" :class="input" required>
                    </FormField>
                    <FormField label="Legal name">
                        <input v-model="form.legal_name" :class="input">
                    </FormField>
                    <FormField label="Registration number">
                        <input v-model="form.registration_number" :class="input">
                    </FormField>
                    <FormField label="Theme" hint="Colours for the menu, portal, and buttons.">
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button
                                v-for="item in themes"
                                :key="item.key"
                                type="button"
                                class="h-10 w-10 rounded-full shadow-sm ring-2 ring-offset-2 transition"
                                :class="form.theme === item.key ? 'ring-slate-900' : 'ring-transparent hover:ring-slate-300'"
                                :style="{ background: item.brand }"
                                :title="item.label"
                                :aria-label="item.label"
                                :aria-pressed="form.theme === item.key"
                                @click="form.theme = item.key"
                            />
                        </div>
                    </FormField>
                </div>
            </div>
        </AppCard>

        <div class="grid gap-6 xl:grid-cols-2">
            <AppCard title="Contact" description="How members and partners reach this cooperative.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Email"><input v-model="form.email" type="email" :class="input"></FormField>
                    <FormField label="Phone"><input v-model="form.phone" :class="input"></FormField>
                    <FormField label="Address" class="sm:col-span-2"><input v-model="form.address" :class="input"></FormField>
                    <FormField label="City"><input v-model="form.city" :class="input"></FormField>
                    <FormField label="State">
                        <AppSelect v-model="form.state" :options="states" />
                    </FormField>
                    <FormField label="LGA"><input v-model="form.lga" :class="input"></FormField>
                </div>
            </AppCard>

            <AppCard title="Numbering" description="Membership numbers, joining fee, and currency.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Membership number prefix" hint="Next number will be this prefix plus a running count.">
                        <input v-model="form.membership_number_prefix" :class="input">
                    </FormField>
                    <FormField label="Membership fee (₦)"><AmountInput v-model="form.membership_fee" :class="input" /></FormField>
                    <FormField label="Currency" class="sm:col-span-2"><input v-model="form.currency" :class="input"></FormField>
                </div>
            </AppCard>
        </div>

        <AppCard title="Financial year" description="Set when your books open and close. March to February is a common cooperative year.">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Starts in">
                        <AppSelect v-model="form.fiscal_year_start_month" :options="months" />
                    </FormField>
                    <FormField label="Ends in" hint="Derived from the start month.">
                        <input :value="monthName(endMonth)" :class="input" disabled>
                    </FormField>
                    <FormField
                        label="Year 1 starts"
                        hint="Calendar year when your first financial year began."
                        class="sm:col-span-2"
                    >
                        <input v-model="form.fiscal_year_one_start_year" type="number" min="1990" max="2100" :class="input" required>
                    </FormField>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700 ring-1 ring-slate-100">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Preview</p>
                    <p class="mt-2">{{ yearOnePreview }}</p>
                    <p class="mt-2 font-medium text-teal-900">Current: {{ currentPreview }}</p>
                </div>
            </div>
        </AppCard>

        <AppCard title="Payments" description="Bank transfers, cash posting, and the code that protects edits.">
            <div class="space-y-6">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Monipay</h3>
                    <p class="mt-1 text-sm text-slate-500">Virtual accounts for member bank transfers. Webhook: <span class="font-medium text-slate-700">/api/webhooks/monipay</span></p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <FormField label="Secret key" hint="Leave blank to keep the current key.">
                            <input v-model="form.monipay_secret" type="password" autocomplete="off" :placeholder="settings.monipay_secret_set ? 'Key saved' : 'sk_…'" :class="input">
                        </FormField>
                        <FormField label="Public key">
                            <input v-model="form.monipay_public" autocomplete="off" placeholder="pub_…" :class="input">
                        </FormField>
                    </div>
                </div>

                <div v-if="settings.can_set_manual_payments" class="border-t border-slate-100 pt-6">
                    <h3 class="text-sm font-semibold text-slate-900">Manual payments</h3>
                    <label class="mt-3 flex cursor-pointer items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3.5 text-sm ring-1 ring-slate-100">
                        <span class="min-w-0">
                            <span class="font-medium text-slate-900">Allow officers to record payments</span>
                            <span class="mt-1 block text-slate-500">Lets staff post cash and transfers. Off means bank transfers only.</span>
                        </span>
                        <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0">
                            <input v-model="form.allow_manual_payments" type="checkbox" class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-[#071c1b] peer-focus-visible:ring-2 peer-focus-visible:ring-teal-800/25 peer-focus-visible:ring-offset-2"></span>
                            <span class="pointer-events-none absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                </div>

                <div v-if="settings.can_set_transaction_access_code" class="border-t border-slate-100 pt-6">
                    <h3 class="text-sm font-semibold text-slate-900">Transaction access code</h3>
                    <p class="mt-1 text-sm text-slate-500">Needed to edit or delete a contribution. Leave blank to keep the current code.</p>
                    <div class="mt-4 max-w-md">
                        <FormField label="Access code" :hint="settings.transaction_access_code_set ? 'A code is already set.' : 'Default until set: 1234'">
                            <input v-model="form.transaction_access_code" type="password" autocomplete="new-password" placeholder="••••" :class="input">
                        </FormField>
                    </div>
                </div>
            </div>
        </AppCard>

        <div
            v-if="form.isDirty || form.processing"
            class="sticky bottom-20 z-20 flex justify-end print:hidden lg:bottom-4"
        >
            <div class="flex items-center gap-3 rounded-full bg-white/95 py-1.5 pl-4 pr-1.5 shadow-lg ring-1 ring-black/5 backdrop-blur">
                <p class="text-xs font-medium text-amber-800">Unsaved changes</p>
                <AppButton type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save organization' }}
                </AppButton>
            </div>
        </div>
    </form>
</template>
