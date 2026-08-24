<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'membership_number' => 'M'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '080'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => MemberStatus::Active,
            'joined_on' => now()->subMonths(6),
            'branch_id' => function (array $attributes) {
                $tenantId = $attributes['tenant_id'] instanceof Tenant
                    ? $attributes['tenant_id']->id
                    : $attributes['tenant_id'];

                return Branch::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_headquarters', true)
                    ->value('id')
                    ?? Branch::factory()->headquarters()->create(['tenant_id' => $tenantId])->id;
            },
        ];
    }
}
