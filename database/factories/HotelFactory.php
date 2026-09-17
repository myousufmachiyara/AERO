<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hotel>
 */
class HotelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Hotel',
            'city' => $this->faker->city(),
            'star_rating' => $this->faker->numberBetween(3, 5),
            'is_active' => true,
        ];
    }
}
