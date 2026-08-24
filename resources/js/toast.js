import { reactive } from 'vue';

const state = reactive({
    items: [],
});

let seq = 0;
let lastKey = '';
let lastAt = 0;

const push = (type, message, duration = 4200) => {
    const text = String(message || '').trim();
    if (! text) {
        return;
    }

    const now = Date.now();
    const key = `${type}:${text}`;
    if (key === lastKey && now - lastAt < 1000) {
        return;
    }
    lastKey = key;
    lastAt = now;

    const id = ++seq;
    state.items.push({ id, type, message: text, duration });

    if (state.items.length > 4) {
        state.items.shift();
    }

    return id;
};

export const toast = {
    state,
    success: (message) => push('success', message),
    error: (message) => push('error', message),
    info: (message) => push('info', message),
    dismiss: (id) => {
        state.items = state.items.filter((item) => item.id !== id);
    },
};

export const showFlashToasts = (flash) => {
    if (! flash) {
        return;
    }
    if (flash.success) {
        toast.success(flash.success);
    }
    if (flash.error) {
        toast.error(flash.error);
    }
};
