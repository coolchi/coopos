<script setup>
import PageHeader from '../../../Components/PageHeader.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    journal: { type: Object, required: true },
});

const page = usePage();
const base = page.props.tenantBase ?? `/${page.props.tenant.slug}`;
</script>

<template>
    <Head :title="journal.reference" />
    <PageHeader eyebrow="Journal" :title="journal.reference" :subtitle="journal.description">
        <template #actions>
            <AppButton :href="`${base}/accounts/journals`" variant="secondary">All journals</AppButton>
        </template>
    </PageHeader>

    <p class="mt-4 text-sm text-slate-500">
        {{ journal.posted_at }}
        <template v-if="journal.branch"> · {{ journal.branch }}</template>
        <template v-if="journal.created_by"> · {{ journal.created_by }}</template>
        · {{ journal.status }}
    </p>

    <AppCard class="mt-6" :padded="false">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Account</th>
                    <th class="px-5 py-3">Memo</th>
                    <th class="px-5 py-3 text-right">Debit</th>
                    <th class="px-5 py-3 text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, i) in journal.lines" :key="i" class="border-t border-slate-100">
                    <td class="px-5 py-3">
                        <div class="font-medium">{{ line.account }}</div>
                        <div class="font-mono text-xs text-slate-500">{{ line.code }}</div>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ line.memo || '—' }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ line.debit }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ line.credit }}</td>
                </tr>
                <tr class="border-t border-slate-200 font-semibold">
                    <td class="px-5 py-3" colspan="2">Total</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ journal.debit }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ journal.credit }}</td>
                </tr>
            </tbody>
        </table>
    </AppCard>
</template>
