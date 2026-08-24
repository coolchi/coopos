<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import AppButton from './AppButton.vue';
import AmountInput from './AmountInput.vue';
import { parseAmount } from '../money';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Record repayment' },
    subtitle: { type: String, default: '' },
    action: { type: String, default: '' },
    product: { type: String, default: '' },
    amount: { type: [String, Number], default: '' },
    outstanding: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const today = () => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-medium tabular-nums text-slate-900 shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20 text-left';

const form = useForm({
    amount: '',
    channel: 'transfer',
    paid_on: today(),
});

watch(() => props.show, (open) => {
    if (! open) {
        return;
    }
    form.clearErrors();
    form.reset();
    form.amount = props.amount ?? '';
    form.channel = 'transfer';
    form.paid_on = today();
});

const submit = () => {
    if (! props.action) {
        return;
    }
    form.transform((data) => ({
        amount: parseAmount(data.amount),
        channel: data.channel,
        paid_on: data.paid_on,
    })).post(props.action, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => emit('close'),
        onFinish: () => form.transform((data) => data),
    });
};
</script>

<template>
    <Modal :show="show" :title="title" :subtitle="subtitle" :z-index="80" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Product">
                <div class="rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-medium text-slate-900">
                    {{ product || 'Loan' }}
                </div>
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Amount (₦)" :error="form.errors.amount" :hint="outstanding ? `Outstanding ${outstanding}` : ''">
                    <AmountInput v-model="form.amount" :class="input" required />
                </FormField>
                <FormField label="Channel" :error="form.errors.channel">
                    <AppSelect
                        v-model="form.channel"
                        :options="[
                            { value: 'cash', label: 'Cash' },
                            { value: 'transfer', label: 'Transfer' },
                        ]"
                        required
                    />
                </FormField>
                <FormField label="Date" class="sm:col-span-2" :error="form.errors.paid_on">
                    <input v-model="form.paid_on" type="date" :class="input" required>
                </FormField>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <AppButton type="button" variant="secondary" :disabled="form.processing" @click="emit('close')">Cancel</AppButton>
                <AppButton type="submit" variant="gold" :disabled="form.processing">Record repayment</AppButton>
            </div>
        </form>
    </Modal>
</template>
