<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Coaster', 'Hiace', 'Land Cruiser', 'Sedan']) . ' ' . $this->faker->numerify('###'),
            'type' => $this->faker->randomElement(['bus', 'van', 'suv', 'sedan']),
            'capacity' => $this->faker->numberBetween(4, 45),
            'is_active' => true,
        ];
    }
}
