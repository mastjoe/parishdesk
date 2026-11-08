<?php

namespace Database\Factories;

use App\Models\BannsObjection;
use App\Models\MarriageApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannsObjection>
 */
class BannsObjectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marriage_application_id' => MarriageApplication::factory()->withBanns(1),
            'raised_by' => fake()->name(),
            'raised_on' => now()->subDays(3)->startOfDay(),
            'details' => 'Says the groom was previously married in another parish.',
        ];
    }

    /**
     * Mark the objection as resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolution' => 'The earlier marriage was declared null by the diocesan tribunal.',
            'resolved_at' => now()->subDay(),
        ]);
    }
}
