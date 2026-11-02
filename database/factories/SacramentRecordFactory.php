<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\HolyOrder;
use App\Enums\SacramentType;
use App\Models\Member;
use App\Models\SacramentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SacramentRecord>
 */
class SacramentRecordFactory extends Factory
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
            'type' => SacramentType::Baptism,
            'person_name' => fake()->name($gender->value),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-30 years', '-2 years'),
            'received_on' => fake()->dateTimeBetween('-1 year', '-1 week'),
            'elsewhere' => false,
            'minister' => 'Rev. Fr. '.fake()->name('male'),
            'father_name' => fake()->name('male'),
            'mother_name' => fake()->name('female'),
            'register_book' => (string) fake()->numberBetween(1, 9),
            'register_page' => (string) fake()->numberBetween(1, 200),
            'entry_no' => fake()->unique()->numerify('####'),
        ];
    }

    /**
     * Record the sacrament as received by a member, using their details.
     */
    public function forMember(Member $member): static
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $member->id,
            'person_name' => $member->full_name,
            'gender' => $member->gender,
            'date_of_birth' => $member->date_of_birth,
        ]);
    }

    /**
     * Record a sacrament of the given type.
     */
    public function ofType(SacramentType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'father_name' => $type->recordsParents() ? $attributes['father_name'] : null,
            'mother_name' => $type->recordsParents() ? $attributes['mother_name'] : null,
            'spouse_name' => $type === SacramentType::Matrimony ? fake()->name() : null,
            'details' => match ($type) {
                SacramentType::Confirmation => ['confirmation_name' => fake()->randomElement(['Peter', 'Paul', 'Mary', 'Theresa', 'Joseph'])],
                SacramentType::HolyOrders => ['order' => HolyOrder::Priest->value],
                default => null,
            },
        ]);
    }

    /**
     * Record a sacrament celebrated in another parish.
     */
    public function elsewhere(string $place = 'St. Mary Catholic Church, Enugu'): static
    {
        return $this->state(fn (array $attributes) => [
            'elsewhere' => true,
            'place' => $place,
        ]);
    }
}
