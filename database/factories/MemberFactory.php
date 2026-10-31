<?php

namespace Database\Factories;

use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use App\Support\People\Occupations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'member_no' => 'MEM-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName($gender->value),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'phone' => fake()->numerify('080########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Ikeja', 'Enugu', 'Onitsha', 'Port Harcourt', 'Abuja']),
            'state' => fake()->randomElement(['Lagos', 'Enugu', 'Anambra', 'Rivers', 'FCT']),
            'occupation' => fake()->randomElement(Occupations::codes()),
            'status' => MemberStatus::Active,
            'joined_at' => fake()->dateTimeBetween('-10 years'),
        ];
    }

    /**
     * Place the member in a family with the given role.
     */
    public function inFamily(Family $family, FamilyRole $role = FamilyRole::Child): static
    {
        return $this->state(fn (array $attributes) => [
            'family_id' => $family->id,
            'family_role' => $role,
            'last_name' => $attributes['last_name'],
        ]);
    }

    /**
     * Link the member to a portal account.
     */
    public function withAccount(?User $user = null): static
    {
        return $this->afterCreating(function (Member $member) use ($user): void {
            $account = $user ?? User::factory()->create(['name' => $member->full_name]);
            $member->forceFill(['user_id' => $account->id])->save();
        });
    }
}
