<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\ProfileChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileChangeRequest>
 */
class ProfileChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'changes' => ['phone' => fake()->numerify('081########')],
            'original' => ['phone' => null],
        ];
    }
}
