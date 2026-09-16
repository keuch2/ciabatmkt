<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return ['division_id' => Division::factory(), 'name' => ucfirst(fake()->unique()->words(2, true)), 'sort_order' => 0];
    }
}
