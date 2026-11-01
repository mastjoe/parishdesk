<?php

namespace Database\Factories;

use App\Enums\ClergyRole;
use App\Models\ClergyAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClergyAssignment>
 */
class ClergyAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Rev. Fr. '.fake()->name('male'),
            'role' => ClergyRole::AssistantPriest,
            'starts_on' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'ends_on' => null,
        ];
    }

    /**
     * Indicate that the assignment has ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => now()->subYears(6),
            'ends_on' => now()->subYears(2),
        ]);
    }
}
