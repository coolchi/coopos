import { computed, ref, unref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useLoanEligibility(memberUuid, amount, options = {}) {
    const page = usePage();
    const checks = ref([]);
    const loading = ref(false);
    const passed = computed(() => checks.value.length > 0 && checks.value.every((check) => check.pass));
    const ready = computed(() => checks.value.length > 0);
    let timer = null;
    let seq = 0;

    const refresh = async () => {
        const uuid = memberUuid == null ? '' : unref(typeof memberUuid === 'function' ? memberUuid() : memberUuid);
        if (! options.portal && ! uuid) {
            checks.value = [];
            loading.value = false;
            return;
        }

        const amt = Number(String(unref(typeof amount === 'function' ? amount() : amount) || '0').replace(/,/g, '')) || 0;
        const base = page.props.tenantBase ?? `/${page.props.tenant?.slug || ''}`;
        const params = new URLSearchParams({ amount: String(amt) });
        if (uuid) {
            params.set('member_uuid', uuid);
        }
        const path = options.portal ? `${base}/portal/apply/eligibility` : `${base}/loans/eligibility`;
        const id = ++seq;
        loading.value = true;

        try {
            const response = await fetch(`${path}?${params}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (id !== seq) {
                return;
            }
            const data = await response.json();
            checks.value = Array.isArray(data.checks) ? data.checks : [];
        } catch {
            if (id === seq) {
                checks.value = [];
            }
        } finally {
            if (id === seq) {
                loading.value = false;
            }
        }
    };

    watch(
        () => [
            memberUuid == null ? '' : unref(typeof memberUuid === 'function' ? memberUuid() : memberUuid),
            unref(typeof amount === 'function' ? amount() : amount),
        ],
        () => {
            clearTimeout(timer);
            timer = setTimeout(refresh, 250);
        },
        { immediate: true },
    );

    return { checks, loading, passed, ready, refresh };
}
