<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SacramentType;
use Database\Factories\SacramentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One entry in a sacramental register.
 *
 * The person's name is always stored as written in the register, so people
 * who are not members (or who left the parish) keep their entries. For
 * Matrimony, the record also names the spouse.
 *
 * @property int $id
 * @property SacramentType $type
 * @property int|null $member_id
 * @property string $person_name
 * @property Gender|null $gender
 * @property Carbon|null $date_of_birth
 * @property Carbon $received_on
 * @property bool $elsewhere Whether it was celebrated outside this parish
 * @property string|null $place
 * @property string|null $minister
 * @property string|null $father_name
 * @property string|null $mother_name
 * @property int|null $spouse_member_id
 * @property string|null $spouse_name
 * @property array<string, string|null>|null $details Type specific fields, such as the confirmation name
 * @property string|null $register_book
 * @property string|null $register_page
 * @property string|null $entry_no
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type', 'member_id', 'person_name', 'gender', 'date_of_birth', 'received_on', 'elsewhere', 'place', 'minister',
    'father_name', 'mother_name', 'spouse_member_id', 'spouse_name', 'details', 'register_book', 'register_page', 'entry_no', 'notes',
])]
class SacramentRecord extends Model
{
    /** @use HasFactory<SacramentRecordFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SacramentType::class,
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'received_on' => 'date',
            'elsewhere' => 'boolean',
            'details' => 'array',
        ];
    }

    /**
     * Get the member who received the sacrament, if they are in the register.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /**
     * Get the spouse in a Matrimony record, if they are in the register.
     *
     * @return BelongsTo<Member, $this>
     */
    public function spouse(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'spouse_member_id')->withTrashed();
    }

    /**
     * Get the godparents, sponsors or witnesses.
     *
     * @return HasMany<SacramentSponsor, $this>
     */
    public function sponsors(): HasMany
    {
        return $this->hasMany(SacramentSponsor::class)->orderBy('id');
    }

    /**
     * Get the user who wrote the entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope the query to the records of a member, including marriages in
     * which they are the spouse.
     *
     * @param  Builder<SacramentRecord>  $query
     */
    public function scopeForMember(Builder $query, Member $member): void
    {
        $query->where(fn (Builder $query) => $query->where('member_id', $member->id)->orWhere('spouse_member_id', $member->id));
    }

    /**
     * Get a type specific detail, such as the confirmation name.
     */
    public function detail(string $key): ?string
    {
        return $this->details[$key] ?? null;
    }

    /**
     * Get where the register can be found, e.g. "Book 3, page 12, entry 45".
     */
    public function registerReference(): ?string
    {
        $parts = array_filter([
            $this->register_book !== null ? "Book {$this->register_book}" : null,
            $this->register_page !== null ? "page {$this->register_page}" : null,
            $this->entry_no !== null ? "entry {$this->entry_no}" : null,
        ]);

        return $parts === [] ? null : ucfirst(implode(', ', $parts));
    }

    /**
     * Configure which attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('sacraments');
    }
}
