<?php

namespace Database\Seeders;

use App\Actions\Sacraments\SaveSacramentRecord;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\SacramentType;
use App\Enums\SponsorRole;
use App\Models\Family;
use App\Models\Member;
use App\Models\SacramentRecord;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sample register entries for the seeded families: baptisms, First Holy
 * Communions, confirmations and marriages, with a few left out so the
 * member profiles show gaps. Run it after PeopleSeeder.
 *
 * Re-running it adds nothing once the registers have entries.
 */
class SacramentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Parishes where people who joined as adults received their sacraments.
     */
    private const array OTHER_PARISHES = [
        'Holy Ghost Cathedral, Enugu',
        'St. Mary Catholic Church, Onitsha',
        'Sacred Heart Parish, Port Harcourt',
        'St. Leo Catholic Church, Ikeja',
    ];

    /**
     * The next entry number in each register.
     *
     * @var array<string, int>
     */
    private array $entries = [];

    /**
     * Run the database seeds.
     */
    public function run(SaveSacramentRecord $save): void
    {
        if (SacramentRecord::query()->exists()) {
            return;
        }

        $families = Family::query()->with('members')->whereIn('name', ['Obi Family', 'Okafor Family', 'Adeyemi Family', 'Musa Family', 'Nwosu Family'])->get();

        foreach ($families as $family) {
            $father = $family->members->firstWhere('family_role', FamilyRole::Father);
            $mother = $family->members->firstWhere('family_role', FamilyRole::Mother);

            foreach ($family->members as $member) {
                if ($member->date_of_birth === null) {
                    continue;
                }

                $isChild = in_array($member->family_role, [FamilyRole::Child, FamilyRole::Grandchild, FamilyRole::Dependent], true);
                $parents = $isChild && $member->family_role === FamilyRole::Child ? [$father?->full_name, $mother?->full_name] : [null, null];

                $this->record($save, SacramentType::Baptism, $member, $member->date_of_birth->copy()->addWeeks(8), $parents);
                $this->record($save, SacramentType::FirstHolyCommunion, $member, $member->date_of_birth->copy()->addYears(9)->setMonth(5)->setDay(15), $parents);

                // Ifeanyi Okafor was never confirmed, so his profile shows the gap.
                if ($member->first_name !== 'Ifeanyi') {
                    $this->record($save, SacramentType::Confirmation, $member, $member->date_of_birth->copy()->addYears(14)->setMonth(8)->setDay(20), $parents);
                }
            }

            if ($father !== null && $mother !== null && $father->wedding_date !== null) {
                $this->marriage($save, $father, $mother, $father->wedding_date);
            }
        }

        // People baptised in the parish who are not in the member register.
        foreach (range(1, 6) as $weeksAgo) {
            $gender = fake()->randomElement(Gender::cases());
            $surname = fake()->lastName();
            $born = now()->subWeeks($weeksAgo * 3 + 8);

            $save->handle([
                'type' => SacramentType::Baptism,
                'person_name' => fake()->firstName($gender->value).' '.$surname,
                'gender' => $gender,
                'date_of_birth' => $born,
                'received_on' => now()->subWeeks($weeksAgo * 3)->previous(CarbonInterface::SUNDAY),
                'minister' => $this->minister(now()),
                'father_name' => fake()->firstName('male').' '.$surname,
                'mother_name' => fake()->firstName('female').' '.$surname,
                ...$this->reference(SacramentType::Baptism),
            ], $this->godparents());
        }
    }

    /**
     * Record a sacrament for a member if they are old enough to have received it.
     *
     * @param  array{0: string|null, 1: string|null}  $parents
     */
    private function record(SaveSacramentRecord $save, SacramentType $type, Member $member, CarbonInterface $on, array $parents): void
    {
        if ($on->isFuture()) {
            return;
        }

        // Sacraments from before someone joined the parish were celebrated elsewhere.
        $elsewhere = $member->joined_at !== null && $on->lt($member->joined_at);

        $save->handle([
            'type' => $type,
            'member_id' => $member->id,
            'person_name' => $member->full_name,
            'gender' => $member->gender,
            'date_of_birth' => $member->date_of_birth,
            'received_on' => $on,
            'elsewhere' => $elsewhere,
            'place' => $elsewhere ? fake()->randomElement(self::OTHER_PARISHES) : null,
            'minister' => $type === SacramentType::Confirmation ? 'Most Rev. Callistus Onaga' : $this->minister($on),
            'father_name' => $parents[0],
            'mother_name' => $parents[1],
            'details' => $type === SacramentType::Confirmation ? ['confirmation_name' => $member->gender === Gender::Male ? 'Joseph' : 'Mary'] : null,
            ...($elsewhere ? [] : $this->reference($type)),
        ], match ($type) {
            SacramentType::Baptism => $this->godparents(),
            SacramentType::Confirmation => [['member_id' => null, 'name' => fake()->name($member->gender->value), 'role' => SponsorRole::Sponsor->value]],
            default => [],
        });
    }

    /**
     * Record the marriage of a family's father and mother.
     */
    private function marriage(SaveSacramentRecord $save, Member $husband, Member $wife, CarbonInterface $on): void
    {
        $elsewhere = $husband->joined_at !== null && $on->lt($husband->joined_at);

        $save->handle([
            'type' => SacramentType::Matrimony,
            'member_id' => $husband->id,
            'person_name' => $husband->full_name,
            'gender' => $husband->gender,
            'date_of_birth' => $husband->date_of_birth,
            'received_on' => $on,
            'elsewhere' => $elsewhere,
            'place' => $elsewhere ? fake()->randomElement(self::OTHER_PARISHES) : null,
            'minister' => $this->minister($on),
            'spouse_member_id' => $wife->id,
            'spouse_name' => $wife->full_name,
            ...($elsewhere ? [] : $this->reference(SacramentType::Matrimony)),
        ], [
            ['member_id' => null, 'name' => fake()->name('male'), 'role' => SponsorRole::Witness->value],
            ['member_id' => null, 'name' => fake()->name('female'), 'role' => SponsorRole::Witness->value],
        ]);
    }

    /**
     * Get a godfather and a godmother.
     *
     * @return list<array{member_id: null, name: string, role: string}>
     */
    private function godparents(): array
    {
        return [
            ['member_id' => null, 'name' => fake()->name('male'), 'role' => SponsorRole::Godfather->value],
            ['member_id' => null, 'name' => fake()->name('female'), 'role' => SponsorRole::Godmother->value],
        ];
    }

    /**
     * Get the priest who most likely celebrated a sacrament on a date,
     * following the seeded clergy history.
     */
    private function minister(CarbonInterface $on): string
    {
        return match (true) {
            $on->gte(now()->subYears(3)->startOfYear()) => fake()->randomElement(['Rev. Fr. Michael Eze', 'Rev. Fr. John Bosco Ani']),
            $on->gte(now()->subYears(11)->startOfYear()) => fake()->randomElement(['Rev. Msgr. Anthony Okeke', 'Rev. Fr. Paul Udeh']),
            default => 'Rev. Fr. Cyprian Nnaji',
        };
    }

    /**
     * Get the next book, page and entry number in a register, with
     * twenty entries to a page and two hundred pages to a book.
     *
     * @return array{register_book: string, register_page: string, entry_no: string}
     */
    private function reference(SacramentType $type): array
    {
        $entry = $this->entries[$type->value] = ($this->entries[$type->value] ?? 0) + 1;

        return [
            'register_book' => (string) (intdiv($entry - 1, 4000) + 1),
            'register_page' => (string) (intdiv($entry - 1, 20) + 1),
            'entry_no' => (string) $entry,
        ];
    }
}
