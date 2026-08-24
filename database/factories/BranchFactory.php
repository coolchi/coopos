<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->city().' Branch',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'city' => fake()->city(),
            'state' => 'Lagos',
            'is_headquarters' => false,
            'is_active' => true,
        ];
    }

    public function headquarters(): static
    {
        return $this->state(fn () => [
            'name' => 'CENTRAL BRANCH',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
    }
}
