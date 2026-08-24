<script setup>
import Pagination from '../../Components/Pagination.vue';
import EmptyState from '../../Components/EmptyState.vue';
import ConfirmModal from '../../Components/ConfirmModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ announcements: Object });
const form = useForm({ title: '', body: '' });
const removing = ref(null);
const removeProcessing = ref(false);
const removeMessage = computed(() => (
    removing.value
        ? `Remove “${removing.value.title}”? Cooperatives will no longer see it.`
        : ''
));

const confirmRemove = () => {
    if (! removing.value) {
        return;
    }
    removeProcessing.value = true;
    router.delete(`/platform/announcements/${removing.value.uuid}`, {
        onFinish: () => {
            removeProcessing.value = false;
            removing.value = null;
        },
    });
};
</script>

<template>
            <Head title="Announcements" />
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h1 class="text-3xl font-semibold tracking-tight">Announcements</h1>
                <p class="mt-2 text-sm text-slate-500">Broadcasts appear in every cooperative workspace.</p>
                <EmptyState v-if="!announcements.data.length" icon="announcements" title="No announcements yet" body="Publish a note for every society on CoopOS." class="mt-6 rounded-3xl bg-white shadow-sm ring-1 ring-black/5" />
                <div v-else class="mt-6 space-y-4">
                    <article v-for="item in announcements.data" :key="item.uuid" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                        <div class="flex items-start justify-between gap-4">
                            <h2 class="font-semibold">{{ item.title }}</h2>
                            <button type="button" class="text-xs text-rose-700" @click="removing = item">Remove</button>
                        </div>
                        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ item.body }}</p>
                        <p class="mt-3 text-xs text-slate-400">{{ item.published_at }}</p>
                    </article>
                </div>
                <Pagination :links="announcements.links" :from="announcements.from" :to="announcements.to" :total="announcements.total" />
            </div>
            <form class="h-fit rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="form.post('/platform/announcements', { onSuccess: () => form.reset() })">
                <h2 class="text-lg font-semibold">Broadcast</h2>
                <input v-model="form.title" required maxlength="160" placeholder="Title" class="mt-4 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                <p v-if="form.errors.title" class="mt-1 text-xs text-rose-700">{{ form.errors.title }}</p>
                <textarea v-model="form.body" required maxlength="5000" rows="6" placeholder="Message to every cooperative" class="mt-3 w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm"></textarea>
                <p v-if="form.errors.body" class="mt-1 text-xs text-rose-700">{{ form.errors.body }}</p>
                <button class="mt-4 w-full rounded-2xl bg-[#071c1b] py-2.5 text-sm text-white" :disabled="form.processing">Publish</button>
            </form>
        </div>

        <ConfirmModal
            :show="Boolean(removing)"
            title="Remove announcement"
            :message="removeMessage"
            confirm-label="Remove"
            variant="danger"
            :processing="removeProcessing"
            @close="removing = null"
            @confirm="confirmRemove"
        />
</template>
