<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Access\StaffPermissions;
use App\Domain\Demo\DemoDataService;
use App\Domain\Lending\LoanEligibilityService;
use App\Domain\Savings\DefaultSavingsCatalog;
use App\Enums\AppMode;
use App\Enums\ApprovalStatus;
use App\Enums\ContributionFrequency;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\ContributionProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FiscalPeriod;
use App\Support\TenantTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function organization()
    {
        $tenant = tenant();

        return Inertia::render('Tenant/Settings/Organization', [
            'tenant' => [
                'name' => $tenant->name,
                'legal_name' => $tenant->legal_name,
                'slogan' => $tenant->slogan,
                'registration_number' => $tenant->registration_number,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'state' => $tenant->state,
                'lga' => $tenant->lga,
                'brand_color' => $tenant->brand_color,
                'theme' => TenantTheme::resolve($tenant)['key'],
                'logo_url' => $tenant->logoUrl(),
            ],
            'themes' => TenantTheme::options(),
            'settings' => $this->settingsPayload($tenant),
            'states' => config('coopos.nigeria_states'),
        ]);
    }

    public function updateOrganization(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'slogan' => ['nullable', 'string', 'max:80'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string'],
            'lga' => ['nullable', 'string', 'max:80'],
            'theme' => ['nullable', 'string', Rule::in(TenantTheme::keys())],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['boolean'],
            'membership_number_prefix' => ['nullable', 'string', 'max:8'],
            'membership_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'fiscal_year_one_start_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'monipay_secret' => ['nullable', 'string', 'max:200'],
            'monipay_public' => ['nullable', 'string', 'max:200'],
            'transaction_access_code' => ['nullable', 'string', 'min:4', 'max:32'],
            'allow_manual_payments' => ['nullable', 'boolean'],
        ]);

        $themeKey = $data['theme'] ?? TenantTheme::DEFAULT;
        $palette = TenantTheme::catalog()[$themeKey] ?? TenantTheme::catalog()[TenantTheme::DEFAULT];

        tenant()->update([
            ...$request->only(
                'name', 'legal_name', 'registration_number', 'phone', 'email',
                'address', 'city', 'state', 'lga',
            ),
            'theme' => $themeKey,
            'brand_color' => $palette['brand'],
            'slogan' => filled($data['slogan'] ?? null) ? trim($data['slogan']) : null,
            'logo_path' => $this->storeLogo($request, tenant()),
        ]);

        $startMonth = (int) ($data['fiscal_year_start_month'] ?? 1);
        $yearOne = (int) ($data['fiscal_year_one_start_year'] ?? now()->year);

        tenant()->settings->update([
            'membership_number_prefix' => strtoupper($data['membership_number_prefix'] ?? 'M'),
            'membership_fee_kobo' => naira_to_kobo($data['membership_fee'] ?? 0),
            'currency' => $data['currency'] ?? 'NGN',
            'fiscal_year_start_month' => $startMonth,
            'fiscal_year_one_start_year' => $yearOne,
        ]);

        $extras = tenant()->settings->extras ?? [];
        $monipay = is_array($extras['monipay'] ?? null) ? $extras['monipay'] : [];
        if (! empty($data['monipay_secret'])) {
            $monipay['secret'] = $data['monipay_secret'];
        }
        if (array_key_exists('monipay_public', $data)) {
            $monipay['public'] = $data['monipay_public'] ?: ($monipay['public'] ?? null);
        }
        $extras['monipay'] = $monipay;
        if (! empty($data['transaction_access_code']) && $request->user()?->isCentralAdmin()) {
            $extras['transaction_access_code_hash'] = Hash::make($data['transaction_access_code']);
        }
        if ($request->user()?->isCentralAdmin() && $request->exists('allow_manual_payments')) {
            $extras['allow_manual_payments'] = $request->boolean('allow_manual_payments');
        }
        tenant()->settings->update(['extras' => $extras]);

        return back()->with('success', 'Organization settings saved.');
    }

    private function storeLogo(Request $request, Tenant $tenant): ?string
    {
        if ($request->boolean('remove_logo') && ! $request->hasFile('logo')) {
            $this->forgetLogo($tenant);

            return null;
        }

        if (! $request->hasFile('logo')) {
            return $tenant->logo_path;
        }

        $this->forgetLogo($tenant);

        return $request->file('logo')->store('tenants/'.$tenant->id, 'public');
    }

    private function forgetLogo(Tenant $tenant): void
    {
        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }
    }

    public function policies()
    {
        app(DefaultSavingsCatalog::class)->ensure();

        return Inertia::render('Tenant/Settings/Policies', [
            'settings' => $this->settingsPayload(tenant()),
            'contribution_products' => $this->loanConditionProducts(),
        ]);
    }

    public function updatePolicies(Request $request)
    {
        $data = $request->validate([
            'require_membership_approval' => ['boolean'],
            'require_verified_bank_for_payout' => ['boolean'],
            'guarantor_exposure_cap' => ['nullable', 'numeric', 'min:0'],
            'max_active_loans' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_debt_to_savings_percent' => ['nullable', 'numeric', 'min:0'],
            'loan_min_savings_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'loan_min_shares_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'loan_required_product_uuids' => ['sometimes', 'array'],
            'loan_required_product_uuids.*' => ['uuid'],
            'loan_check_attendance' => ['boolean'],
            'loan_max_absences_per_year' => ['nullable', 'integer', 'min:1', 'max:50'],
            'email_notifications' => ['boolean'],
            'sms_notifications' => ['boolean'],
        ]);

        $channels = [];
        if ($request->boolean('email_notifications')) {
            $channels[] = 'email';
        }
        if ($request->boolean('sms_notifications')) {
            $channels[] = 'sms';
        }

        $policies = [
            'require_membership_approval' => $request->boolean('require_membership_approval'),
            'require_verified_bank_for_payout' => $request->boolean('require_verified_bank_for_payout'),
            'guarantor_exposure_cap_kobo' => naira_to_kobo($data['guarantor_exposure_cap'] ?? 0),
            'max_active_loans' => $data['max_active_loans'] ?? 1,
            'max_debt_to_savings_bps' => (int) round(($data['max_debt_to_savings_percent'] ?? 200) * 100),
            'notification_channels' => $channels,
        ];

        if ($request->exists('loan_min_savings_percent')) {
            $policies['loan_min_savings_percent'] = (int) ($data['loan_min_savings_percent'] ?? 50);
        }
        if ($request->exists('loan_min_shares_percent')) {
            $policies['loan_min_shares_percent'] = (int) ($data['loan_min_shares_percent'] ?? 10);
        }
        if ($request->exists('loan_max_absences_per_year')) {
            $policies['loan_max_absences_per_year'] = (int) ($data['loan_max_absences_per_year'] ?? 3);
        }
        if ($request->exists('loan_check_attendance')) {
            $policies['loan_check_attendance'] = $request->boolean('loan_check_attendance');
        }
        if ($request->exists('loan_required_product_uuids')) {
            $policies['loan_required_product_uuids'] = ContributionProduct::query()
                ->where('is_active', true)
                ->whereIn('uuid', $request->input('loan_required_product_uuids', []))
                ->pluck('uuid')
                ->values()
                ->all();
        }

        tenant()->settings->update($policies);

        return back()->with('success', 'Policies saved.');
    }

    public function users()
    {
        setPermissionsTeamId(tenant()->id);

        return Inertia::render('Tenant/Settings/Users', [
            'users' => User::query()->with('branch')->where('tenant_id', tenant()->id)->where('type', UserType::Staff)->get()
                ->map(fn ($u) => [
                    'uuid' => $u->uuid,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->getRoleNames()->values(),
                    'branch_uuid' => $u->branch_id ? $u->branch?->uuid : null,
                    'branch' => $u->branch?->name,
                    'last_login_at' => optional($u->last_login_at)->toDayDateTimeString(),
                ]),
            'roles' => Role::query()->where('tenant_id', tenant()->id)->where('name', '!=', 'Member')->pluck('name'),
            'branches' => Branch::query()->orderByDesc('is_headquarters')->orderBy('name')->get(['uuid', 'name', 'code']),
        ]);
    }

    public function storeUser(Request $request)
    {
        setPermissionsTeamId(tenant()->id);
        $roles = Role::query()->where('tenant_id', tenant()->id)->where('name', '!=', 'Member')->pluck('name')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in($roles)],
            'branch_uuid' => [Rule::requiredIf($request->input('role') === 'Branch Admin'), 'nullable', 'uuid'],
        ]);

        $branch = ! empty($data['branch_uuid'])
            ? Branch::query()->where('uuid', $data['branch_uuid'])->firstOrFail()
            : null;

        abort_if($data['role'] === 'Branch Admin' && ! $branch, 422, 'Branch admins must be assigned to a branch.');

        $user = User::query()->create([
            'tenant_id' => tenant()->id,
            'branch_id' => $data['role'] === 'Administrator' ? null : $branch?->id,
            'type' => UserType::Staff,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);

        if ($branch && $data['role'] === 'Branch Admin' && ! $branch->admin_user_id) {
            $branch->update(['admin_user_id' => $user->id]);
        }

        return back()->with('success', 'Staff member invited.');
    }

    public function updateUserRole(Request $request, string $tenant, User $user)
    {
        abort_unless($user->tenant_id === tenant()->id && $user->type === UserType::Staff, 404);
        setPermissionsTeamId(tenant()->id);

        $roles = Role::query()->where('tenant_id', tenant()->id)->where('name', '!=', 'Member')->pluck('name')->all();
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in($roles)],
            'branch_uuid' => [Rule::requiredIf($request->input('role') === 'Branch Admin'), 'nullable', 'uuid'],
        ]);

        if ($user->hasRole('Administrator') && $data['role'] !== 'Administrator') {
            $admins = User::query()->where('tenant_id', tenant()->id)->get()
                ->filter(fn (User $u) => $u->hasRole('Administrator'));
            if ($admins->count() <= 1) {
                return back()->with('error', 'Keep at least one administrator.');
            }
        }

        $user->syncRoles([$data['role']]);

        if ($data['role'] === 'Administrator') {
            $user->update(['branch_id' => null]);
        } elseif (! empty($data['branch_uuid'])) {
            $branch = Branch::query()->where('uuid', $data['branch_uuid'])->firstOrFail();
            $user->update(['branch_id' => $branch->id]);
            if ($data['role'] === 'Branch Admin' && ! $branch->admin_user_id) {
                $branch->update(['admin_user_id' => $user->id]);
            }
        }

        return back()->with('success', 'Role updated.');
    }

    public function access()
    {
        setPermissionsTeamId(tenant()->id);

        $roles = Role::query()->where('tenant_id', tenant()->id)->where('name', '!=', 'Member')->orderBy('name')->get();
        $permissions = collect(StaffPermissions::catalog());

        return Inertia::render('Tenant/Settings/Access', [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ]),
            'permissions' => $permissions,
            'groups' => $this->permissionGroups($permissions->all()),
        ]);
    }

    public function updateRolePermissions(Request $request, string $tenant, int $role)
    {
        setPermissionsTeamId(tenant()->id);
        $roleModel = Role::query()->where('tenant_id', tenant()->id)->whereKey($role)->firstOrFail();
        abort_if($roleModel->name === 'Member', 403);

        $allowed = StaffPermissions::catalog();
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($allowed)],
        ]);

        $perms = $data['permissions'] ?? [];
        if ($roleModel->name === 'Administrator') {
            $perms = array_values(array_unique([...$perms, ...StaffPermissions::administratorLocked()]));
        }

        $roleModel->syncPermissions($perms);

        return back()->with('success', $roleModel->name.' permissions saved.');
    }

    public function activity(Request $request)
    {
        $staffIds = User::query()->where('tenant_id', tenant()->id)->pluck('id');

        $logs = Activity::query()
            ->with('causer')
            ->where(function ($query) use ($staffIds) {
                $query->where(function ($inner) {
                    $inner->where('subject_type', Tenant::class)->where('subject_id', tenant()->id);
                })->orWhere(function ($inner) use ($staffIds) {
                    $inner->where('causer_type', User::class)->whereIn('causer_id', $staffIds);
                });
            })
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('description', 'like', "%{$q}%")
                        ->orWhere('event', 'like', "%{$q}%")
                        ->orWhere('properties', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'subject' => class_basename((string) $log->subject_type) ?: '—',
                'causer' => $log->causer?->name ?? 'System',
                'device' => $log->getExtraProperty('device') ?: '—',
                'ip' => $log->getExtraProperty('ip'),
                'created_at' => $log->created_at?->toDayDateTimeString(),
            ]);

        return Inertia::render('Tenant/Settings/Activity', [
            'logs' => $logs,
            'filters' => $request->only('q'),
        ]);
    }

    public function workflows()
    {
        return Inertia::render('Tenant/Settings/Workflows', [
            'workflows' => ApprovalWorkflow::query()
                ->with('steps')
                ->withCount([
                    'requests',
                    'requests as pending_requests_count' => fn ($q) => $q->where('status', ApprovalStatus::Pending),
                ])
                ->orderBy('applies_to')
                ->orderBy('min_amount_kobo')
                ->get()
                ->map(fn (ApprovalWorkflow $w) => $this->presentWorkflow($w)),
            'roles' => $this->approverRoles(),
            'types' => $this->workflowTypes(),
        ]);
    }

    public function storeWorkflow(Request $request)
    {
        $data = $this->validatedWorkflow($request);

        DB::transaction(function () use ($data) {
            $workflow = ApprovalWorkflow::query()->create([
                'name' => $data['name'],
                'applies_to' => $data['applies_to'],
                'min_amount_kobo' => naira_to_kobo($data['min_amount']),
                'max_amount_kobo' => $this->maxAmountKobo($data['max_amount'] ?? null),
                'is_active' => $data['is_active'],
            ]);
            $this->syncSteps($workflow, $data['steps']);
        });

        return back()->with('success', 'Workflow created.');
    }

    public function updateWorkflow(Request $request, string $tenant, ApprovalWorkflow $workflow)
    {
        $data = $this->validatedWorkflow($request);

        DB::transaction(function () use ($workflow, $data) {
            $workflow->update([
                'name' => $data['name'],
                'applies_to' => $data['applies_to'],
                'min_amount_kobo' => naira_to_kobo($data['min_amount']),
                'max_amount_kobo' => $this->maxAmountKobo($data['max_amount'] ?? null),
                'is_active' => $data['is_active'],
            ]);
            $this->syncSteps($workflow, $data['steps']);

            $maxStep = $workflow->steps()->count();
            $workflow->requests()
                ->where('status', ApprovalStatus::Pending)
                ->where('current_step', '>', $maxStep)
                ->update(['current_step' => $maxStep]);
        });

        return back()->with('success', $workflow->name.' saved.');
    }

    public function toggleWorkflow(string $tenant, ApprovalWorkflow $workflow)
    {
        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back()->with('success', $workflow->name.' '.($workflow->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroyWorkflow(string $tenant, ApprovalWorkflow $workflow)
    {
        if ($workflow->requests()->exists()) {
            return back()->with('error', 'This workflow has approval history and cannot be deleted. Disable it instead.');
        }

        $workflow->delete();

        return back()->with('success', $workflow->name.' deleted.');
    }

    public function demoData(DemoDataService $demo)
    {
        $this->assertCanManageDemo();

        return Inertia::render('Tenant/Settings/DemoData', [
            'app_mode' => tenant()->settings?->appMode()->value ?? AppMode::Demo->value,
            'app_mode_label' => tenant()->settings?->appMode()->label() ?? AppMode::Demo->label(),
            'is_demo' => (bool) tenant()->settings?->isDemo(),
            'seeded_total' => $demo->seededTotal(),
            'features' => $demo->features(),
        ]);
    }

    public function updateAppMode(Request $request)
    {
        $this->assertCanManageDemo();

        $data = $request->validate([
            'app_mode' => ['required', Rule::enum(AppMode::class)],
        ]);

        tenant()->settings->update(['app_mode' => AppMode::from($data['app_mode'])]);

        $mode = AppMode::from($data['app_mode']);

        return back()->with('success', $mode === AppMode::Live
            ? 'App is now Live. Demo seeding is turned off.'
            : 'App is now in Demo mode. You can seed and clear sample data.');
    }

    public function seedDemo(Request $request, DemoDataService $demo)
    {
        $this->assertCanManageDemo();

        $data = $request->validate([
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['string'],
        ]);

        try {
            $created = $demo->seed($data['features']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $modules = collect($created)->filter(fn ($count) => $count > 0)->keys()->count();

        return back()->with('success', $modules === 0
            ? 'Nothing new was added. Sample data for the selected modules may already exist.'
            : 'Sample data seeded for '.$modules.' module'.($modules === 1 ? '' : 's').'.');
    }

    public function resetDemo(Request $request, DemoDataService $demo)
    {
        $this->assertCanManageDemo();

        $data = $request->validate([
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['string'],
        ]);

        try {
            $removed = $demo->reset($data['features']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = array_sum($removed);

        return back()->with('success', $count === 0
            ? 'No sample data to clear for the selected modules.'
            : 'Cleared '.$count.' sample record'.($count === 1 ? '' : 's').'.');
    }

    private function assertCanManageDemo(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(Tenant $tenant): array
    {
        $settings = $tenant->settings;
        $channels = $settings?->notification_channels ?? [];

        return [
            'membership_fee_kobo' => $settings?->membership_fee_kobo ?? 0,
            'membership_number_prefix' => $settings?->membership_number_prefix ?: 'M',
            'membership_number_next' => $settings?->membership_number_next ?? 1,
            'currency' => $settings?->currency ?: 'NGN',
            'fiscal_year_start_month' => $settings?->fiscal_year_start_month ?? 1,
            'fiscal_year_one_start_year' => $settings?->fiscal_year_one_start_year
                ?? FiscalPeriod::currentStartYear((int) ($settings?->fiscal_year_start_month ?? 1)),
            'require_membership_approval' => (bool) ($settings?->require_membership_approval ?? true),
            'require_verified_bank_for_payout' => (bool) ($settings?->require_verified_bank_for_payout ?? true),
            'guarantor_exposure_cap_kobo' => $settings?->guarantor_exposure_cap_kobo ?? 0,
            'max_active_loans' => $settings?->max_active_loans ?? 1,
            'max_debt_to_savings_bps' => $settings?->max_debt_to_savings_bps ?? 20000,
            'loan_min_savings_percent' => $settings?->loan_min_savings_percent ?? 50,
            'loan_min_shares_percent' => $settings?->loan_min_shares_percent ?? 10,
            'loan_required_product_uuids' => app(LoanEligibilityService::class)
                ->requiredProducts($settings)
                ->pluck('uuid')
                ->values()
                ->all(),
            'loan_check_attendance' => (bool) ($settings?->loan_check_attendance ?? true),
            'loan_max_absences_per_year' => $settings?->loan_max_absences_per_year ?? 3,
            'email_notifications' => in_array('email', $channels, true),
            'sms_notifications' => in_array('sms', $channels, true),
            'monipay_public' => $settings?->extras['monipay']['public'] ?? '',
            'monipay_secret_set' => filled($settings?->extras['monipay']['secret'] ?? config('services.monipay.secret')),
            'transaction_access_code_set' => filled($settings?->extras['transaction_access_code_hash'] ?? null),
            'can_set_transaction_access_code' => (bool) auth()->user()?->isCentralAdmin(),
            'allow_manual_payments' => (bool) ($settings?->allowsManualPayments() ?? true),
            'can_set_manual_payments' => (bool) auth()->user()?->isCentralAdmin(),
        ];
    }

    /**
     * @return list<array{uuid: string, name: string, bucket: string, bucket_label: string, frequency: string, hint: string}>
     */
    private function loanConditionProducts(): array
    {
        return ContributionProduct::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (ContributionProduct $product) => sprintf('%02d-%s', $product->bucket?->sortOrder() ?? 99, mb_strtolower($product->name)))
            ->values()
            ->map(function (ContributionProduct $product) {
                $frequency = $product->frequency instanceof ContributionFrequency
                    ? $product->frequency
                    : ContributionFrequency::tryFrom((string) $product->frequency);

                $hint = match ($frequency) {
                    ContributionFrequency::OneTime => 'Must be paid once',
                    ContributionFrequency::Yearly => 'Must be paid this financial year',
                    default => 'Must have a payment this financial year',
                };

                return [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'bucket' => $product->bucket?->value,
                    'bucket_label' => $product->bucket?->label() ?? 'Other',
                    'frequency' => $frequency?->label() ?? '',
                    'hint' => $hint,
                ];
            })
            ->all();
    }

    /**
     * @param  list<string>  $permissions
     * @return list<array{label: string, items: list<string>}>
     */
    private function permissionGroups(array $permissions): array
    {
        $groups = StaffPermissions::groups();

        return collect($groups)
            ->map(fn ($items, $label) => [
                'label' => $label,
                'items' => array_values(array_intersect($items, $permissions)),
            ])
            ->filter(fn ($group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWorkflow(ApprovalWorkflow $workflow): array
    {
        return [
            'uuid' => $workflow->uuid,
            'name' => $workflow->name,
            'applies_to' => $workflow->applies_to,
            'applies_to_label' => $this->workflowTypeLabel($workflow->applies_to),
            'min' => money((int) $workflow->min_amount_kobo),
            'max' => $workflow->max_amount_kobo === null ? 'No ceiling' : money((int) $workflow->max_amount_kobo),
            'min_amount' => round(((int) $workflow->min_amount_kobo) / 100, 2),
            'max_amount' => $workflow->max_amount_kobo === null ? null : round(((int) $workflow->max_amount_kobo) / 100, 2),
            'is_active' => $workflow->is_active,
            'requests_count' => (int) ($workflow->requests_count ?? 0),
            'pending_count' => (int) ($workflow->pending_requests_count ?? 0),
            'can_delete' => (int) ($workflow->requests_count ?? 0) === 0,
            'steps' => $workflow->steps->map(fn ($step) => [
                'id' => $step->id,
                'position' => $step->position,
                'name' => $step->name,
                'role_name' => $step->role_name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedWorkflow(Request $request): array
    {
        $roles = $this->approverRoles();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'applies_to' => ['required', 'string', Rule::in(['loan', 'withdrawal', 'islamic_financing'])],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:8'],
            'steps.*.name' => ['required', 'string', 'max:120'],
            'steps.*.role_name' => ['required', 'string', Rule::in($roles)],
        ], [
            'name.required' => 'Name the workflow.',
            'steps.required' => 'Add at least one approval step.',
            'steps.min' => 'Add at least one approval step.',
            'steps.*.name.required' => 'Name this step.',
            'steps.*.role_name.required' => 'Choose who approves this step.',
            'steps.*.role_name.in' => 'Choose a staff role for this step.',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $max = $data['max_amount'] ?? null;
        if ($max !== null && $max !== '' && (float) $max < (float) $data['min_amount']) {
            throw ValidationException::withMessages([
                'max_amount' => 'The ceiling must be at least the minimum amount.',
            ]);
        }

        return $data;
    }

    /**
     * @param  list<array{name: string, role_name: string}>  $steps
     */
    private function syncSteps(ApprovalWorkflow $workflow, array $steps): void
    {
        $workflow->steps()->delete();

        foreach (array_values($steps) as $index => $step) {
            $workflow->steps()->create([
                'position' => $index + 1,
                'name' => $step['name'],
                'role_name' => $step['role_name'],
                'required_approvals' => 1,
            ]);
        }
    }

    private function maxAmountKobo(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return naira_to_kobo($amount);
    }

    /**
     * @return list<string>
     */
    private function approverRoles(): array
    {
        setPermissionsTeamId(tenant()->id);

        return Role::query()
            ->where('tenant_id', tenant()->id)
            ->where('name', '!=', 'Member')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function workflowTypes(): array
    {
        return [
            ['value' => 'withdrawal', 'label' => 'Withdrawals'],
            ['value' => 'loan', 'label' => 'Loans'],
            ['value' => 'islamic_financing', 'label' => 'Islamic financing'],
        ];
    }

    private function workflowTypeLabel(string $appliesTo): string
    {
        return match ($appliesTo) {
            'loan' => 'Loans',
            'withdrawal' => 'Withdrawals',
            'islamic_financing' => 'Islamic financing',
            default => str_replace('_', ' ', $appliesTo),
        };
    }
}
