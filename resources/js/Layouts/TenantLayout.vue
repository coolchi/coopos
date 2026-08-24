<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import NotificationBell from '../Components/NotificationBell.vue';
import { useCan } from '../composables/useCan';

const page = usePage();
const { can } = useCan();
const tenant = computed(() => page.props.tenant);
const user = computed(() => page.props.auth.user);
const terms = computed(() => page.props.terminology || {});
const base = computed(() => page.props.tenantBase ?? (tenant.value?.slug ? `/${tenant.value.slug}` : ''));
const counts = computed(() => page.props.navCounts || {});
const leave = useForm({});
const logout = useForm({});
const switchBranchForm = useForm({ branch_uuid: '' });
const switchFiscalYearForm = useForm({ year: '' });
const query = ref('');
const mobileOpen = ref(false);
const profileOpen = ref(false);
const profileRef = ref(null);
const branchOpen = ref(false);
const branchRef = ref(null);
const fiscalYearOpen = ref(false);
const fiscalYearRef = ref(null);
const menuOpen = ref({});
const current = computed(() => page.url.split('?')[0]);

const tenantTag = computed(() => {
    const slogan = String(tenant.value?.slogan || '').trim();
    if (slogan) {
        return slogan;
    }
    const type = tenant.value?.type_label || tenant.value?.type || '';
    const mode = tenant.value?.mode_label || tenant.value?.mode || '';
    if (! type) {
        return mode;
    }
    if (! mode || tenant.value?.type === tenant.value?.mode || type.toLowerCase().includes(mode.toLowerCase())) {
        return type;
    }

    return `${type} · ${mode}`;
});

const accessibleBranches = computed(() => page.props.accessible_branches || []);
const canSwitchBranch = computed(() => accessibleBranches.value.length > 1);
const canViewAllBranches = computed(() => Boolean(page.props.can_view_all_branches));
const branchName = computed(() => {
    if (page.props.branch?.name) {
        return page.props.branch.name;
    }
    if (canSwitchBranch.value && canViewAllBranches.value) {
        return 'All branches';
    }

    return accessibleBranches.value[0]?.name || 'CENTRAL BRANCH';
});

const financialYear = computed(() => page.props.financial_year || null);
const fiscalYearOptions = computed(() => financialYear.value?.options || []);
const canSwitchFiscalYear = computed(() => fiscalYearOptions.value.length > 1);

const initials = computed(() => {
    const parts = (user.value?.name || '').trim().split(/\s+/).filter(Boolean);

    return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
});

defineProps({
    title: { type: String, default: '' },
});

const icons = {
    dashboard: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    branches: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    members: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    savings: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
    withdrawals: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
    loans: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    islamic: 'M12 3v2.25m6.364 1.386l-1.591 1.591M21 12h-2.25m-1.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z',
    approvals: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    reports: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    products: 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
    announcements: 'M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A17.696 17.696 0 013 12c0-.83.112-1.633.322-2.396C3.806 8.756 4.63 8.25 5.51 8.25H6.75z',
    support: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z',
    settings: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    home: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    billing: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
    opening: 'M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.354-.059.709-.09 1.07-.09h9.86c.361 0 .716.031 1.07.09m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-1.07-.09H5.57a2.246 2.246 0 00-1.07.09m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122',
};

const groups = computed(() => {
    const money = [];
    if (can('contributions.view')) {
        money.push({ href: `${base.value}/contributions`, label: 'Contributions', icon: 'savings' });
        money.push({ href: `${base.value}/contributions/enrollments`, label: 'Mandates', icon: 'members' });
    }
    if (page.props.can_manage_opening_balances || (can('contributions.view') && ! page.props.opening_balances_locked) || can('settings.manage')) {
        money.push({
            href: `${base.value}/opening-balances`,
            label: page.props.opening_balances_locked ? 'Brought forward' : 'Opening balances',
            icon: 'opening',
        });
    }
    if (can('finance.view')) {
        money.push({
            href: `${base.value}/accounts`,
            label: 'Accounts',
            icon: 'billing',
            children: [
                { href: `${base.value}/accounts`, label: 'Chart of accounts' },
                { href: `${base.value}/accounts/expenses`, label: 'Expenses' },
                { href: `${base.value}/accounts/income`, label: 'Income' },
                { href: `${base.value}/accounts/journals`, label: 'Journals' },
            ],
        });
    }
    if (can('withdrawals.view')) {
        money.push({ href: `${base.value}/withdrawals`, label: 'Withdrawals', icon: 'withdrawals' });
    }
    if (tenant.value?.offers_conventional && can('loans.view')) {
        money.push({ href: `${base.value}/loans`, label: terms.value.loans || 'Loans', icon: 'loans' });
    }
    if (tenant.value?.offers_islamic && can('islamic.view')) {
        money.push({ href: `${base.value}/loan`, label: terms.value.loans || 'Loans', icon: 'loans' });
    }

    const operations = [
        can('dashboard.view') ? { href: `${base.value}/dashboard`, label: 'Dashboard', icon: 'dashboard' } : null,
        can('branches.view') ? { href: `${base.value}/branches`, label: 'Branches', icon: 'branches' } : null,
        can('members.view') ? { href: `${base.value}/members`, label: 'Members', icon: 'members' } : null,
        can('products.view', 'products.manage') ? { href: `${base.value}/products`, label: 'Products', icon: 'products' } : null,
        ...money,
        can('approvals.act') ? { href: `${base.value}/approvals`, label: 'Approvals', icon: 'approvals', badge: counts.value.pending_approvals } : null,
    ].filter(Boolean);

    const office = [
        can('reports.view') ? {
            href: `${base.value}/reports`,
            label: 'Reports',
            icon: 'reports',
            children: [
                { id: 'overview', href: `${base.value}/reports`, label: 'Overview' },
                { id: 'profit-loss', href: `${base.value}/reports/profit-loss`, label: 'Profit & loss' },
                { id: 'balance-sheet', href: `${base.value}/reports/accounts`, label: 'Balance sheet' },
                { id: 'trial-balance', href: `${base.value}/reports/trial-balance`, label: 'Trial balance' },
                { id: 'ledger', href: `${base.value}/reports/ledger`, label: 'General ledger' },
                { id: 'statement', href: `${base.value}/reports/statement`, label: 'Member statement' },
            ],
        } : null,
        can('attendance.view') ? { href: `${base.value}/attendance`, label: 'Attendance', icon: 'members' } : null,
        can('communication.send') ? { href: `${base.value}/communication`, label: 'Announcements', icon: 'announcements' } : null,
        can('support.manage') ? { href: `${base.value}/support`, label: 'Support', icon: 'support' } : null,
        can('settings.manage') ? { href: `${base.value}/settings`, label: 'Settings', icon: 'settings' } : null,
    ].filter(Boolean);

    return [
        operations.length ? { label: 'Operations', items: operations } : null,
        office.length ? { label: 'Office', items: office } : null,
    ].filter(Boolean);
});

const mobileTabs = computed(() => [
    { href: `${base.value}/dashboard`, label: 'Home', icon: 'home', show: can('dashboard.view') },
    { href: `${base.value}/members`, label: 'Members', icon: 'members', show: can('members.view') },
    { href: `${base.value}/contributions`, label: 'Contributions', icon: 'savings', show: can('contributions.view') },
    { href: `${base.value}/reports`, label: 'Reports', icon: 'reports', show: can('reports.view') },
].filter((tab) => tab.show));

const allHrefs = computed(() => groups.value.flatMap((g) => g.items.flatMap((i) => [
    i.href,
    ...(i.children || []).map((child) => child.href),
])));

const isActive = (href) => {
    const path = current.value.replace(/\/$/, '') || '/';
    const target = href.replace(/\/$/, '');
    if (path === target) {
        return true;
    }
    const siblings = allHrefs.value.filter((h) => h !== href && h.startsWith(`${href}/`));
    if (siblings.some((s) => path === s || path.startsWith(`${s}/`))) {
        return false;
    }
    return path.startsWith(`${target}/`);
};

const isSectionActive = (item) => {
    if (item.children?.some((child) => isActive(child.href))) {
        return true;
    }

    return isActive(item.href);
};

const isMenuOpen = (item) => menuOpen.value[item.href] ?? isSectionActive(item);

const toggleMenu = (item) => {
    menuOpen.value = { ...menuOpen.value, [item.href]: !isMenuOpen(item) };
};

const openMenu = (item) => {
    menuOpen.value = { ...menuOpen.value, [item.href]: true };
};

const search = () => {
    router.get(`${base.value}/members`, { q: query.value || undefined }, { preserveState: true });
    mobileOpen.value = false;
};

const switchBranch = (uuid) => {
    branchOpen.value = false;
    if ((page.props.branch?.uuid || 'all') === (uuid || 'all')) {
        return;
    }
    switchBranchForm.branch_uuid = uuid || 'all';
    switchBranchForm.post(`${base.value}/current-branch`, { preserveScroll: true });
};

const switchFiscalYear = (year) => {
    fiscalYearOpen.value = false;
    if (Number(financialYear.value?.year) === Number(year)) {
        return;
    }
    switchFiscalYearForm.year = year;
    switchFiscalYearForm.post(`${base.value}/current-fiscal-year`, { preserveScroll: true });
};

const signOut = () => {
    profileOpen.value = false;
    logout.post('/logout');
};

const onDocumentClick = (event) => {
    if (profileRef.value && ! profileRef.value.contains(event.target)) {
        profileOpen.value = false;
    }
    if (branchRef.value && ! branchRef.value.contains(event.target)) {
        branchOpen.value = false;
    }
    if (fiscalYearRef.value && ! fiscalYearRef.value.contains(event.target)) {
        fiscalYearOpen.value = false;
    }
};

watch(mobileOpen, (open) => {
    document.body.classList.toggle('overflow-hidden', open);
});

onMounted(() => document.addEventListener('click', onDocumentClick));

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <Head v-if="title" :title="title" />
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/40 print:hidden lg:hidden" @click="mobileOpen = false"></div>
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[min(18rem,88vw)] flex-col bg-ink text-white shadow-2xl transition-transform duration-200 print:hidden lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="px-5 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/10"
                        >
                            <img :src="tenant?.logo_url || '/images/coopos-logo.png'" :alt="tenant?.name || 'CoopOS'" class="h-full w-full object-contain">
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] uppercase tracking-[0.28em] text-gold">CoopOS</div>
                            <div class="mt-1 truncate text-lg font-semibold leading-tight">{{ tenant?.name }}</div>
                        </div>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-teal-100 hover:bg-white/10 lg:hidden" aria-label="Close menu" @click="mobileOpen = false">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div v-if="tenantTag || tenant?.app_mode === 'demo'" class="mt-2 flex items-center gap-2 text-xs text-teal-200/70">
                    <span class="whitespace-nowrap">{{ tenantTag }}</span>
                    <span
                        v-if="tenant?.app_mode === 'demo'"
                        class="rounded-full bg-amber-400/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-200"
                    >Demo</span>
                </div>
            </div>
            <form class="px-4 pb-3 lg:hidden" @submit.prevent="search">
                <input
                    v-model="query"
                    type="search"
                    placeholder="Find a member"
                    class="w-full rounded-full border-0 bg-white/10 px-4 py-2 text-sm text-white placeholder:text-teal-200/70"
                >
            </form>
            <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-8">
                <div v-for="group in groups" :key="group.label">
                    <div class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-teal-200/50">{{ group.label }}</div>
                    <template v-for="item in group.items" :key="item.href">
                        <div v-if="item.children?.length" class="mb-1">
                            <div
                                class="flex items-center rounded-xl transition"
                                :class="isSectionActive(item)
                                    ? (isMenuOpen(item) ? 'text-white' : 'bg-white/10 text-white')
                                    : 'text-teal-50/70 hover:bg-white/5 hover:text-white'"
                            >
                                <Link
                                    :href="item.href"
                                    class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5 text-sm"
                                    @click="openMenu(item); mobileOpen = false"
                                >
                                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                    </svg>
                                    <span class="truncate">{{ item.label }}</span>
                                </Link>
                                <button
                                    type="button"
                                    class="mr-1.5 rounded-lg p-1.5 text-current hover:bg-white/10"
                                    :aria-expanded="isMenuOpen(item)"
                                    :aria-label="`${isMenuOpen(item) ? 'Collapse' : 'Expand'} ${item.label}`"
                                    @click.stop="toggleMenu(item)"
                                >
                                    <svg
                                        class="h-4 w-4 transition-transform"
                                        :class="isMenuOpen(item) ? 'rotate-180' : ''"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </div>
                            <div v-if="isMenuOpen(item)" class="ml-5 mt-0.5 space-y-0.5 border-l border-white/10 pl-2">
                                <Link
                                    v-for="child in item.children"
                                    :key="child.id || child.href"
                                    :href="child.href"
                                    class="block rounded-lg px-3 py-2 text-sm transition"
                                    :class="isActive(child.href) ? 'bg-white/10 text-white' : 'text-teal-50/60 hover:bg-white/5 hover:text-white'"
                                    @click="mobileOpen = false"
                                >
                                    {{ child.label }}
                                </Link>
                            </div>
                        </div>
                        <Link
                            v-else
                            :href="item.href"
                            class="mb-1 flex items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-sm transition"
                            :class="isActive(item.href) ? 'bg-white/10 text-white' : 'text-teal-50/70 hover:bg-white/5 hover:text-white'"
                            @click="mobileOpen = false"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                </svg>
                                <span class="truncate">{{ item.label }}</span>
                            </span>
                            <span
                                v-if="item.badge"
                                class="rounded-full bg-gold px-2 py-0.5 text-[11px] font-semibold text-ink"
                            >{{ item.badge }}</span>
                        </Link>
                    </template>
                </div>
            </nav>
        </aside>

        <div class="print-sheet lg:pl-72">
            <div class="print-letterhead">
                <div class="flex items-center gap-3">
                    <img v-if="tenant?.logo_url" :src="tenant.logo_url" :alt="tenant?.name" class="h-10 w-10 object-contain">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.28em] text-teal-800">CoopOS</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900">{{ tenant?.name }}</div>
                    </div>
                </div>
                <div v-if="tenantTag" class="text-sm text-slate-500">{{ tenantTag }}</div>
            </div>
            <div class="sticky top-0 z-20 print:hidden">
                <div v-if="page.props.impersonating" class="flex items-center justify-between bg-gold px-4 py-2 text-sm text-ink sm:px-6">
                    <span>You are impersonating this cooperative.</span>
                    <button type="button" class="font-semibold underline" @click="leave.post('/impersonation/leave')">Return to platform</button>
                </div>
                <div v-if="page.props.staff_impersonation" class="flex items-center justify-between bg-teal-800 px-4 py-2 text-sm text-white sm:px-6">
                    <span>
                        Logged in as {{ page.props.staff_impersonation.as }}
                        <span v-if="page.props.staff_impersonation.branch"> · {{ page.props.staff_impersonation.branch }}</span>
                    </span>
                    <button type="button" class="font-semibold underline" @click="leave.post(`${base}/login-as/leave`)">Return to {{ page.props.staff_impersonation.original }}</button>
                </div>
                <header class="flex items-center justify-between gap-3 border-b border-slate-200/80 bg-white/90 px-3 py-2.5 backdrop-blur sm:px-6">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-700 ring-1 ring-slate-200/80 lg:hidden"
                        aria-label="Open menu"
                        @click="mobileOpen = true"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <div class="flex min-w-0 items-center gap-1.5 rounded-2xl bg-slate-50 p-1 ring-1 ring-slate-200/70">
                        <div v-if="canSwitchBranch" ref="branchRef" class="relative min-w-0">
                            <button
                                type="button"
                                class="flex max-w-[11rem] items-center gap-2 rounded-xl px-2.5 py-1.5 text-left transition hover:bg-white sm:max-w-[14rem]"
                                :class="branchOpen ? 'bg-white shadow-sm ring-1 ring-black/5' : ''"
                                :aria-expanded="branchOpen"
                                aria-haspopup="listbox"
                                aria-label="Switch branch"
                                @click.stop="branchOpen = !branchOpen"
                            >
                                <span class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ink/10 text-ink sm:inline-flex">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons.branches" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] font-medium uppercase tracking-[0.14em] text-slate-400">Branch</span>
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ branchName }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <div
                                v-if="branchOpen"
                                class="absolute left-0 z-30 mt-2 w-64 overflow-hidden rounded-2xl bg-white py-1 shadow-xl ring-1 ring-black/5"
                                role="listbox"
                            >
                                <button
                                    v-if="canViewAllBranches"
                                    type="button"
                                    class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                                    :class="!page.props.branch ? 'font-semibold text-teal-800' : 'text-slate-700'"
                                    role="option"
                                    @click="switchBranch('all')"
                                >
                                    All branches
                                    <svg v-if="!page.props.branch" class="h-4 w-4 text-teal-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </button>
                                <button
                                    v-for="b in accessibleBranches"
                                    :key="b.uuid"
                                    type="button"
                                    class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                                    :class="page.props.branch?.uuid === b.uuid ? 'font-semibold text-teal-800' : 'text-slate-700'"
                                    role="option"
                                    @click="switchBranch(b.uuid)"
                                >
                                    <span class="truncate">{{ b.name }}</span>
                                    <svg v-if="page.props.branch?.uuid === b.uuid" class="h-4 w-4 shrink-0 text-teal-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="flex max-w-[11rem] items-center gap-2 rounded-xl px-2.5 py-1.5 sm:max-w-[14rem]">
                            <span class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ink/10 text-ink sm:inline-flex">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons.branches" />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[10px] font-medium uppercase tracking-[0.14em] text-slate-400">Branch</span>
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ branchName }}</span>
                            </span>
                        </div>

                        <div
                            v-if="financialYear?.display"
                            ref="fiscalYearRef"
                            class="relative min-w-0 border-l border-slate-200/80 pl-1"
                            :title="financialYear.months_label ? `${financialYear.months_label} · ${financialYear.range}` : financialYear.range"
                        >
                            <button
                                v-if="canSwitchFiscalYear"
                                type="button"
                                class="flex max-w-[12rem] items-center gap-2 rounded-xl px-2.5 py-1.5 text-left transition hover:bg-white sm:max-w-[15rem]"
                                :class="fiscalYearOpen ? 'bg-white shadow-sm ring-1 ring-black/5' : ''"
                                :aria-expanded="fiscalYearOpen"
                                aria-haspopup="listbox"
                                aria-label="Switch financial year"
                                @click.stop="fiscalYearOpen = !fiscalYearOpen"
                            >
                                <span class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-800/10 text-teal-900 sm:inline-flex">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] font-medium uppercase tracking-[0.14em] text-slate-400">Financial year</span>
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ financialYear.display }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <div
                                v-else
                                class="flex max-w-[12rem] items-center gap-2 rounded-xl px-2.5 py-1.5 sm:max-w-[15rem]"
                            >
                                <span class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-800/10 text-teal-900 sm:inline-flex">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] font-medium uppercase tracking-[0.14em] text-slate-400">Financial year</span>
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ financialYear.display }}</span>
                                </span>
                            </div>
                            <div
                                v-if="fiscalYearOpen"
                                class="absolute left-0 z-30 mt-2 w-72 overflow-hidden rounded-2xl bg-white py-1 shadow-xl ring-1 ring-black/5"
                                role="listbox"
                            >
                                <button
                                    v-for="option in fiscalYearOptions"
                                    :key="option.year"
                                    type="button"
                                    class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                                    :class="option.current ? 'font-semibold text-teal-800' : 'text-slate-700'"
                                    role="option"
                                    @click="switchFiscalYear(option.year)"
                                >
                                    <span class="min-w-0 truncate">
                                        <span class="block">{{ option.display }}</span>
                                        <span class="block text-[11px] font-normal text-slate-500">Year {{ option.year_number }}</span>
                                    </span>
                                    <svg v-if="option.current" class="h-4 w-4 shrink-0 text-teal-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <form v-if="can('members.view')" class="hidden max-w-md flex-1 md:block" @submit.prevent="search">
                    <label class="relative block">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>
                        <input
                            v-model="query"
                            type="search"
                            placeholder="Find a member"
                            class="w-full rounded-full border-0 bg-slate-50 py-2 pl-9 pr-4 text-sm text-slate-800 ring-1 ring-slate-200/80 placeholder:text-slate-400 focus:bg-white focus:ring-teal-800/20"
                        >
                    </label>
                </form>
                <div class="flex shrink-0 items-center gap-2">
                    <NotificationBell />
                    <div ref="profileRef" class="relative shrink-0">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full bg-slate-50 py-1 pl-1 pr-2 ring-1 ring-slate-200/80 transition hover:bg-white sm:pr-3"
                        :aria-expanded="profileOpen"
                        aria-haspopup="menu"
                        @click.stop="profileOpen = !profileOpen"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ink text-xs font-semibold text-gold">
                            {{ initials }}
                        </span>
                        <span class="hidden max-w-[9rem] truncate text-left text-sm font-medium text-slate-800 sm:block">{{ user?.name }}</span>
                        <svg class="hidden h-4 w-4 text-slate-400 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div
                        v-if="profileOpen"
                        class="absolute right-0 mt-2 w-64 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
                        role="menu"
                    >
                        <div class="border-b border-slate-100 px-4 py-3">
                            <div class="truncate text-sm font-semibold text-slate-900">{{ user?.name }}</div>
                            <div class="truncate text-xs text-slate-500">{{ user?.email }}</div>
                        </div>
                        <Link
                            v-if="can('settings.manage')"
                            :href="`${base}/settings`"
                            class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
                            role="menuitem"
                            @click="profileOpen = false"
                        >
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="icons.settings" />
                            </svg>
                            Settings
                        </Link>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-rose-700 hover:bg-rose-50"
                            role="menuitem"
                            @click="signOut"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            Sign out
                        </button>
                    </div>
                    </div>
                </div>
            </header>
            </div>

            <main class="px-3 py-6 pb-24 sm:px-6 sm:py-8 lg:pb-8">
                <Transition name="page" mode="out-in">
                    <div :key="current" class="page-shell">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-slate-200 bg-white/95 px-1 py-1.5 backdrop-blur print:hidden lg:hidden">
            <Link
                v-for="tab in mobileTabs"
                :key="tab.href"
                :href="tab.href"
                class="flex flex-col items-center gap-0.5 rounded-xl px-2 py-1.5 text-[11px] font-medium"
                :class="isActive(tab.href) ? 'text-teal-800' : 'text-slate-500'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[tab.icon]" />
                </svg>
                {{ tab.label }}
            </Link>
        </nav>
    </div>
</template>
