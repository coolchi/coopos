<script setup>
import Modal from './Modal.vue';
import AppButton from './AppButton.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'primary' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" :title="title" @close="emit('close')">
        <p class="text-sm text-slate-600">{{ message }}</p>
        <div class="mt-6 flex justify-end gap-2">
            <AppButton v-if="cancelLabel" type="button" variant="secondary" :disabled="processing" @click="emit('close')">{{ cancelLabel }}</AppButton>
            <AppButton type="button" :variant="variant" :disabled="processing" @click="emit('confirm')">{{ confirmLabel }}</AppButton>
        </div>
    </Modal>
</template>
