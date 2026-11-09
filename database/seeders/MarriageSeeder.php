<?php

namespace Database\Seeders;

use App\Actions\Marriages\RecordWedding;
use App\Actions\Marriages\SaveMarriageApplication;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MarriageCheck;
use App\Enums\MarriageStatus;
use App\Enums\SponsorRole;
use App\Models\MarriageApplication;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sample marriage applications at every stage, from a first interview to a
 * wedding written in the marriage register. Single adult members are used
 * where the register has them. Run it after SacramentSeeder.
 *
 * Re-running it adds nothing once there are applications.
 */
class MarriageSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(SaveMarriageApplication $save, RecordWedding $wedding): void
    {
        if (MarriageApplication::query()->exists()) {
            return;
        }

        $singles = fn (Gender $gender) => Member::query()
            ->where('gender', $gender)
            ->where('marital_status', MaritalStatus::Single)
            ->whereDate('date_of_birth', '<=', now()->subYears(21))
            ->get();
        $men = $singles(Gender::Male);
        $women = $singles(Gender::Female);

        $apply = function (?Member $groom, ?Member $bride, CarbonInterface $proposed) use ($save): MarriageApplication {
            return $save->handle([
                'groom_member_id' => $groom?->id,
                'groom_name' => $groom->full_name ?? $this->person('male'),
                'groom_parish' => $groom === null ? fake()->randomElement(['Holy Ghost Cathedral, Enugu', 'St. Leo Catholic Church, Ikeja']) : null,
                'bride_member_id' => $bride?->id,
                'bride_name' => $bride->full_name ?? $this->person('female'),
                'bride_parish' => $bride === null ? 'Sacred Heart Parish, Port Harcourt' : null,
                ...$this->parents('groom', $groom),
                ...$this->parents('bride', $bride),
                'proposed_date' => $proposed->next(CarbonInterface::SATURDAY),
                'officiant' => fake()->randomElement(['Rev. Fr. Michael Eze', 'Rev. Fr. John Bosco Ani']),
            ]);
        };

        // A couple just interviewed, with some documents still to come.
        $this->advance($apply($men->shift(), null, now()->addMonths(4)), checks: [MarriageCheck::GroomBaptismCertificate, MarriageCheck::MarriageCourse]);

        // Documents in order, waiting for the first banns.
        $this->advance($apply(null, $women->shift(), now()->addMonths(2)), MarriageStatus::DocumentsVerified);

        // Two of the three banns published.
        $this->advance($apply($men->shift(), $women->shift(), now()->addWeeks(5)), MarriageStatus::Banns, banns: 2);

        // An objection after the first banns holds up the marriage.
        $held = $this->advance($apply(null, null, now()->addWeeks(7)), MarriageStatus::Banns, banns: 1);
        $held->objections()->create([
            'raised_by' => $this->person('female'),
            'raised_on' => now()->subDays(4),
            'details' => 'Says the groom was married before in a customary marriage that was later blessed in church.',
        ]);

        // Cleared and waiting for the wedding day.
        $this->advance($apply($men->shift(), null, now()->addWeeks(2)), MarriageStatus::Cleared, banns: 3);

        // Married last month, so the wedding is in the marriage register.
        $married = $this->advance($apply($men->shift(), $women->shift(), now()->subMonth()), MarriageStatus::Cleared, banns: 3, weeksAgo: 8);
        $wedding->handle($married, [
            'received_on' => now()->subMonth()->previous(CarbonInterface::SATURDAY)->toDateString(),
            'minister' => $married->officiant,
            'register_book' => '2',
            'register_page' => '41',
            'entry_no' => '815',
        ], [
            ['member_id' => null, 'name' => $this->person('male'), 'role' => SponsorRole::Witness->value],
            ['member_id' => null, 'name' => $this->person('female'), 'role' => SponsorRole::Witness->value],
        ]);

        // A couple who chose to marry in the bride's home parish instead.
        $withdrawn = $this->advance($apply(null, null, now()->addMonths(3)), MarriageStatus::DocumentsVerified);
        $withdrawn->forceFill([
            'status' => MarriageStatus::Withdrawn,
            'withdrawn_at' => now()->subWeek(),
            'withdrawal_reason' => 'The couple will marry in the bride\'s home parish. A letter of freedom was sent.',
        ])->save();
    }

    /**
     * Get a plain first and last name, without the titles and suffixes the
     * fake name generator sometimes adds.
     */
    private function person(string $gender): string
    {
        return fake()->firstName($gender).' '.fake()->lastName();
    }

    /**
     * Get a partner's parents: from their family when they are in the
     * register, otherwise made-up names.
     *
     * @return array<string, int|string|null>
     */
    private function parents(string $side, ?Member $partner): array
    {
        $family = $partner?->familyParents();

        return [
            "{$side}_father_member_id" => $family['father']->id ?? null,
            "{$side}_father_name" => $family['father']->full_name ?? ($partner === null ? $this->person('male') : null),
            "{$side}_mother_member_id" => $family['mother']->id ?? null,
            "{$side}_mother_name" => $family['mother']->full_name ?? ($partner === null ? $this->person('female') : null),
        ];
    }

    /**
     * Move an application to a stage, ticking its checks and publishing its
     * banns a week apart, the last one the given number of weeks ago.
     *
     * @param  list<MarriageCheck>|null  $checks
     */
    private function advance(MarriageApplication $application, MarriageStatus $status = MarriageStatus::Applied, int $banns = 0, int $weeksAgo = 1, ?array $checks = null): MarriageApplication
    {
        $application->forceFill([
            'status' => $status,
            'checks' => array_map(fn (MarriageCheck $check): string => $check->value, $checks ?? MarriageCheck::cases()),
            'documents_verified_at' => $status === MarriageStatus::Applied ? null : now()->subWeeks($weeksAgo + $banns + 1),
            'cleared_at' => $status === MarriageStatus::Cleared ? now()->subWeeks($weeksAgo)->addDay() : null,
        ])->save();

        foreach (range(1, $banns) as $number) {
            if ($banns === 0) {
                break;
            }

            $application->publications()->create([
                'number' => $number,
                'published_on' => now()->subWeeks($weeksAgo + $banns - $number)->previous(CarbonInterface::SUNDAY),
            ]);
        }

        return $application;
    }
}
