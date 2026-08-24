<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const workspaceUrl = computed(() => page.props.dashboardUrl || '/home');

const signOut = () => router.post('/logout');

const features = [
    { icon: '/images/landing/icons/members.png', title: 'Member lifecycle', body: 'Onboard, KYC, groups, next of kin, and bulk import without spreadsheets.' },
    { icon: '/images/landing/icons/money.png', title: 'Savings engine', body: 'Daily, weekly, or monthly contributions with schedules, reminders, and statements.' },
    { icon: '/images/landing/icons/handshake.png', title: 'Digital loans', body: 'Apply, guarantee, approve, disburse, and repay with a full audit trail.' },
    { icon: '/images/landing/icons/mosque.png', title: 'Islamic finance', body: 'Qard Hasan and Murabaha with disclosed profit — never interest calculations.' },
    { icon: '/images/landing/icons/approval.png', title: 'Approvals that scale', body: 'Amount-banded workflows for officers, treasurers, committees, and chairmen.' },
    { icon: '/images/landing/icons/chart.png', title: 'Live reporting', body: 'Portfolio, delinquency, and member statements branded for your society.' },
    { icon: '/images/landing/icons/transfer.png', title: 'Payments in Nigeria', body: 'Manual posting today, Paystack and other gateways through a clean adapter.' },
    { icon: '/images/landing/icons/shield.png', title: 'Tenant isolation', body: 'Every cooperative is a sealed workspace. Super Admin never mixes the books.' },
];

const steps = [
    { n: '01', title: 'Create your workspace', body: 'Register the society, choose conventional, Islamic, or hybrid, and invite officers.' },
    { n: '02', title: 'Bring members in', body: 'Add people by form, invitation, or CSV. Each member gets a private portal.' },
    { n: '03', title: 'Run the books digitally', body: 'Contributions, financing, approvals, and statements post to an immutable ledger.' },
];
</script>

<template>
    <div class="min-h-screen bg-[#071c1b] text-white">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#071c1b]">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-3">
                    <img src="/images/coopos-logo.png" alt="CoopOS" class="h-9 w-9 rounded-xl">
                    <span class="text-lg font-semibold tracking-tight">CoopOS</span>
                </Link>
                <nav class="hidden items-center gap-8 text-sm text-teal-100/80 md:flex">
                    <a href="#product" class="hover:text-white">Product</a>
                    <a href="#modes" class="hover:text-white">Modes</a>
                    <a href="#islamic" class="hover:text-white">Islamic finance</a>
                    <a href="#security" class="hover:text-white">Trust</a>
                </nav>
                <div class="flex items-center gap-3 text-sm">
                    <template v-if="user">
                        <button type="button" class="rounded-full px-4 py-2 text-teal-100 hover:bg-white/10" @click="signOut">Sign out</button>
                        <a :href="workspaceUrl" class="rounded-full bg-[#d4b06a] px-5 py-2.5 font-semibold text-[#071c1b] shadow-lg shadow-black/20 hover:bg-[#e0c184]">
                            Open workspace
                        </a>
                    </template>
                    <template v-else>
                        <Link href="/login" class="rounded-full px-4 py-2 text-teal-100 hover:bg-white/10">Sign in</Link>
                        <Link href="/register" class="rounded-full bg-[#d4b06a] px-5 py-2.5 font-semibold text-[#071c1b] shadow-lg shadow-black/20 hover:bg-[#e0c184]">
                            Start your cooperative
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <section class="relative min-h-[92vh] overflow-hidden">
            <img src="/images/landing/hero-team.jpg" alt="Nigerian cooperative officers in a working session" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-[#041514] via-[#041514]/85 to-[#041514]/25"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#071c1b] via-transparent to-[#041514]/40"></div>

            <div class="relative mx-auto flex min-h-[92vh] max-w-7xl items-end px-6 pb-16 pt-32 lg:items-center lg:pb-24">
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.32em] text-[#d4b06a]">Nigerian cooperative operating system</p>
                    <h1 class="mt-5 max-w-3xl text-4xl font-semibold leading-[1.08] tracking-tight sm:text-6xl">
                        The operating system for societies that still run on ledgers and trust.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-teal-50/80">
                        Replace contribution books, Excel, and paper loans with a workspace your treasurer can actually use — for conventional, Islamic, and hybrid cooperatives.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a v-if="user" :href="workspaceUrl" class="rounded-full bg-[#d4b06a] px-7 py-3.5 font-semibold text-[#071c1b] shadow-xl shadow-black/30">
                            Open workspace
                        </a>
                        <Link v-else href="/register" class="rounded-full bg-[#d4b06a] px-7 py-3.5 font-semibold text-[#071c1b] shadow-xl shadow-black/30">
                            Create a workspace
                        </Link>
                        <a href="#product" class="rounded-full border border-white/20 bg-white/5 px-7 py-3.5 font-medium backdrop-blur">
                            See the platform
                        </a>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-8 text-sm text-teal-100/70">
                        <div><span class="block text-2xl font-semibold text-white">3</span> finance modes</div>
                        <div><span class="block text-2xl font-semibold text-white">₦</span> kobo-accurate ledger</div>
                        <div><span class="block text-2xl font-semibold text-white">0%</span> interest on Islamic products</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-y border-white/10 bg-[#0b2a28]">
            <div class="mx-auto grid max-w-7xl gap-6 px-6 py-8 sm:grid-cols-3">
                <div class="flex items-center gap-4">
                    <img src="/images/landing/icons/lock.png" alt="" class="h-11 w-11">
                    <div>
                        <div class="font-medium">Bank-grade isolation</div>
                        <div class="text-sm text-teal-100/60">Each society is a sealed tenant.</div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <img src="/images/landing/icons/check.png" alt="" class="h-11 w-11">
                    <div>
                        <div class="font-medium">Immutable journals</div>
                        <div class="text-sm text-teal-100/60">Balances are posted, never edited.</div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <img src="/images/landing/icons/mobile.png" alt="" class="h-11 w-11">
                    <div>
                        <div class="font-medium">Member-first mobile</div>
                        <div class="text-sm text-teal-100/60">Built for phones in the market and office.</div>
                    </div>
                </div>
            </div>
        </section>

        <section id="product" class="bg-[#f4f1ea] py-24 text-slate-900">
            <div class="mx-auto max-w-7xl px-6">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">The product</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-tight">Designed like modern banking software. Spoken like a cooperative.</h2>
                    <p class="mt-4 text-lg text-slate-600">Officers get a calm dashboard. Members get a portal that works on a smartphone. The ledger underneath is serious.</p>
                </div>

                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="feature in features" :key="feature.title" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
                        <img :src="feature.icon" :alt="feature.title" class="h-12 w-12">
                        <h3 class="mt-4 text-lg font-semibold">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ feature.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="modes" class="bg-[#071c1b] py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#d4b06a]">One platform, three modes</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-tight">Staff, traders, farmers, and Islamic societies on the same OS.</h2>
                </div>
                <div class="mt-14 grid gap-6 lg:grid-cols-3">
                    <article class="group overflow-hidden rounded-[1.75rem] bg-[#0b2a28] ring-1 ring-white/10">
                        <div class="h-56 overflow-hidden">
                            <img src="/images/landing/office.jpg" alt="Nigerian staff cooperative meeting" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        </div>
                        <div class="p-7">
                            <img src="/images/landing/icons/bank.png" alt="" class="h-10 w-10">
                            <h3 class="mt-4 text-2xl font-semibold">General</h3>
                            <p class="mt-3 text-teal-50/70">Monthly savings, personal and emergency loans, reducing-balance interest, guarantors, and disbursement workflows.</p>
                        </div>
                    </article>
                    <article class="group overflow-hidden rounded-[1.75rem] bg-[#0b2a28] ring-1 ring-white/10">
                        <div class="h-56 overflow-hidden">
                            <img src="/images/landing/islamic.jpg" alt="Nigerian Muslim officers reviewing Islamic cooperative financing" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        </div>
                        <div class="p-7">
                            <img src="/images/landing/icons/mosque.png" alt="" class="h-10 w-10">
                            <h3 class="mt-4 text-2xl font-semibold">Islamic</h3>
                            <p class="mt-3 text-teal-50/70">Qard Hasan with permitted fees only. Murabaha with disclosed profit. Terminology and calculators never mix with riba.</p>
                        </div>
                    </article>
                    <article class="group overflow-hidden rounded-[1.75rem] bg-[#0b2a28] ring-1 ring-white/10">
                        <div class="h-56 overflow-hidden">
                            <img src="/images/landing/community.jpg" alt="Nigerian cooperative members in a savings meeting" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        </div>
                        <div class="p-7">
                            <img src="/images/landing/icons/handshake.png" alt="" class="h-10 w-10">
                            <h3 class="mt-4 text-2xl font-semibold">Hybrid</h3>
                            <p class="mt-3 text-teal-50/70">Offer both books in one cooperative. Products are labelled, reports are split, and members only see what they are eligible for.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-[#f4f1ea] py-24 text-slate-900">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">How it works</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-tight">Live in days, not a six-month custom build.</h2>
                    <ol class="mt-10 space-y-8">
                        <li v-for="step in steps" :key="step.n" class="flex gap-5">
                            <span class="text-sm font-semibold tracking-[0.2em] text-[#c4a35a]">{{ step.n }}</span>
                            <div>
                                <h3 class="text-xl font-semibold">{{ step.title }}</h3>
                                <p class="mt-1 text-slate-600">{{ step.body }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
                <div class="grid gap-4">
                    <img src="/images/landing/meeting.jpg" alt="Nigerian officers reviewing cooperative records" class="h-64 w-full rounded-[1.5rem] object-cover shadow-xl">
                    <div class="grid grid-cols-2 gap-4">
                        <img src="/images/landing/savings.jpg" alt="Counting Nigerian naira savings" class="h-48 w-full rounded-[1.5rem] object-cover">
                        <img src="/images/landing/approvals.jpg" alt="Signing a financing agreement" class="h-48 w-full rounded-[1.5rem] object-cover">
                    </div>
                </div>
            </div>
        </section>

        <section id="islamic" class="relative overflow-hidden py-24">
            <img src="/images/landing/islamic.jpg" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-[#041514]/88"></div>
            <div class="relative mx-auto grid max-w-7xl gap-12 px-6 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#d4b06a]">Islamic savings and financing</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-tight">Profit is disclosed. Interest is never calculated.</h2>
                    <p class="mt-4 max-w-lg text-lg text-teal-50/75">
                        Run a Shariah-compliant cooperative: member savings, Qard Hasan loans, and Murabaha asset financing on one ledger. Murabaha sale price equals purchase cost plus agreed profit. Qard Hasan repayable amount equals principal plus permitted administrative fees. The conventional interest engine cannot touch these products.
                    </p>
                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/10">
                            <img src="/images/landing/icons/mosque.png" alt="" class="h-10 w-10">
                            <div class="mt-3 font-semibold">Qard Hasan</div>
                            <p class="mt-1 text-sm text-teal-50/65">Interest-free loan with optional admin fee. No compounding.</p>
                        </div>
                        <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/10">
                            <img src="/images/landing/icons/document.png" alt="" class="h-10 w-10">
                            <div class="mt-3 font-semibold">Murabaha</div>
                            <p class="mt-1 text-sm text-teal-50/65">Asset, supplier, cost, profit, and frozen sale agreement.</p>
                        </div>
                    </div>
                </div>
                <img src="/images/landing/welfare.jpg" alt="Nigerian Muslim cooperative officers closing a financing agreement" class="h-[420px] w-full rounded-[1.75rem] object-cover ring-1 ring-white/15">
            </div>
        </section>

        <section class="bg-[#f4f1ea] py-24 text-slate-900">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-2">
                <img src="/images/landing/product-phone.png" alt="Member portal on a smartphone" class="w-full rounded-[2rem] shadow-2xl ring-1 ring-slate-200">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-800">Member portal</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-tight">Balances, contributions, and applications from a phone.</h2>
                    <p class="mt-4 text-lg text-slate-600">Members should not need the treasurer to know what they have saved or what they still owe. The portal is mobile-first on purpose.</p>
                    <ul class="mt-8 space-y-4 text-slate-700">
                        <li class="flex items-start gap-3">
                            <img src="/images/landing/icons/money.png" alt="" class="mt-0.5 h-7 w-7">
                            <span>See savings, financing balances, and the next due date.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <img src="/images/landing/icons/transfer.png" alt="" class="mt-0.5 h-7 w-7">
                            <span>Pay contributions and request withdrawals with a proper approval path.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <img src="/images/landing/icons/document.png" alt="" class="mt-0.5 h-7 w-7">
                            <span>Download a branded statement with opening and closing balances.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="security" class="bg-[#071c1b] py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#d4b06a]">Trust</p>
                        <h2 class="mt-3 text-4xl font-semibold tracking-tight">Financial software, not a pretty spreadsheet.</h2>
                        <p class="mt-4 text-lg text-teal-50/75">Every important action is validated, authorised, posted to the ledger, and written to the audit log.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl bg-[#0b2a28] p-6 ring-1 ring-white/10">
                            <img src="/images/landing/icons/shield.png" alt="" class="h-12 w-12">
                            <h3 class="mt-4 font-semibold">Role-based access</h3>
                            <p class="mt-2 text-sm text-teal-50/65">Chairman, treasurer, loan officer, auditor, member — each sees only their work.</p>
                        </div>
                        <div class="rounded-3xl bg-[#0b2a28] p-6 ring-1 ring-white/10">
                            <img src="/images/landing/icons/lock.png" alt="" class="h-12 w-12">
                            <h3 class="mt-4 font-semibold">Encrypted KYC</h3>
                            <p class="mt-2 text-sm text-teal-50/65">BVN and NIN are stored encrypted. Locked savings cannot be withdrawn early.</p>
                        </div>
                        <div class="rounded-3xl bg-[#0b2a28] p-6 ring-1 ring-white/10">
                            <img src="/images/landing/icons/chart.png" alt="" class="h-12 w-12">
                            <h3 class="mt-4 font-semibold">Idempotent payments</h3>
                            <p class="mt-2 text-sm text-teal-50/65">Webhook replays cannot double-credit a contribution.</p>
                        </div>
                        <div class="rounded-3xl bg-[#0b2a28] p-6 ring-1 ring-white/10">
                            <img src="/images/landing/icons/support.png" alt="" class="h-12 w-12">
                            <h3 class="mt-4 font-semibold">Support built in</h3>
                            <p class="mt-2 text-sm text-teal-50/65">Tickets from the cooperative to the CoopOS platform team.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden">
            <img src="/images/landing/farmers.jpg" alt="Agricultural cooperative fields" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-[#041514]/80"></div>
            <div class="relative mx-auto max-w-4xl px-6 py-28 text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#d4b06a]">Ready when you are</p>
                <h2 class="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">Give your society software worthy of the money it holds.</h2>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-teal-50/80">Onboard your cooperative in a single afternoon.</p>
                <a v-if="user" :href="workspaceUrl" class="mt-10 inline-flex rounded-full bg-[#d4b06a] px-8 py-4 font-semibold text-[#071c1b] shadow-2xl">
                    Open workspace
                </a>
                <Link v-else href="/register" class="mt-10 inline-flex rounded-full bg-[#d4b06a] px-8 py-4 font-semibold text-[#071c1b] shadow-2xl">
                    Start your cooperative
                </Link>
            </div>
        </section>

        <footer class="border-t border-white/10 bg-[#041514] py-12">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <img src="/images/coopos-logo.png" alt="CoopOS" class="h-8 w-8 rounded-lg">
                    <div>
                        <div class="font-semibold">CoopOS</div>
                        <div class="text-xs text-teal-100/50">Built for Nigerian cooperative societies</div>
                    </div>
                </div>
                <div class="flex gap-6 text-sm text-teal-100/60">
                    <template v-if="user">
                        <a :href="workspaceUrl">Open workspace</a>
                    </template>
                    <template v-else>
                        <Link href="/login">Sign in</Link>
                        <Link href="/register">Create a workspace</Link>
                    </template>
                </div>
            </div>
        </footer>
    </div>
</template>
