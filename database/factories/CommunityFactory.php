<?php

namespace Database\Factories;

use App\Enums\CommunityType;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'St. '.fake()->unique()->firstName().' '.fake()->randomElement(['Community', 'Station', 'Zone']),
            'type' => fake()->randomElement(CommunityType::cases()),
            'description' => null,
        ];
    }
}
