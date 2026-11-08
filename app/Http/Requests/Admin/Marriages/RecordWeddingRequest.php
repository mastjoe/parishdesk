<?php

namespace App\Http\Requests\Admin\Marriages;

use App\Enums\SacramentType;
use App\Enums\SponsorRole;
use App\Models\MarriageApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordWeddingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('recordWedding', $this->application());
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $squish = fn (mixed $value): ?string => is_string($value) && Str::squish($value) !== '' ? Str::squish($value) : null;

        $this->merge([
            'minister' => $squish($this->input('minister')),
            'witnesses' => collect((array) $this->input('witnesses'))
                ->map(fn (mixed $witness): mixed => is_array($witness) ? [...$witness, 'name' => $squish($witness['name'] ?? null)] : $witness)
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
        $lastBanns = $this->application()->publications()->reorder('number', 'desc')->value('published_on');

        return [
            'received_on' => array_filter(['required', 'date', 'before_or_equal:today', $lastBanns !== null ? 'after_or_equal:'.$lastBanns : null]),
            'minister' => ['nullable', 'string', 'max:255'],
            'register_book' => ['nullable', 'string', 'max:20'],
            'register_page' => ['nullable', 'string', 'max:20'],
            'entry_no' => ['nullable', 'string', 'max:20', Rule::unique('sacrament_records', 'entry_no')
                ->where('type', SacramentType::Matrimony->value)
                ->where('register_book', $this->input('register_book'))
                ->withoutTrashed()],
            'notes' => ['nullable', 'string', 'max:5000'],
            'witnesses' => ['array', 'max:'.SacramentType::Matrimony->maxSponsors()],
            'witnesses.*.member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'witnesses.*.name' => ['required', 'string', 'max:255'],
        ];
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
                $couple = array_filter([$this->application()->groom_member_id, $this->application()->bride_member_id]);
                $witnesses = collect((array) $this->input('witnesses'))->pluck('member_id')->filter();

                if ($witnesses->contains(fn (mixed $id): bool => in_array((int) $id, $couple, true))) {
                    $validator->errors()->add('witnesses', __('The groom and the bride cannot be witnesses at their own wedding.'));
                }
            },
        ];
    }

    /**
     * Get the register entry fields, without the witnesses.
     *
     * @return array{received_on: string, minister: string|null, register_book: string|null, register_page: string|null, entry_no: string|null, notes: string|null}
     */
    public function entry(): array
    {
        return [
            'received_on' => $this->validated('received_on'),
            'minister' => $this->validated('minister'),
            'register_book' => $this->validated('register_book'),
            'register_page' => $this->validated('register_page'),
            'entry_no' => $this->validated('entry_no'),
            'notes' => $this->validated('notes'),
        ];
    }

    /**
     * Get the witnesses to write in the register entry.
     *
     * @return list<array{member_id: int|null, name: string, role: string}>
     */
    public function witnesses(): array
    {
        return array_values(array_map(fn (array $witness): array => [
            'member_id' => isset($witness['member_id']) ? (int) $witness['member_id'] : null,
            'name' => $witness['name'],
            'role' => SponsorRole::Witness->value,
        ], $this->validated('witnesses', [])));
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_no.unique' => __('This entry number is already used in that register book.'),
            'received_on.before_or_equal' => __('The date cannot be in the future.'),
            'received_on.after_or_equal' => __('The wedding cannot be before the last publication of the banns.'),
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
            'received_on' => 'wedding date',
            'witnesses.*.name' => 'witness\'s name',
            'witnesses.*.member_id' => 'witness',
        ];
    }

    /**
     * Get the application whose wedding is recorded.
     */
    private function application(): MarriageApplication
    {
        /** @var MarriageApplication */
        return $this->route('marriage');
    }
}
