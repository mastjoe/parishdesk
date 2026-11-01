<?php

namespace App\Actions\Members;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Models\Community;
use App\Models\Member;
use App\Support\Import\CsvFile;
use App\Support\People\Occupations;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Adds members from a CSV file using a column mapping chosen by the user.
 *
 * Rows that fail validation, or that look like someone already in the
 * register, are skipped and reported back with the reason.
 */
class ImportMembers
{
    /**
     * The member fields a CSV column can be mapped to, with their labels.
     */
    public const array FIELDS = [
        'member_no' => 'Member number',
        'title' => 'Title',
        'first_name' => 'First name',
        'middle_name' => 'Middle name',
        'last_name' => 'Last name',
        'baptismal_name' => 'Baptismal name',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of birth',
        'marital_status' => 'Marital status',
        'wedding_date' => 'Wedding date',
        'phone' => 'Phone',
        'alt_phone' => 'Other phone',
        'whatsapp_phone' => 'WhatsApp number',
        'email' => 'Email',
        'address' => 'Address',
        'city' => 'City',
        'state' => 'State',
        'occupation' => 'Occupation',
        'emergency_contact_name' => 'Emergency contact name',
        'emergency_contact_phone' => 'Emergency contact phone',
        'emergency_contact_relationship' => 'Emergency contact relationship',
        'state_of_origin' => 'State of origin',
        'lga_of_origin' => 'LGA of origin',
        'community' => 'Community',
        'status' => 'Status',
        'joined_at' => 'Date joined',
    ];

    /**
     * The fields every import must map.
     */
    public const array REQUIRED = ['first_name', 'last_name', 'gender'];

    /**
     * The largest number of rows imported from one file.
     */
    public const int MAX_ROWS = 5000;

    /**
     * Community ids keyed by lower-cased community name.
     *
     * @var array<string, int>
     */
    private array $communities = [];

    public function __construct(
        private SaveMember $saveMember,
    ) {}

    /**
     * Import the file.
     *
     * @param  array<string, string>  $mapping  member field => CSV header
     * @return array{created: int, skipped: list<array{line: int, name: string, reason: string}>}
     */
    public function handle(string $path, array $mapping): array
    {
        $csv = new CsvFile($path);
        $columns = array_filter(array_map(
            fn (string $header): int|false => array_search($header, $csv->headers(), true),
            array_intersect_key($mapping, self::FIELDS),
        ), fn (int|false $index): bool => $index !== false);

        $this->communities = Community::query()->pluck('id', 'name')->mapWithKeys(fn (int $id, string $name) => [Str::lower($name) => $id])->all();

        $created = 0;
        $skipped = [];

        foreach ($csv->rows() as $line => $row) {
            if ($created + count($skipped) >= self::MAX_ROWS) {
                break;
            }

            $values = array_map(fn (int $index): string => $row[$index] ?? '', $columns);
            $name = trim(($values['first_name'] ?? '').' '.($values['last_name'] ?? ''));
            $reason = $this->import($values);

            if ($reason === null) {
                $created++;
            } else {
                $skipped[] = ['line' => $line, 'name' => $name !== '' ? $name : __('(no name)'), 'reason' => $reason];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Import one row, returning why it was skipped, if it was.
     *
     * @param  array<string, string>  $values
     */
    private function import(array $values): ?string
    {
        $data = $this->normalise($values);

        $validator = Validator::make($data, [
            'member_no' => ['nullable', 'string', 'max:30', Rule::unique('members', 'member_no')],
            'title' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'baptismal_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'wedding_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', Rule::in(Occupations::codes())],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'state_of_origin' => ['nullable', 'string', 'max:100'],
            'lga_of_origin' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'joined_at' => ['nullable', 'date'],
        ], [
            'gender.required' => __('Gender is missing.'),
            'gender.enum' => __('Gender must be male or female.'),
            '*.date' => __('The :attribute is not a date we understand.'),
        ]);

        if ($validator->fails()) {
            return (string) $validator->errors()->first();
        }

        if (($existing = $this->findExisting($data)) !== null) {
            return __('Already in the register as :number.', ['number' => $existing->member_no]);
        }

        try {
            DB::transaction(fn () => $this->saveMember->handle($data));
        } catch (Throwable $exception) {
            report($exception);

            return __('Could not be saved.');
        }

        return null;
    }

    /**
     * Turn the raw cells into member attributes.
     *
     * @param  array<string, string>  $values
     * @return array<string, string|int|null>
     */
    private function normalise(array $values): array
    {
        $data = array_map(fn (string $value): ?string => $value === '' ? null : Str::squish($value), $values);

        foreach (['date_of_birth', 'wedding_date', 'joined_at'] as $field) {
            $data[$field] = $this->date($data[$field] ?? null);
        }

        $data['gender'] = $this->choice($data['gender'] ?? null, Gender::class, ['m' => 'male', 'man' => 'male', 'f' => 'female', 'woman' => 'female']);
        $data['marital_status'] = $this->choice($data['marital_status'] ?? null, MaritalStatus::class);
        $data['status'] = $this->choice($data['status'] ?? null, MemberStatus::class) ?? MemberStatus::Active->value;
        $data['email'] = isset($data['email']) ? Str::lower($data['email']) : null;

        // Job titles are matched to the closest occupation on the list.
        if (isset($data['occupation'])) {
            $data['occupation'] = Occupations::guess($data['occupation']);
        }

        $community = isset($data['community']) ? Str::lower($data['community']) : null;
        $data['community_id'] = $community !== null ? ($this->communities[$community] ?? null) : null;
        unset($data['community']);

        return $data;
    }

    /**
     * Read a date written as 2026-10-30, 30/10/2026, 30-10-2026 or 30.10.2026.
     *
     * Day-first is assumed when the order is ambiguous.
     */
    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            // Formatting back rules out overflowing dates such as 31/02/2001.
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    /**
     * Match a cell to an enum by value, label, or one of the given aliases.
     *
     * Unknown values are passed through so validation can report them.
     *
     * @param  class-string<Gender|MaritalStatus|MemberStatus>  $enum
     * @param  array<string, string>  $aliases
     */
    private function choice(?string $value, string $enum, array $aliases = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $needle = Str::lower($value);

        foreach ($enum::cases() as $case) {
            if (in_array($needle, [Str::lower((string) $case->value), Str::lower($case->label()), str_replace('_', ' ', (string) $case->value)], true)) {
                return (string) $case->value;
            }
        }

        return $aliases[$needle] ?? $value;
    }

    /**
     * Find a member who appears to be the same person as the row.
     *
     * @param  array<string, string|int|null>  $data
     */
    private function findExisting(array $data): ?Member
    {
        if (isset($data['email'])) {
            $member = Member::query()->where('email', $data['email'])->first();

            if ($member !== null) {
                return $member;
            }
        }

        if (! isset($data['date_of_birth'])) {
            return null;
        }

        return Member::query()
            ->where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->whereDate('date_of_birth', (string) $data['date_of_birth'])
            ->first();
    }
}
