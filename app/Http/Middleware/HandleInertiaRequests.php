<?php

namespace App\Http\Middleware;

use App\Domain\IslamicFinance\Terminology;
use App\Enums\CooperativeMode;
use App\Enums\UserType;
use App\Models\ApprovalRequest;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Support\BranchContext;
use App\Support\FiscalPeriod;
use App\Support\Tenancy;
use App\Support\TenantContext;
use App\Support\TenantTheme;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $tenant = TenantContext::current();
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type?->value,
                    'is_platform_admin' => $user->isPlatformAdmin(),
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
            ],
            'dashboardUrl' => $user?->homeUrl(),
            'tenant' => $tenant ? [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slogan' => $tenant->slogan,
                'slug' => $tenant->slug,
                'mode' => $tenant->mode->value,
                'mode_label' => match ($tenant->mode) {
                    CooperativeMode::General => 'General',
                    CooperativeMode::Islamic => 'Islamic',
                    CooperativeMode::Hybrid => 'Hybrid',
                },
                'type' => $tenant->type->value,
                'type_label' => $tenant->type->label(),
                'status' => $tenant->status->value,
                'logo_path' => $tenant->logo_path,
                'logo_url' => $tenant->logoUrl(),
                'brand_color' => $tenant->brand_color,
                'theme' => TenantTheme::resolve($tenant),
                'offers_conventional' => $tenant->offersConventional(),
                'offers_islamic' => $tenant->offersIslamic(),
                'app_mode' => $tenant->settings?->appMode()->value ?? 'demo',
            ] : null,
            'terminology' => $tenant ? Terminology::for($tenant) : null,
            'tenantBase' => Tenancy::pathPrefix($tenant),
            'financial_year' => $tenant ? FiscalPeriod::shared() : null,
            'csrf_token' => csrf_token(),
            'branch' => $this->branchPayload($user),
            'accessible_branches' => $this->accessibleBranches($user),
            'can_view_all_branches' => (bool) ($user && $tenant && $user->isCentralAdmin()),
            'can_manage_branches' => (bool) ($user && $tenant && $user->can('branches.manage')),
            'opening_balances_locked' => (bool) ($tenant && filled($tenant->settings?->extras['opening_balances_locked_at'] ?? null)),
            'can_manage_opening_balances' => (bool) ($user && $tenant && $user->can('contributions.manage') && (
                ! filled($tenant->settings?->extras['opening_balances_locked_at'] ?? null)
            )),
            'allow_manual_payments' => (bool) ($tenant?->settings?->allowsManualPayments() ?? true),
            'impersonating' => (bool) $request->session()->get('impersonator_id'),
            'staff_impersonation' => $this->staffImpersonation($request, $user),
            'platform' => [
                'name' => PlatformSetting::get('platform_name', config('coopos.name')),
                'home' => Tenancy::centralUrl('/platform'),
            ],
            'navCounts' => $this->navCounts($user, $request),
            'notifications' => $this->notificationsPayload($user),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_errors' => $request->session()->get('import_errors', []),
            ],
        ];
    }

    private function staffImpersonation(Request $request, $user): ?array
    {
        $originalId = $request->session()->get('staff_impersonator_id');
        if (! $originalId || ! $user) {
            return null;
        }

        $user->loadMissing('branch');

        return [
            'as' => $user->name,
            'original' => $request->session()->get('staff_impersonator_name') ?: 'your account',
            'branch' => TenantContext::id() ? $user->branch?->name : null,
        ];
    }

    /**
     * @return array{unread: int, recent: list<array<string, mixed>>}
     */
    private function notificationsPayload($user): array
    {
        $empty = ['unread' => 0, 'recent' => []];

        if (! $user) {
            return $empty;
        }

        try {
            return [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()
                    ->latest()
                    ->limit(15)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? 'Notification',
                        'body' => $notification->data['body'] ?? '',
                        'url' => $notification->data['url'] ?? null,
                        'category' => $notification->data['category'] ?? 'general',
                        'read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all(),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function navCounts($user, Request $request): ?array
    {
        if (! $user) {
            return null;
        }

        try {
            if ($user->isPlatformAdmin() && str_starts_with($request->path(), 'platform')) {
                return [
                    'pending' => Tenant::query()->where('status', 'pending')->count(),
                    'tickets' => SupportTicket::query()->whereIn('status', ['open', 'pending'])->count(),
                    'past_due' => Subscription::query()->where('status', 'past_due')->count(),
                ];
            }

            if (TenantContext::current() && $user->type === UserType::Staff) {
                return [
                    'pending_approvals' => ApprovalRequest::query()->where('status', 'pending')->count(),
                ];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return list<array{uuid: string, name: string, code: string, is_headquarters: bool}>
     */
    private function accessibleBranches($user): array
    {
        if (! $user || ! TenantContext::id() || $user->tenant_id !== TenantContext::id()) {
            return [];
        }

        try {
            return $user->accessibleBranches()
                ->map(fn ($branch) => [
                    'uuid' => $branch->uuid,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'is_headquarters' => (bool) $branch->is_headquarters,
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function branchPayload($user): ?array
    {
        $branch = BranchContext::current();

        if (! $branch && $user?->isBranchScoped() && $user->branch_id && TenantContext::id()) {
            $branch = $user->branch;
        }

        if (! $branch) {
            return null;
        }

        return [
            'uuid' => $branch->uuid,
            'name' => $branch->name,
            'code' => $branch->code,
            'is_headquarters' => $branch->is_headquarters,
        ];
    }
}
