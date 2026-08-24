<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        TenantContext::bypass();

        return Inertia::render('Platform/Plans', [
            'plans' => SubscriptionPlan::query()->withCount('subscriptions')->orderBy('sort_order')->get()->map(fn (SubscriptionPlan $plan) => [
                'uuid' => $plan->uuid,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'monthly_price' => money((int) $plan->monthly_price_kobo),
                'yearly_price' => money((int) $plan->yearly_price_kobo),
                'monthly_price_naira' => intdiv((int) $plan->monthly_price_kobo, 100),
                'yearly_price_naira' => intdiv((int) $plan->yearly_price_kobo, 100),
                'member_limit' => $plan->member_limit,
                'features' => $plan->features ?? [],
                'is_active' => $plan->is_active,
                'subscribers' => $plan->subscriptions_count,
            ]),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'monthly_price_naira' => ['required', 'integer', 'min:0'],
            'yearly_price_naira' => ['required', 'integer', 'min:0'],
            'member_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $plan->update([
            'monthly_price_kobo' => naira_to_kobo($data['monthly_price_naira']),
            'yearly_price_kobo' => naira_to_kobo($data['yearly_price_naira']),
            'member_limit' => $data['member_limit'],
            'is_active' => $data['is_active'],
            'description' => $data['description'] ?? $plan->description,
        ]);

        return back()->with('success', "{$plan->name} has been updated.");
    }
}
