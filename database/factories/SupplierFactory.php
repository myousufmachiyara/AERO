<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'SUP-' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['airline', 'hotel', 'visa_agency', 'transport', 'other']),
            'contact_person' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
        ];
    }
}
