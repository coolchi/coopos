import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useCan() {
    const page = usePage();
    const permissions = computed(() => {
        const list = page.props.auth?.user?.permissions;

        return Array.isArray(list) ? list : [];
    });

    const can = (...names) => names.some((name) => permissions.value.includes(name));

    return { can, permissions };
}
