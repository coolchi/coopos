<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Savings\ContributionService;
use App\Http\Controllers\Controller;
use App\Models\ContributionEnrollment;
use App\Models\ContributionProduct;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MandateController extends Controller
{
    public function store(Request $request, string $tenant, ContributionService $service)
    {
        $data = $this->validatedAmount($request);
        $member = $this->member();
        $product = $this->activeProduct($data['product_uuid']);

        $this->assertOptionalProduct($product);

        if ($this->activeEnrollment($member, $product)) {
            throw ValidationException::withMessages([
                'product_uuid' => 'You already have a mandate on this product.',
            ]);
        }

        try {
            $service->enroll($member, $product, naira_to_kobo($data['amount']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Mandate added.');
    }

    public function update(Request $request, string $tenant, ContributionEnrollment $enrollment, ContributionService $service)
    {
        $member = $this->ownedEnrollment($enrollment);
        $data = $this->validatedAmount($request, requireProduct: false);
        $product = $enrollment->product;

        if (! $product?->is_active) {
            throw ValidationException::withMessages([
                'product_uuid' => 'This product is no longer available.',
            ]);
        }

        $this->assertOptionalProduct($product);

        try {
            $service->enroll($member, $product, naira_to_kobo($data['amount']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Mandate updated.');
    }

    public function destroy(string $tenant, ContributionEnrollment $enrollment)
    {
        $this->ownedEnrollment($enrollment);
        $enrollment->delete();

        return back()->with('success', 'Mandate removed.');
    }

    /**
     * @return array{product_uuid?: string, amount: float|int|string}
     */
    private function validatedAmount(Request $request, bool $requireProduct = true): array
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:0'],
        ];

        if ($requireProduct) {
            $rules['product_uuid'] = ['required', 'uuid'];
        }

        return $request->validate($rules, [
            'product_uuid.required' => 'Select a product.',
            'amount.required' => 'Enter an amount.',
            'amount.numeric' => 'Enter a valid amount.',
        ], [
            'product_uuid' => 'product',
            'amount' => 'amount',
        ]);
    }

    private function activeProduct(string $uuid): ContributionProduct
    {
        $product = ContributionProduct::query()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_uuid' => 'Select a product.',
            ]);
        }

        return $product;
    }

    private function assertOptionalProduct(ContributionProduct $product): void
    {
        if ($product->is_required) {
            throw ValidationException::withMessages([
                'product_uuid' => 'Required products are not added as mandates.',
            ]);
        }
    }

    private function activeEnrollment(Member $member, ContributionProduct $product): ?ContributionEnrollment
    {
        return $member->enrollments()
            ->where('contribution_product_id', $product->id)
            ->where('status', 'active')
            ->first();
    }

    private function ownedEnrollment(ContributionEnrollment $enrollment): Member
    {
        $member = $this->member();
        abort_unless($enrollment->member_id === $member->id && $enrollment->status === 'active', 404);
        abort_if($enrollment->product?->is_required, 404);

        return $member;
    }

    private function member(): Member
    {
        return request()->user()->member()->firstOrFail();
    }
}
