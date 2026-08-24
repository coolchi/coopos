<script setup>
import AppCard from './AppCard.vue';
import AppButton from './AppButton.vue';
import AppSelect from './AppSelect.vue';
import AmountInput from './AmountInput.vue';
import ConfirmModal from './ConfirmModal.vue';
import EmptyState from './EmptyState.vue';
import FormField from './FormField.vue';
import Modal from './Modal.vue';
import MoneyAmount from './MoneyAmount.vue';
import { computed, ref, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { parseAmount } from '../money';

const props = defineProps({
    mandates: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`;
const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium tabular-nums text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20 text-left';

const formOpen = ref(false);
const editing = ref(null);
const pendingRemove = ref(null);
const removing = ref(false);

const form = useForm({
    product_uuid: '',
    amount: '',
});

const usedProductUuids = computed(() => new Set((props.mandates || []).map((plan) => plan.product_uuid).filter(Boolean)));
const visibleMandates = computed(() => (props.mandates || []).filter((plan) => ! plan.is_required));

const optionalProducts = computed(() => (props.products || []).filter((product) => ! product.is_required));

const availableProducts = computed(() => optionalProducts.value.filter((product) => {
    if (editing.value?.product_uuid === product.uuid) {
        return true;
    }

    return ! usedProductUuids.value.has(product.uuid);
}));

const productOptions = computed(() => availableProducts.value.map((product) => ({
    value: product.uuid,
    label: product.name,
})));

const selectedProduct = computed(() => (props.products || []).find((product) => product.uuid === form.product_uuid));
const amountLocked = computed(() => Boolean(selectedProduct.value?.amount_is_fixed || editing.value?.amount_is_fixed));
const canAdd = computed(() => availableProducts.value.length > 0 && ! editing.value);
const isEdit = computed(() => Boolean(editing.value));
const modalTitle = computed(() => (isEdit.value ? 'Edit mandate' : 'Add mandate'));

const applyProductDefaults = (uuid) => {
    const product = (props.products || []).find((item) => item.uuid === uuid);
    if (! product) {
        return;
    }
    if (product.default_amount != null && product.default_amount !== '') {
        form.amount = product.default_amount;
    }
};

watch(() => form.product_uuid, (uuid, previous) => {
    if (! formOpen.value || isEdit.value || ! uuid || uuid === previous) {
        return;
    }
    applyProductDefaults(uuid);
});

const closeForm = () => {
    formOpen.value = false;
    editing.value = null;
    form.transform((data) => data);
    form.reset();
    form.clearErrors();
};

const openAdd = () => {
    const first = availableProducts.value[0];
    editing.value = null;
    form.product_uuid = first?.uuid || '';
    form.amount = first?.default_amount ?? '';
    form.clearErrors();
    formOpen.value = true;
};

const openEdit = (plan) => {
    editing.value = plan;
    form.product_uuid = plan.product_uuid;
    form.amount = plan.amount_naira ?? '';
    form.clearErrors();
    formOpen.value = true;
};

const submit = () => {
    const payload = {
        preserveScroll: true,
        onSuccess: closeForm,
    };

    if (isEdit.value) {
        form.transform((data) => ({ amount: parseAmount(data.amount) || 0 }))
            .patch(`${base}/portal/mandates/${editing.value.uuid}`, payload);

        return;
    }

    form.transform((data) => ({
        product_uuid: data.product_uuid,
        amount: parseAmount(data.amount) || 0,
    })).post(`${base}/portal/mandates`, payload);
};

const confirmRemove = () => {
    if (! pendingRemove.value) {
        return;
    }
    removing.value = true;
    router.delete(`${base}/portal/mandates/${pendingRemove.value.uuid}`, {
        preserveScroll: true,
        onFinish: () => {
            removing.value = false;
            pendingRemove.value = null;
        },
    });
};
</script>

<template>
    <AppCard class="mt-8" title="Mandates" :padded="false">
        <template #header>
            <button
                v-if="optionalProducts.length"
                type="button"
                class="text-sm font-medium text-teal-800 hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
                :disabled="!canAdd"
                @click="openAdd"
            >
                Add mandate
            </button>
        </template>
        <EmptyState
            v-if="!visibleMandates.length"
            compact
            icon="savings"
            title="No mandates yet"
            body="Add a mandate to set how much you save on a product each month."
        />
        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="plan in visibleMandates"
                :key="plan.uuid"
                class="flex items-center gap-2 px-5 py-4 sm:gap-4"
            >
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold text-slate-900">{{ plan.product }}</div>
                    <div v-if="plan.starts_on" class="mt-0.5 text-xs text-slate-500">From {{ plan.starts_on }}</div>
                </div>
                <div class="shrink-0 font-semibold tabular-nums text-slate-900">
                    <MoneyAmount :value="plan.amount" />
                </div>
                <div class="flex shrink-0 items-center">
                    <button
                        v-if="plan.product_uuid && !plan.amount_is_fixed"
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-50 hover:text-teal-800"
                        title="Edit mandate"
                        aria-label="Edit mandate"
                        @click="openEdit(plan)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-rose-600 hover:bg-rose-50"
                        title="Remove mandate"
                        aria-label="Remove mandate"
                        @click="pendingRemove = plan"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </li>
        </ul>
    </AppCard>

    <Modal :show="formOpen" :title="modalTitle" @close="closeForm">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Product" :error="form.errors.product_uuid">
                <AppSelect
                    v-if="!isEdit"
                    v-model="form.product_uuid"
                    placeholder="Select product"
                    :options="productOptions"
                    required
                    size="sm"
                />
                <p v-else class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-medium text-slate-900">
                    {{ editing.product }}
                </p>
            </FormField>
            <FormField label="Amount (₦)" :error="form.errors.amount">
                <AmountInput
                    v-model="form.amount"
                    :readonly="amountLocked"
                    :class="[input, amountLocked ? 'bg-slate-100 text-slate-700' : ''].join(' ')"
                    required
                />
            </FormField>
            <div class="flex justify-end gap-2 pt-1">
                <AppButton type="button" variant="secondary" @click="closeForm">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="form.processing">
                    {{ isEdit ? 'Save mandate' : 'Add mandate' }}
                </AppButton>
            </div>
        </form>
    </Modal>

    <ConfirmModal
        :show="Boolean(pendingRemove)"
        title="Remove mandate"
        :message="pendingRemove ? `Remove your ${pendingRemove.product} mandate? You can add it again later.` : ''"
        confirm-label="Remove"
        variant="danger"
        :processing="removing"
        @close="pendingRemove = null"
        @confirm="confirmRemove"
    />
</template>
