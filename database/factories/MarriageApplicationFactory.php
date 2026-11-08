<?php

namespace Database\Factories;

use App\Enums\MarriageCheck;
use App\Enums\MarriageStatus;
use App\Models\MarriageApplication;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarriageApplication>
 */
class MarriageApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_no' => fake()->unique()->numerify('MAR/2026/###'),
            'groom_name' => fake()->name('male'),
            'bride_name' => fake()->name('female'),
            'status' => MarriageStatus::Applied,
            'proposed_date' => fake()->dateTimeBetween('+6 weeks', '+6 months'),
            'officiant' => 'Rev. Fr. '.fake()->name('male'),
        ];
    }

    /**
     * Make the application for a groom and bride in the member register.
     */
    public function forCouple(?Member $groom = null, ?Member $bride = null): static
    {
        return $this->state(fn (array $attributes) => array_filter([
            'groom_member_id' => $groom?->id,
            'groom_name' => $groom?->full_name,
            'bride_member_id' => $bride?->id,
            'bride_name' => $bride?->full_name,
        ]));
    }

    /**
     * Tick off every check without verifying the documents yet.
     */
    public function checked(): static
    {
        return $this->state(fn (array $attributes) => [
            'checks' => MarriageCheck::values(),
        ]);
    }

    /**
     * Mark the documents as verified, ready for the banns.
     */
    public function documentsVerified(): static
    {
        return $this->checked()->state(fn (array $attributes) => [
            'status' => MarriageStatus::DocumentsVerified,
            'documents_verified_at' => now()->subWeeks(4),
        ]);
    }

    /**
     * Publish the banns the given number of times, a week apart.
     */
    public function withBanns(int $published = MarriageApplication::BANNS_REQUIRED): static
    {
        return $this->documentsVerified()
            ->state(fn (array $attributes) => ['status' => MarriageStatus::Banns])
            ->afterCreating(function (MarriageApplication $application) use ($published): void {
                foreach (range(1, $published) as $number) {
                    $application->publications()->create([
                        'number' => $number,
                        'published_on' => now()->subWeeks($published - $number + 1)->startOfDay(),
                    ]);
                }
            });
    }

    /**
     * Clear the couple to marry after the three banns.
     */
    public function cleared(): static
    {
        return $this->withBanns()->state(fn (array $attributes) => [
            'status' => MarriageStatus::Cleared,
            'cleared_at' => now()->subDay(),
        ]);
    }
}
