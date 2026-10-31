<?php

namespace Database\Seeders;

use App\Actions\Members\SaveMember;
use App\Enums\ClergyRole;
use App\Enums\CommunityType;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Models\ClergyAssignment;
use App\Models\Community;
use App\Models\Family;
use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sample communities, families, members and clergy for trying out the
 * people pages locally. Run it after UserSeeder: it links the portal test
 * account (member@example.com) to a member record.
 *
 * Re-running it adds nothing new once the sample families exist.
 */
class PeopleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(SaveMember $saveMember): void
    {
        if (Family::query()->where('name', 'Obi Family')->exists()) {
            return;
        }

        $communities = collect([
            ['St. Jude SCC', CommunityType::SmallChristianCommunity],
            ['St. Theresa SCC', CommunityType::SmallChristianCommunity],
            ['Holy Family Station', CommunityType::Station],
            ['Our Lady of Fatima Outstation', CommunityType::Outstation],
        ])->map(fn (array $community) => Community::query()->create(['name' => $community[0], 'type' => $community[1]]));

        $families = [
            ['Obi', 0, [
                ['Emeka', Gender::Male, FamilyRole::Father, '-48 years', MaritalStatus::Married],
                ['Cecilia', Gender::Female, FamilyRole::Mother, '-44 years', MaritalStatus::Married],
                ['Chidi', Gender::Male, FamilyRole::Child, '-15 years', null],
                ['Adaeze', Gender::Female, FamilyRole::Child, '-9 years', null],
            ]],
            ['Okafor', 0, [
                ['Grace', Gender::Female, FamilyRole::Mother, '-67 years', MaritalStatus::Widowed],
                ['Ifeanyi', Gender::Male, FamilyRole::Child, '-34 years', MaritalStatus::Single],
            ]],
            ['Adeyemi', 1, [
                ['Tunde', Gender::Male, FamilyRole::Father, '-39 years', MaritalStatus::Married],
                ['Funmi', Gender::Female, FamilyRole::Mother, '-36 years', MaritalStatus::Married],
                ['Tobi', Gender::Male, FamilyRole::Child, '-6 years', null],
            ]],
            ['Musa', 2, [
                ['Daniel', Gender::Male, FamilyRole::Father, '-52 years', MaritalStatus::Married],
                ['Ruth', Gender::Female, FamilyRole::Mother, '-50 years', MaritalStatus::Married],
                ['Esther', Gender::Female, FamilyRole::Child, '-22 years', MaritalStatus::Single],
                ['Samuel', Gender::Male, FamilyRole::Dependent, '-17 years', null],
            ]],
            ['Nwosu', 3, [
                ['Patrick', Gender::Male, FamilyRole::Father, '-71 years', MaritalStatus::Married],
                ['Agnes', Gender::Female, FamilyRole::Mother, '-69 years', MaritalStatus::Married],
                ['Chinedu', Gender::Male, FamilyRole::Grandchild, '-12 years', null],
            ]],
        ];

        $index = 0;

        foreach ($families as [$surname, $communityIndex, $people]) {
            $community = $communities[$communityIndex];
            $family = Family::query()->create([
                'name' => "{$surname} Family",
                'community_id' => $community->id,
                'address' => fake()->streetAddress(),
                'home_phone' => fake()->numerify('080########'),
            ]);
            [$city, $state] = fake()->randomElement([['Ikeja', 'Lagos'], ['Enugu', 'Enugu'], ['Onitsha', 'Anambra'], ['Port Harcourt', 'Rivers']]);

            $weddingDate = now()->subYears(fake()->numberBetween(5, 40))->addDays(fake()->numberBetween(-20, 20));

            foreach ($people as [$firstName, $gender, $role, $age, $marital]) {
                // Spread a few birthdays over the coming weeks so the celebrations page has something to show.
                $birthday = now()->modify($age)->addDays($index * 3 - 6);
                $index++;

                // Minors have no phone, email or job of their own.
                $isChild = $birthday->age < 18;
                $isMarried = $marital === MaritalStatus::Married;

                $phone = $isChild ? null : fake()->numerify('080########');

                $saveMember->handle([
                    'title' => $isChild ? null : ($gender === Gender::Male ? 'Mr.' : 'Mrs.'),
                    'first_name' => $firstName,
                    'last_name' => $surname,
                    'gender' => $gender,
                    'date_of_birth' => $birthday,
                    'marital_status' => $marital,
                    'wedding_date' => $isMarried ? $weddingDate : null,
                    'phone' => $phone,
                    'whatsapp_phone' => $phone,
                    'email' => $isChild ? null : strtolower("{$firstName}.{$surname}@example.net"),
                    'communication_consent' => ! $isChild,
                    'address' => $family->address,
                    'city' => $city,
                    'state' => $state,
                    // Student, or one of: trader, secondary teacher, nurse, civil engineer, tailor, accountant.
                    'occupation' => $isChild ? 'student' : fake()->randomElement(['5211', '2330', '2221', '2142', '7531', '2411']),
                    'emergency_contact_name' => $isChild ? null : fake()->name(),
                    'emergency_contact_phone' => $isChild ? null : fake()->numerify('080########'),
                    'emergency_contact_relationship' => $isChild ? null : fake()->randomElement(['Sibling', 'Parent', 'Friend', 'Cousin']),
                    'community_id' => $community->id,
                    'family_id' => $family->id,
                    'family_role' => $role,
                    'status' => MemberStatus::Active,
                    'joined_at' => now()->subYears(fake()->numberBetween(1, 15)),
                ]);
            }
        }

        foreach (range(1, 12) as $ignored) {
            $saveMember->handle(Member::factory()->make([
                'community_id' => $communities->random()->id,
                'member_no' => null,
            ])->only(['first_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'city', 'state', 'status', 'joined_at', 'community_id']));
        }

        $communities[0]->update(['leader_member_id' => Member::query()->where('first_name', 'Emeka')->value('id')]);
        $communities[2]->update(['leader_member_id' => Member::query()->where('first_name', 'Daniel')->value('id')]);

        // The portal test account belongs to Cecilia Obi.
        $cecilia = Member::query()->where('first_name', 'Cecilia')->where('last_name', 'Obi')->firstOrFail();
        $account = User::query()->where('email', 'member@example.com')->first();

        if ($account !== null) {
            $cecilia->forceFill(['user_id' => $account->id, 'email' => $account->email])->save();

            ProfileChangeRequest::query()->create([
                'member_id' => $cecilia->id,
                'submitted_by' => $account->id,
                'changes' => ['phone' => '08031234567', 'occupation' => '2221'],
                'original' => ['phone' => $cecilia->phone, 'occupation' => $cecilia->occupation],
                'note' => 'I changed my number and my job last month.',
            ]);
        }

        ClergyAssignment::query()->create(['name' => 'Rev. Fr. Michael Eze', 'role' => ClergyRole::ParishPriest, 'starts_on' => now()->subYears(3)->startOfYear()]);
        ClergyAssignment::query()->create(['name' => 'Rev. Fr. John Bosco Ani', 'role' => ClergyRole::AssistantPriest, 'starts_on' => now()->subYear()->setMonth(9)->startOfMonth()]);
        ClergyAssignment::query()->create(['name' => 'Rev. Msgr. Anthony Okeke', 'role' => ClergyRole::ParishPriest, 'starts_on' => now()->subYears(11)->startOfYear(), 'ends_on' => now()->subYears(3)->startOfYear()->subDay()]);
        ClergyAssignment::query()->create(['name' => 'Rev. Fr. Paul Udeh', 'role' => ClergyRole::AssistantPriest, 'starts_on' => now()->subYears(7)->startOfYear(), 'ends_on' => now()->subYears(4)->endOfYear()]);
    }
}
