<?php

namespace Database\Factories;

use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Cooperative';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'type' => CooperativeType::Staff,
            'mode' => CooperativeMode::General,
            'status' => TenantStatus::Active,
            'email' => fake()->companyEmail(),
            'phone' => '080'.fake()->numerify('########'),
            'state' => 'Lagos',
            'approved_at' => now(),
        ];
    }
}
