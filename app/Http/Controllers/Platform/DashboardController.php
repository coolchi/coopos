<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\SupportTicket;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\TenantContext;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        TenantContext::bypass();

        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $months = collect(range(5, 0))->map(function (int $ago) use ($now) {
            $start = $now->copy()->subMonths($ago)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $paid = Invoice::query()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end]);
            $kobo = (int) (clone $paid)->sum('amount_kobo');

            return [
                'label' => $start->format('M'),
                'month' => $start->format('F'),
                'signups' => Tenant::query()->whereBetween('created_at', [$start, $end])->count(),
                'invoices' => (clone $paid)->count(),
                'revenue_kobo' => $kobo,
                'revenue' => money($kobo),
                'compact' => $this->compactNaira($kobo),
                'current' => $start->isSameMonth($now),
            ];
        });

        $peak = max(1, (int) $months->max('revenue_kobo'));
        $months = $months->map(function (array $row) use ($peak) {
            $row['pct'] = (int) round($row['revenue_kobo'] / $peak * 100);

            return $row;
        });

        $mrrKobo = (int) Subscription::query()->where('status', 'active')->with('plan')->get()
            ->sum(fn ($s) => $s->interval === 'yearly'
                ? intdiv((int) $s->plan?->yearly_price_kobo, 12)
                : (int) $s->plan?->monthly_price_kobo);

        $collectedKobo = (int) Invoice::query()->where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount_kobo');

        return Inertia::render('Platform/Dashboard', [
            'kpis' => [
                'cooperatives' => Tenant::query()->count(),
                'active' => Tenant::query()->where('status', 'active')->count(),
                'pending' => Tenant::query()->where('status', 'pending')->count(),
                'suspended' => Tenant::query()->where('status', 'suspended')->count(),
                'trials' => Subscription::query()->where('status', 'trialing')->count(),
                'past_due' => Subscription::query()->where('status', 'past_due')->count(),
                'members' => Member::query()->count(),
                'open_tickets' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(),
                'high_tickets' => SupportTicket::query()->where('priority', 'high')->whereIn('status', ['open', 'pending'])->count(),
                'mrr' => money($mrrKobo),
                'mrr_kobo' => $mrrKobo,
                'collected_this_month' => money($collectedKobo),
                'collected_label' => 'Collected in '.$now->format('F'),
            ],
            'chart' => $months,
            'attention' => $this->attention(),
            'recent' => Tenant::query()->with('subscription.plan')->latest()->take(6)->get()->map(fn (Tenant $t) => $this->tenantCard($t)),
            'tickets' => SupportTicket::query()->with('tenant')->latest()->take(5)->get()->map(fn (SupportTicket $ticket) => [
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'cooperative' => $ticket->tenant?->name,
                'created_at' => $ticket->created_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attention(): array
    {
        $items = collect();

        Tenant::query()->where('status', 'pending')->latest()->take(5)->get()->each(function (Tenant $tenant) use ($items) {
            $items->push([
                'tone' => 'amber',
                'label' => 'Awaiting approval',
                'title' => $tenant->name,
                'meta' => $tenant->host().' · '.$tenant->created_at?->diffForHumans(),
                'href' => '/platform/cooperatives/'.$tenant->slug,
                'action' => 'Review',
            ]);
        });

        Tenant::query()->where('status', 'suspended')->latest()->take(3)->get()->each(function (Tenant $tenant) use ($items) {
            $items->push([
                'tone' => 'rose',
                'label' => 'Suspended',
                'title' => $tenant->name,
                'meta' => $tenant->suspension_reason ?: $tenant->host(),
                'href' => '/platform/cooperatives/'.$tenant->slug,
                'action' => 'Open',
            ]);
        });

        Subscription::query()->with(['tenant', 'plan'])->where('status', 'past_due')->latest()->take(5)->get()
            ->each(function (Subscription $subscription) use ($items) {
                $items->push([
                    'tone' => 'orange',
                    'label' => 'Past due',
                    'title' => $subscription->tenant?->name ?: 'Unknown cooperative',
                    'meta' => trim(($subscription->plan?->name ?? 'Plan').' · '.$subscription->interval),
                    'href' => $subscription->tenant?->slug
                        ? '/platform/cooperatives/'.$subscription->tenant->slug
                        : '/platform/subscriptions',
                    'action' => 'Billing',
                ]);
            });

        SupportTicket::query()->with('tenant')->where('priority', 'high')->whereIn('status', ['open', 'pending'])->latest()->take(4)->get()
            ->each(function (SupportTicket $ticket) use ($items) {
                $items->push([
                    'tone' => 'slate',
                    'label' => 'High-priority ticket',
                    'title' => $ticket->subject,
                    'meta' => ($ticket->tenant?->name ?? 'Cooperative').' · '.$ticket->created_at?->diffForHumans(),
                    'href' => '/platform/tickets/'.$ticket->uuid,
                    'action' => 'Reply',
                ]);
            });

        return $items->take(4)->values()->all();
    }

    private function tenantCard(Tenant $tenant): array
    {
        return [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'host' => $tenant->host(),
            'initials' => mb_strtoupper(mb_substr($tenant->name, 0, 1)),
            'mode' => $tenant->mode->label(),
            'status' => $tenant->status->value,
            'plan' => $tenant->subscription?->plan?->name,
            'created_at' => $tenant->created_at?->diffForHumans(),
        ];
    }

    private function compactNaira(int $kobo): string
    {
        $naira = $kobo / 100;

        if ($naira <= 0) {
            return '₦0';
        }

        if ($naira >= 1_000_000) {
            return '₦'.rtrim(rtrim(number_format($naira / 1_000_000, 1), '0'), '.').'m';
        }

        if ($naira >= 1_000) {
            return '₦'.number_format($naira / 1_000, $naira >= 10_000 ? 0 : 1).'k';
        }

        return money($kobo);
    }
}
