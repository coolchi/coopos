<script setup>
import AppCard from '../../Components/AppCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    announcements: { type: Array, default: () => [] },
});

const page = usePage();
const base = computed(() => page.props.tenantBase ?? `/${page.props.tenant?.slug}`);
</script>

<template>
    <Head title="Announcements" />
        <Link :href="`${base}/portal`" class="text-sm font-medium text-teal-800">Back to home</Link>
        <div class="mt-3">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">Member portal</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Announcements</h1>
        </div>

        <div v-if="!announcements.length" class="mt-8 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5">
            <EmptyState
                icon="announcements"
                title="No announcements yet"
                body="Your cooperative has not posted a note yet. Check back after the next meeting."
            />
        </div>
        <div v-else class="mt-6 space-y-4">
            <article
                v-for="a in announcements"
                :key="a.uuid"
                class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5"
                :class="a.is_pinned ? 'ring-[#d4b06a]/40' : ''"
            >
                <div v-if="a.is_pinned" class="h-1 bg-[#d4b06a]"></div>
                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-slate-900">{{ a.title }}</h2>
                                <StatusBadge v-if="a.is_pinned" value="pinned" />
                                <span v-if="a.is_platform" class="text-[11px] uppercase tracking-wide text-slate-400">CoopOS</span>
                            </div>
                            <p v-if="a.published_at" class="mt-1 text-xs text-slate-400">{{ a.published_at }}</p>
                        </div>
                    </div>
                    <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-600">{{ a.body }}</p>
                </div>
            </article>
        </div>
</template>
