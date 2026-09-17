<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'CUS-' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->name(),
            'customer_type' => 'individual',
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
        ];
    }
}
