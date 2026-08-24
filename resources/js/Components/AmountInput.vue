<script setup>
import { computed, nextTick } from 'vue';
import { formatAmount, parseAmount } from '../money';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    id: { type: String, default: '' },
    name: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'blur', 'change']);

const display = computed(() => formatAmount(props.modelValue));

const restoreCursor = (el, digitsBefore) => {
    const formatted = el.value;
    let pos = 0;
    let seen = 0;
    while (pos < formatted.length && seen < digitsBefore) {
        if (/[\d.]/.test(formatted[pos])) {
            seen += 1;
        }
        pos += 1;
    }
    el.setSelectionRange(pos, pos);
};

const onInput = (event) => {
    const el = event.target;
    const cursor = el.selectionStart ?? el.value.length;
    const digitsBefore = el.value.slice(0, cursor).replace(/[^\d.]/g, '').length;
    emit('update:modelValue', parseAmount(el.value));
    nextTick(() => restoreCursor(el, digitsBefore));
};
</script>

<template>
    <input
        :id="id || undefined"
        :name="name || undefined"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        :value="display"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        :placeholder="placeholder"
        class="tabular-nums"
        @input="onInput"
        @blur="$emit('blur', $event)"
        @change="$emit('change', $event)"
    >
</template>
