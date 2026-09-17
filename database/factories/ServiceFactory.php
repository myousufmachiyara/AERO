<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Airport Meet & Greet', 'Ziyarat Tour', 'SIM Card', 'Zamzam Water', 'Local Guide']),
            'category' => 'other',
            'default_unit' => 'each',
            'is_active' => true,
        ];
    }
}
