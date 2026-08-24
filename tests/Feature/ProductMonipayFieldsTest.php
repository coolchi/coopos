<?php

namespace Tests\Feature;

use App\Models\ContributionProduct;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductMonipayFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_page_loads_without_blocking_on_monipay(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        config(['services.monipay.secret' => 'sk_test_coopos']);

        Http::fake([
            'api.monipay.ng/*' => Http::response(['status' => true, 'data' => []], 200),
        ]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/products")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Products/Index')
                ->where('monipay_configured', true)
                ->where('revenue_heads', [])
                ->where('settlement_banks', [])
            );
    }

    public function test_monipay_options_endpoint_returns_catalog(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        config(['services.monipay.secret' => 'sk_test_coopos']);

        Http::fake([
            'api.monipay.ng/payment/revenue-head*' => Http::response([
                'status' => true,
                'message' => 'ok',
                'data' => [
                    'data' => [
                        [
                            'id' => 'REV1',
                            'rev_name' => 'Member Savings',
                            'settlement_bank_id' => 'SB1',
                            'settlement_bank' => [
                                'id' => 'SB1',
                                'name' => 'Main',
                                'account_no' => '0123456789',
                                'bank_name' => 'GTBank',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'api.monipay.ng/payment/settlement-bank*' => Http::response([
                'status' => true,
                'message' => '',
                'data' => [
                    [
                        'id' => 'SB1',
                        'name' => 'Main Settlement',
                        'account_no' => '0123456789',
                        'account_name' => 'Al-Barakah Cooperative',
                        'bank_code' => '058',
                        'bank_name' => 'GTBank',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->getJson("/{$tenant->slug}/products/monipay-options")
            ->assertOk()
            ->assertJsonPath('monipay_configured', true)
            ->assertJsonPath('revenue_heads.0.value', 'REV1')
            ->assertJsonPath('settlement_banks.0.value', 'SB1');
    }

    public function test_staff_can_link_existing_revenue_head_and_settlement_bank(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        config(['services.monipay.secret' => 'sk_test_coopos']);

        Http::fake([
            'api.monipay.ng/payment/revenue-head*' => Http::response([
                'status' => true,
                'data' => [
                    'data' => [
                        [
                            'id' => 'REV1',
                            'rev_name' => 'Member Savings',
                            'settlement_bank_id' => 'SB1',
                        ],
                    ],
                ],
            ], 200),
            'api.monipay.ng/payment/settlement-bank*' => Http::response([
                'status' => true,
                'data' => [
                    [
                        'id' => 'SB1',
                        'name' => 'Main Settlement',
                        'account_no' => '0123456789',
                        'account_name' => 'Al-Barakah Cooperative',
                        'bank_code' => '058',
                        'bank_name' => 'GTBank',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/products", [
                'kind' => 'savings',
                'name' => 'Member savings',
                'finance_mode' => 'conventional',
                'bucket' => 'savings',
                'frequency' => 'monthly',
                'minimum_amount' => 1000,
                'default_amount' => 15000,
                'revenue_head_id' => 'REV1',
                'settlement_bank_id' => 'SB1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contribution_products', [
            'name' => 'Member savings',
            'monipay_revenue_head_id' => 'REV1',
            'monipay_revenue_head_name' => 'Member Savings',
            'monipay_settlement_bank_id' => 'SB1',
            'monipay_settlement_account_no' => '0123456789',
            'monipay_settlement_bank_name' => 'GTBank',
        ]);
    }

    public function test_staff_can_create_a_new_revenue_head_from_product_form(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        config(['services.monipay.secret' => 'sk_test_coopos']);

        Http::fake([
            'api.monipay.ng/payment/revenue-head/create' => Http::response([
                'success' => true,
                'message' => 'RevenueHead Created successfully!',
                'revhead' => 'REVNEW1',
                'data' => [
                    'rev_name' => 'Acceptance Fee',
                    'settlement_bank' => [
                        'id' => 'SB1',
                        'name' => 'Main',
                        'account_no' => '0123456789',
                        'bank_name' => 'GTBank',
                    ],
                ],
            ], 200),
            'api.monipay.ng/payment/revenue-head*' => Http::response([
                'status' => true,
                'data' => ['data' => []],
            ], 200),
            'api.monipay.ng/payment/settlement-bank*' => Http::response([
                'status' => true,
                'data' => [
                    [
                        'id' => 'SB1',
                        'name' => 'Main Settlement',
                        'account_no' => '0123456789',
                        'account_name' => 'Al-Barakah Cooperative',
                        'bank_code' => '058',
                        'bank_name' => 'GTBank',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/products", [
                'kind' => 'savings',
                'name' => 'Acceptance Fee',
                'finance_mode' => 'conventional',
                'bucket' => 'savings',
                'frequency' => 'monthly',
                'minimum_amount' => 50000,
                'revenue_head_id' => '__create__',
                'new_revenue_head_name' => 'Acceptance Fee',
                'settlement_bank_id' => 'SB1',
            ])
            ->assertRedirect();

        $product = ContributionProduct::query()->where('name', 'Acceptance Fee')->first();
        $this->assertNotNull($product);
        $this->assertSame('REVNEW1', $product->monipay_revenue_head_id);
        $this->assertSame('Acceptance Fee', $product->monipay_revenue_head_name);
        $this->assertSame('SB1', $product->monipay_settlement_bank_id);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.monipay.ng/payment/revenue-head/create'
            && $request['revname'] === 'Acceptance Fee'
            && $request['account_no'] === '0123456789'
            && $request['bank_code'] === '058');
    }

    public function test_staff_can_link_monipay_fields_on_islamic_products(): void
    {
        [$tenant, $admin] = $this->makeCooperative(['mode' => 'islamic']);
        config(['services.monipay.secret' => 'sk_test_coopos']);

        Http::fake([
            'api.monipay.ng/payment/revenue-head*' => Http::response([
                'status' => true,
                'data' => [
                    'data' => [
                        [
                            'id' => 'REV1',
                            'rev_name' => 'Qard Hasan',
                            'settlement_bank_id' => 'SB1',
                        ],
                    ],
                ],
            ], 200),
            'api.monipay.ng/payment/settlement-bank*' => Http::response([
                'status' => true,
                'data' => [
                    [
                        'id' => 'SB1',
                        'name' => 'Main Settlement',
                        'account_no' => '0123456789',
                        'account_name' => 'Al-Barakah Cooperative',
                        'bank_code' => '058',
                        'bank_name' => 'GTBank',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/products", [
                'kind' => 'islamic',
                'name' => 'Qard Hasan financing',
                'structure' => 'qard_hasan',
                'minimum_amount' => 20000,
                'maximum_amount' => 300000,
                'minimum_tenure_months' => 1,
                'maximum_tenure_months' => 12,
                'admin_fee' => 500,
                'revenue_head_id' => 'REV1',
                'settlement_bank_id' => 'SB1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('islamic_financing_products', [
            'name' => 'Qard Hasan financing',
            'monipay_revenue_head_id' => 'REV1',
            'monipay_revenue_head_name' => 'Qard Hasan',
            'monipay_settlement_bank_id' => 'SB1',
            'monipay_settlement_account_no' => '0123456789',
        ]);
    }
}
