<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Notifications\Notifier;
use App\Domain\Platform\TenantProvisioner;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CooperativeController extends Controller
{
    public function index(Request $request)
    {
        TenantContext::bypass();

        $tenants = Tenant::query()
            ->with('subscription.plan')
            ->withCount('members')
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('state', 'like', "%{$q}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('mode')->toString(), fn ($query, $mode) => $query->where('mode', $mode))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Tenant $t) => [
                'uuid' => $t->uuid,
                'name' => $t->name,
                'slug' => $t->slug,
                'host' => $t->host(),
                'url' => $t->appUrl('/dashboard'),
                'mode' => $t->mode->label(),
                'type' => $t->type->label(),
                'status' => $t->status->value,
                'state' => $t->state,
                'members_count' => $t->members_count,
                'plan' => $t->subscription?->plan?->name,
                'billing' => $t->subscription?->status?->value,
                'created_at' => $t->created_at?->toDateString(),
            ]);

        return Inertia::render('Platform/Cooperatives/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['q', 'status', 'mode']),
            'allow_impersonation' => (bool) PlatformSetting::get('allow_impersonation', true),
            'counts' => [
                'all' => Tenant::query()->count(),
                'active' => Tenant::query()->where('status', 'active')->count(),
                'pending' => Tenant::query()->where('status', 'pending')->count(),
                'suspended' => Tenant::query()->where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function show(Tenant $tenant)
    {
        TenantContext::bypass();

        $tenant->load(['subscription.plan', 'settings']);

        return Inertia::render('Platform/Cooperatives/Show', [
            'cooperative' => [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'legal_name' => $tenant->legal_name,
                'host' => $tenant->host(),
                'url' => $tenant->appUrl('/dashboard'),
                'mode' => $tenant->mode->label(),
                'mode_value' => $tenant->mode->value,
                'type' => $tenant->type->label(),
                'status' => $tenant->status->value,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'state' => $tenant->state,
                'city' => $tenant->city,
                'address' => $tenant->address,
                'approved_at' => $tenant->approved_at?->toDayDateTimeString(),
                'suspended_at' => $tenant->suspended_at?->toDayDateTimeString(),
                'suspension_reason' => $tenant->suspension_reason,
                'created_at' => $tenant->created_at?->toDayDateTimeString(),
                'members' => $tenant->members()->count(),
                'staff' => $tenant->users()->where('type', UserType::Staff)->count(),
                'plan' => $tenant->subscription?->plan?->name,
                'plan_slug' => $tenant->subscription?->plan?->slug,
                'billing' => $tenant->subscription?->status?->value,
                'interval' => $tenant->subscription?->interval,
                'trial_ends_at' => $tenant->subscription?->trial_ends_at?->toDateString(),
                'allow_impersonation' => (bool) PlatformSetting::get('allow_impersonation', true),
            ],
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['name', 'slug']),
            'invoices' => Invoice::query()->where('tenant_id', $tenant->id)->latest()->take(8)->get()->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'amount' => money((int) $invoice->amount_kobo),
                'status' => $invoice->status,
                'due_at' => $invoice->due_at?->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateString(),
            ]),
            'tickets' => SupportTicket::query()->where('tenant_id', $tenant->id)->latest()->take(5)->get()->map(fn (SupportTicket $ticket) => [
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
            ]),
            'admins' => User::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', UserType::Staff)
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (User $user) => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type?->value,
                    'last_login_at' => $user->last_login_at?->diffForHumans(),
                ]),
        ]);
    }

    public function approve(Tenant $tenant, TenantProvisioner $provisioner)
    {
        if ($tenant->status->value === 'active') {
            return back()->with('error', "{$tenant->name} is already active.");
        }

        $provisioner->activate($tenant);
        app(Notifier::class)->cooperativeStatus(
            $tenant,
            'Cooperative approved',
            $tenant->name.' is now live on CoopOS.',
        );

        return back()->with('success', "{$tenant->name} has been approved.");
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        if ($tenant->status->value === 'suspended') {
            return back()->with('error', "{$tenant->name} is already suspended.");
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $tenant->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'] ?? 'Suspended by platform admin',
        ]);

        app(Notifier::class)->cooperativeStatus(
            $tenant,
            'Cooperative suspended',
            $data['reason'] ?? 'Your cooperative has been suspended.',
        );

        return back()->with('success', "{$tenant->name} has been suspended.");
    }

    public function reactivate(Tenant $tenant, TenantProvisioner $provisioner)
    {
        if ($tenant->status->value === 'active') {
            return back()->with('error', "{$tenant->name} is already active.");
        }

        $provisioner->activate($tenant);
        app(Notifier::class)->cooperativeStatus(
            $tenant,
            'Cooperative reactivated',
            $tenant->name.' is active again.',
        );

        return back()->with('success', "{$tenant->name} is active again.");
    }

    public function changePlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:subscription_plans,slug'],
        ]);

        $plan = SubscriptionPlan::query()->where('slug', $data['plan'])->firstOrFail();

        if ($tenant->subscription) {
            $tenant->subscription->update(['subscription_plan_id' => $plan->id]);
        } else {
            $tenant->subscription()->create([
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'interval' => 'monthly',
                'starts_at' => now(),
            ]);
        }

        return back()->with('success', "{$tenant->name} is now on {$plan->name}.");
    }
}
