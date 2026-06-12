<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'region_code' => 'RG-' . $this->faker->unique()->numberBetween(1, 999),
            'region_name' => $this->faker->city(),
            'status' => 'active',
        ];
    }
}
