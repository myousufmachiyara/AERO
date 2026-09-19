<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Airline>
 */
class AirlineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Airways',
            'numeric_code' => $this->faker->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
