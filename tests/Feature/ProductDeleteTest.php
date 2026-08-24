<?php

namespace Tests\Feature;

use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Enums\MemberStatus;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\Member;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_product_without_payments_can_be_deleted(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $product = ContributionProduct::query()->create([
            'name' => 'Old thrift',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => ContributionFrequency::Monthly,
            'minimum_amount_kobo' => 100000,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/products")
            ->delete("/{$tenant->slug}/products/savings/{$product->uuid}")
            ->assertRedirect("/{$tenant->slug}/products");

        $this->assertSoftDeleted('contribution_products', ['id' => $product->id]);
    }

    public function test_active_product_cannot_be_deleted(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $product = ContributionProduct::query()->create([
            'name' => 'Live thrift',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => ContributionFrequency::Monthly,
            'minimum_amount_kobo' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/products")
            ->delete("/{$tenant->slug}/products/savings/{$product->uuid}")
            ->assertRedirect("/{$tenant->slug}/products")
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('contribution_products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_inactive_product_with_payments_cannot_be_deleted(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $member = Member::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'membership_number' => 'M90001',
            'status' => MemberStatus::Active,
        ]);

        $product = ContributionProduct::query()->create([
            'name' => 'Paid thrift',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => ContributionFrequency::Monthly,
            'minimum_amount_kobo' => 100000,
            'is_active' => false,
        ]);

        Contribution::query()->create([
            'member_id' => $member->id,
            'contribution_product_id' => $product->id,
            'amount_kobo' => 500000,
            'paid_on' => now()->toDateString(),
            'channel' => 'cash',
            'notes' => 'TEST-DEL-1',
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/products")
            ->delete("/{$tenant->slug}/products/savings/{$product->uuid}")
            ->assertRedirect("/{$tenant->slug}/products")
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('contribution_products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_products_page_marks_deletable_inactive_products(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        ContributionProduct::query()->create([
            'name' => 'Removable',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => ContributionFrequency::Monthly,
            'minimum_amount_kobo' => 100000,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/products")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Products/Index')
                ->where('products.0.can_delete', true)
            );
    }
}
