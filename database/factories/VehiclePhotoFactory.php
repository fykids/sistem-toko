<?php

namespace Database\Factories;

use App\Models\CarTransaction;
use App\Models\VehiclePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiclePhoto>
 */
class VehiclePhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_transaction_id' => CarTransaction::factory(),
            'path' => 'vehicle-photos/'.$this->faker->uuid().'.jpg',
            'is_cover' => false,
            'position' => 0,
        ];
    }
}
