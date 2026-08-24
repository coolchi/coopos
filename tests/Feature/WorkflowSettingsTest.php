<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\LoanStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflows_page_includes_editable_payload(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/settings/workflows")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Settings/Workflows')
                ->has('workflows')
                ->has('roles')
                ->has('types')
                ->where('workflows.0.name', fn ($name) => is_string($name) && $name !== '')
            );
    }

    public function test_admin_can_create_and_update_a_workflow(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/workflows", [
                'name' => 'Large withdrawals',
                'applies_to' => 'withdrawal',
                'min_amount' => 100000,
                'max_amount' => '',
                'is_active' => true,
                'steps' => [
                    ['name' => 'Treasurer review', 'role_name' => 'Treasurer'],
                    ['name' => 'Chairman sign-off', 'role_name' => 'Chairman'],
                ],
            ])
            ->assertRedirect();

        $workflow = ApprovalWorkflow::query()->where('name', 'Large withdrawals')->first();
        $this->assertNotNull($workflow);
        $this->assertSame(10000000, $workflow->min_amount_kobo);
        $this->assertNull($workflow->max_amount_kobo);
        $this->assertSame(2, $workflow->steps()->count());

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->put("/{$tenant->slug}/settings/workflows/{$workflow->uuid}", [
                'name' => 'Large STS withdrawals',
                'applies_to' => 'withdrawal',
                'min_amount' => 250000,
                'max_amount' => 1000000,
                'is_active' => true,
                'steps' => [
                    ['name' => 'Administrator approval', 'role_name' => 'Administrator'],
                ],
            ])
            ->assertRedirect();

        $workflow->refresh();
        $this->assertSame('Large STS withdrawals', $workflow->name);
        $this->assertSame(25000000, $workflow->min_amount_kobo);
        $this->assertSame(100000000, $workflow->max_amount_kobo);
        $this->assertSame(['Administrator approval'], $workflow->steps()->pluck('name')->all());
    }

    public function test_unused_workflow_can_be_deleted(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $workflow = ApprovalWorkflow::query()->where('applies_to', 'withdrawal')->first();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete("/{$tenant->slug}/settings/workflows/{$workflow->uuid}")
            ->assertRedirect();

        $this->assertDatabaseMissing('approval_workflows', ['uuid' => $workflow->uuid]);
    }

    public function test_workflow_with_history_cannot_be_deleted(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $workflow = ApprovalWorkflow::query()->where('applies_to', 'loan')->first();

        $member = Member::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'membership_number' => 'M20001',
            'status' => 'active',
        ]);
        $product = LoanProduct::query()->create([
            'name' => 'Emergency',
            'minimum_amount_kobo' => 100000,
            'maximum_amount_kobo' => 50000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => 'reducing_balance',
            'interest_rate_bps' => 1200,
            'guarantors_required' => 0,
            'is_active' => true,
        ]);
        $application = LoanApplication::query()->create([
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'amount_kobo' => 25000000,
            'tenure_months' => 6,
            'purpose' => 'School fees',
            'status' => LoanStatus::PendingApproval,
            'submitted_at' => now(),
        ]);
        ApprovalRequest::query()->create([
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => LoanApplication::class,
            'approvable_id' => $application->id,
            'current_step' => 1,
            'status' => ApprovalStatus::Pending,
            'requested_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/settings/workflows")
            ->delete("/{$tenant->slug}/settings/workflows/{$workflow->uuid}")
            ->assertRedirect("/{$tenant->slug}/settings/workflows");

        $this->assertDatabaseHas('approval_workflows', ['uuid' => $workflow->uuid]);
    }
}
