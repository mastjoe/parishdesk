<?php

namespace App\Http\Requests\Admin\Sacraments;

use App\Enums\Gender;
use App\Enums\HolyOrder;
use App\Enums\SacramentType;
use App\Enums\SponsorRole;
use App\Models\SacramentRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSacramentRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', SacramentRecord::class);
    }

    /**
     * Prepare the data for validation.
     *
     * The type of an existing entry never changes, and only entries made
     * elsewhere name the place they were celebrated.
     */
    protected function prepareForValidation(): void
    {
        $squish = fn (mixed $value): ?string => is_string($value) && Str::squish($value) !== '' ? Str::squish($value) : null;

        $this->merge([
            'type' => $this->record()->type->value ?? $this->input('type'),
            'person_name' => $squish($this->input('person_name')),
            'spouse_name' => $squish($this->input('spouse_name')),
            'place' => $this->boolean('elsewhere') ? $squish($this->input('place')) : null,
            'sponsors' => collect((array) $this->input('sponsors'))
                ->map(fn (mixed $sponsor): mixed => is_array($sponsor) ? [...$sponsor, 'name' => $squish($sponsor['name'] ?? null)] : $sponsor)
                ->all(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->type();
        $record = $this->record();

        $rules = [
            'type' => ['required', Rule::enum(SacramentType::class)],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'person_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:received_on'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'elsewhere' => ['boolean'],
            'place' => ['nullable', 'required_if_accepted:elsewhere', 'string', 'max:255'],
            'minister' => ['nullable', 'string', 'max:255'],
            'register_book' => ['nullable', 'string', 'max:20'],
            'register_page' => ['nullable', 'string', 'max:20'],
            'entry_no' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sponsors' => ['array', 'max:'.($type?->maxSponsors() ?? 0)],
            'sponsors.*.member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'sponsors.*.name' => ['required', 'string', 'max:255'],
            'sponsors.*.role' => ['required', Rule::in(array_map(fn (SponsorRole $role): string => $role->value, $type?->sponsorRoles() ?? []))],
        ];

        if ($type === null) {
            return $rules;
        }

        // A member can only be baptised, make their First Holy Communion and be confirmed once.
        if ($type->isOnce()) {
            $rules['member_id'][] = Rule::unique('sacrament_records', 'member_id')
                ->where('type', $type->value)
                ->withoutTrashed()
                ->ignore($record?->id);
        }

        // The same entry number cannot appear twice in one register book.
        $rules['entry_no'][] = Rule::unique('sacrament_records', 'entry_no')
            ->where('type', $type->value)
            ->where('register_book', $this->input('register_book'))
            ->withoutTrashed()
            ->ignore($record?->id);

        if ($type->recordsParents()) {
            $rules['father_name'] = ['nullable', 'string', 'max:255'];
            $rules['mother_name'] = ['nullable', 'string', 'max:255'];
        }

        if ($type === SacramentType::Matrimony) {
            $rules['spouse_member_id'] = ['nullable', 'integer', 'different:member_id', Rule::exists('members', 'id')->withoutTrashed()];
            $rules['spouse_name'] = ['required', 'string', 'max:255'];
        }

        if ($type === SacramentType::Confirmation) {
            $rules['details.confirmation_name'] = ['nullable', 'string', 'max:100'];
        }

        if ($type === SacramentType::HolyOrders) {
            $rules['details.order'] = ['required', Rule::enum(HolyOrder::class)];
        }

        return $rules;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sponsors = collect((array) $this->input('sponsors'))->pluck('member_id')->filter();

                if ($sponsors->contains(fn (mixed $id): bool => (int) $id === $this->integer('member_id'))) {
                    $validator->errors()->add('sponsors', __('The person receiving the sacrament cannot also be their own sponsor.'));
                }
            },
        ];
    }

    /**
     * Get the validated register entry, without its sponsors.
     *
     * Fields the sacrament does not use are cleared, so changing what an
     * entry records never leaves stale values behind.
     *
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        $type = SacramentType::from($this->validated('type'));
        $data = $this->safe()->except(['sponsors', 'details']);

        return [
            ...$data,
            'father_name' => $type->recordsParents() ? ($data['father_name'] ?? null) : null,
            'mother_name' => $type->recordsParents() ? ($data['mother_name'] ?? null) : null,
            'spouse_member_id' => $type === SacramentType::Matrimony ? ($data['spouse_member_id'] ?? null) : null,
            'spouse_name' => $type === SacramentType::Matrimony ? ($data['spouse_name'] ?? null) : null,
            'details' => array_filter((array) $this->validated('details', [])) ?: null,
        ];
    }

    /**
     * Get the godparents, sponsors or witnesses to write in the entry.
     *
     * @return list<array{member_id: int|null, name: string, role: string}>
     */
    public function sponsors(): array
    {
        return array_values(array_map(fn (array $sponsor): array => [
            'member_id' => isset($sponsor['member_id']) ? (int) $sponsor['member_id'] : null,
            'name' => $sponsor['name'],
            'role' => $sponsor['role'],
        ], $this->validated('sponsors', [])));
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_id.unique' => __('This member already has a :type record.', ['type' => Str::lower($this->type()?->label() ?? '')]),
            'entry_no.unique' => __('This entry number is already used in that register book.'),
            'spouse_member_id.different' => __('A person cannot be recorded as their own spouse.'),
            'place.required_if_accepted' => __('Say where the sacrament was celebrated.'),
            'received_on.before_or_equal' => __('The date cannot be in the future.'),
            'date_of_birth.before_or_equal' => __('The date of birth must be on or before the date of the sacrament.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'member_id' => 'member',
            'person_name' => 'name',
            'received_on' => 'date',
            'spouse_member_id' => 'spouse',
            'spouse_name' => 'spouse\'s name',
            'details.confirmation_name' => 'confirmation name',
            'details.order' => 'order',
            'sponsors.*.name' => 'sponsor\'s name',
            'sponsors.*.role' => 'sponsor\'s role',
            'sponsors.*.member_id' => 'sponsor',
        ];
    }

    /**
     * Get the entry being corrected, if any.
     */
    private function record(): ?SacramentRecord
    {
        $record = $this->route('sacrament');

        return $record instanceof SacramentRecord ? $record : null;
    }

    /**
     * Get the sacrament being recorded, if the request names a valid one.
     */
    private function type(): ?SacramentType
    {
        return SacramentType::tryFrom((string) $this->input('type'));
    }
}
