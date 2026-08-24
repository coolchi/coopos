import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useTenantBase() {
    const page = usePage();

    return computed(() => page.props.tenantBase ?? (page.props.tenant?.slug ? `/${page.props.tenant.slug}` : ''));
}
