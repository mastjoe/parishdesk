<?php

namespace Database\Factories;

use App\Models\BannsPublication;
use App\Models\MarriageApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannsPublication>
 */
class BannsPublicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marriage_application_id' => MarriageApplication::factory()->documentsVerified(),
            'number' => 1,
            'published_on' => now()->subWeek()->startOfDay(),
        ];
    }
}
