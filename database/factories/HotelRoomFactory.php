<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HotelRoom>
 */
class HotelRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_type' => $this->faker->randomElement(['Single', 'Double', 'Triple', 'Quad']),
            'capacity' => $this->faker->numberBetween(1, 4),
            'default_rate' => $this->faker->randomFloat(2, 50, 500),
            'currency' => 'SAR',
            'is_active' => true,
        ];
    }
}
