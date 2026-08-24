<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function invoices(Request $request)
    {
        TenantContext::bypass();

        $invoices = Invoice::query()
            ->with('tenant')
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"));
                });
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'cooperative' => $invoice->tenant?->name,
                'slug' => $invoice->tenant?->slug,
                'amount' => money((int) $invoice->amount_kobo),
                'status' => $invoice->status,
                'provider' => $invoice->provider,
                'due_at' => $invoice->due_at?->format('j M Y'),
                'paid_at' => $invoice->paid_at?->format('j M Y'),
                'is_overdue' => $invoice->status === 'past_due'
                    || ($invoice->status === 'pending' && $invoice->due_at?->isPast()),
                'can_collect' => ! in_array($invoice->status, ['paid', 'void'], true),
            ]);

        return Inertia::render('Platform/Billing/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'q']),
            'summary' => [
                'paid' => money((int) Invoice::query()->where('status', 'paid')->sum('amount_kobo')),
                'open' => money((int) Invoice::query()->whereIn('status', ['pending', 'past_due'])->sum('amount_kobo')),
                'past_due' => money((int) Invoice::query()->where('status', 'past_due')->sum('amount_kobo')),
                'count' => Invoice::query()->count(),
            ],
            'counts' => [
                'all' => Invoice::query()->count(),
                'paid' => Invoice::query()->where('status', 'paid')->count(),
                'pending' => Invoice::query()->where('status', 'pending')->count(),
                'past_due' => Invoice::query()->where('status', 'past_due')->count(),
                'void' => Invoice::query()->where('status', 'void')->count(),
            ],
        ]);
    }

    public function markPaid(Invoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return back()->with('error', "{$invoice->number} is already paid.");
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider' => $invoice->provider ?: 'manual',
        ]);

        return back()->with('success', "{$invoice->number} marked as paid.");
    }

    public function void(Invoice $invoice)
    {
        if ($invoice->status === 'void') {
            return back()->with('error', "{$invoice->number} is already void.");
        }

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Paid invoices cannot be voided. Issue a credit instead.');
        }

        $invoice->update([
            'status' => 'void',
            'paid_at' => null,
        ]);

        return back()->with('success', "{$invoice->number} was voided.");
    }

    public function subscriptions(Request $request)
    {
        TenantContext::bypass();

        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Subscription $sub) => [
                'uuid' => $sub->uuid,
                'cooperative' => $sub->tenant?->name,
                'slug' => $sub->tenant?->slug,
                'plan' => $sub->plan?->name,
                'status' => $sub->status?->value,
                'interval' => $sub->interval,
                'price' => money($sub->interval === 'yearly'
                    ? (int) $sub->plan?->yearly_price_kobo
                    : (int) $sub->plan?->monthly_price_kobo),
                'trial_ends_at' => $sub->trial_ends_at?->toDateString(),
                'starts_at' => $sub->starts_at?->toDateString(),
            ]);

        return Inertia::render('Platform/Billing/Subscriptions', [
            'subscriptions' => $subscriptions,
            'filters' => $request->only('status'),
            'summary' => [
                'active' => Subscription::query()->where('status', 'active')->count(),
                'trialing' => Subscription::query()->where('status', 'trialing')->count(),
                'past_due' => Subscription::query()->where('status', 'past_due')->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'status' => ['required', 'in:trialing,active,past_due,cancelled'],
        ]);

        $subscription->update([
            'status' => $data['status'],
            'cancelled_at' => $data['status'] === 'cancelled' ? now() : null,
        ]);

        return back()->with('success', 'Subscription updated.');
    }
}
