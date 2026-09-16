<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Division> */
class DivisionFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => ucfirst(fake()->unique()->words(2, true)), 'sort_order' => 0];
    }
}
