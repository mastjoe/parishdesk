<?php

namespace App\Models;

use App\Enums\MarriageCheck;
use App\Enums\MarriageStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MarriageApplicationFactory;
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
 * A couple's application to marry in the parish.
 *
 * It moves from Applied to Documents verified once every check is done,
 * then through the three publications of the banns to Cleared, and ends
 * when the wedding is written in the marriage register or the couple
 * withdraws. An objection that is not yet resolved holds up the banns.
 *
 * @property int $id
 * @property string $application_no
 * @property int|null $groom_member_id
 * @property string $groom_name
 * @property int|null $groom_father_member_id
 * @property string|null $groom_father_name
 * @property int|null $groom_mother_member_id
 * @property string|null $groom_mother_name
 * @property string|null $groom_parish The groom's parish when it is not this one
 * @property int|null $bride_member_id
 * @property string $bride_name
 * @property int|null $bride_father_member_id
 * @property string|null $bride_father_name
 * @property int|null $bride_mother_member_id
 * @property string|null $bride_mother_name
 * @property string|null $bride_parish The bride's parish when it is not this one
 * @property MarriageStatus $status
 * @property Carbon|null $proposed_date
 * @property string|null $wedding_parish The parish where the wedding will be held when it is not this one
 * @property string|null $officiant
 * @property list<string>|null $checks The MarriageCheck values that are done
 * @property CarbonImmutable|null $documents_verified_at
 * @property CarbonImmutable|null $cleared_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property string|null $withdrawal_reason
 * @property int|null $matrimony_record_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'groom_member_id', 'groom_name', 'groom_parish', 'bride_member_id', 'bride_name', 'bride_parish',
    'groom_father_member_id', 'groom_father_name', 'groom_mother_member_id', 'groom_mother_name',
    'bride_father_member_id', 'bride_father_name', 'bride_mother_member_id', 'bride_mother_name',
    'proposed_date', 'wedding_parish', 'officiant', 'notes',
])]
class MarriageApplication extends Model
{
    /** @use HasFactory<MarriageApplicationFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The numbering sequence that hands out application numbers.
     */
    public const string NUMBER_SEQUENCE = 'marriage';

    /**
     * The default application number format, e.g. "MAR/2026/007".
     */
    public const string NUMBER_FORMAT = 'MAR/{Y}/{seq:3}';

    /**
     * How many times the banns are published before the couple is cleared.
     */
    public const int BANNS_REQUIRED = 3;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'applied',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MarriageStatus::class,
            'proposed_date' => 'date',
            'checks' => 'array',
            'documents_verified_at' => 'datetime',
            'cleared_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * Get the groom, if he is in the member register.
     *
     * @return BelongsTo<Member, $this>
     */
    public function groom(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'groom_member_id')->withTrashed();
    }

    /**
     * Get the bride, if she is in the member register.
     *
     * @return BelongsTo<Member, $this>
     */
    public function bride(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'bride_member_id')->withTrashed();
    }

    /**
     * Get the publications of the banns, first to last.
     *
     * @return HasMany<BannsPublication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(BannsPublication::class)->orderBy('number');
    }

    /**
     * Get the objections raised against the marriage, oldest first.
     *
     * @return HasMany<BannsObjection, $this>
     */
    public function objections(): HasMany
    {
        return $this->hasMany(BannsObjection::class)->orderBy('raised_on')->orderBy('id');
    }

    /**
     * Get the entry in the marriage register, once the couple is married.
     *
     * @return BelongsTo<SacramentRecord, $this>
     */
    public function matrimonyRecord(): BelongsTo
    {
        return $this->belongsTo(SacramentRecord::class, 'matrimony_record_id');
    }

    /**
     * Get the user who opened the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope the query to applications still on their way to a wedding.
     *
     * @param  Builder<MarriageApplication>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', MarriageStatus::open());
    }

    /**
     * Scope the query to applications in which the member is the groom or the bride.
     *
     * @param  Builder<MarriageApplication>  $query
     */
    public function scopeInvolving(Builder $query, int $memberId): void
    {
        $query->where(fn (Builder $query) => $query->where('groom_member_id', $memberId)->orWhere('bride_member_id', $memberId));
    }

    /**
     * Get the couple's names, e.g. "John Obi & Ada Eze".
     */
    public function coupleName(): string
    {
        return "{$this->groom_name} & {$this->bride_name}";
    }

    /**
     * Whether the application is still on its way to a wedding.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Get the checks that are not done yet.
     *
     * @return list<MarriageCheck>
     */
    public function missingChecks(): array
    {
        $done = $this->checks ?? [];

        return array_values(array_filter(MarriageCheck::cases(), fn (MarriageCheck $check): bool => ! in_array($check->value, $done, true)));
    }

    /**
     * Get how many times the banns have been published.
     */
    public function bannsPublished(): int
    {
        return $this->relationLoaded('publications') ? $this->publications->count() : $this->publications()->count();
    }

    /**
     * Whether an objection is still waiting to be resolved.
     */
    public function hasOpenObjection(): bool
    {
        return $this->relationLoaded('objections')
            ? $this->objections->contains(fn (BannsObjection $objection): bool => $objection->isOpen())
            : $this->objections()->whereNull('resolved_at')->exists();
    }

    /**
     * Whether the checklist can still be changed. It is fixed once the
     * documents are verified.
     */
    public function canChangeChecks(): bool
    {
        return $this->status === MarriageStatus::Applied;
    }

    /**
     * Whether every check is done, so the documents can be verified.
     */
    public function canVerifyDocuments(): bool
    {
        return $this->status === MarriageStatus::Applied && $this->missingChecks() === [];
    }

    /**
     * Whether the next publication of the banns can be recorded.
     */
    public function canPublishBanns(): bool
    {
        return in_array($this->status, [MarriageStatus::DocumentsVerified, MarriageStatus::Banns], true)
            && $this->bannsPublished() < self::BANNS_REQUIRED
            && ! $this->hasOpenObjection();
    }

    /**
     * Whether the latest publication can be taken back, for when it was
     * recorded by mistake.
     */
    public function canUndoBanns(): bool
    {
        return $this->status === MarriageStatus::Banns && $this->bannsPublished() > 0;
    }

    /**
     * Whether an objection can be raised. Objections are taken until the
     * wedding.
     */
    public function canReceiveObjections(): bool
    {
        return in_array($this->status, [MarriageStatus::DocumentsVerified, MarriageStatus::Banns, MarriageStatus::Cleared], true);
    }

    /**
     * Whether the banns are complete and unopposed, so the couple can be cleared.
     */
    public function canBeCleared(): bool
    {
        return $this->status === MarriageStatus::Banns
            && $this->bannsPublished() >= self::BANNS_REQUIRED
            && ! $this->hasOpenObjection();
    }

    /**
     * Whether the wedding can be written in the marriage register.
     */
    public function canBeMarried(): bool
    {
        return $this->status === MarriageStatus::Cleared && ! $this->hasOpenObjection();
    }

    /**
     * Configure which attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([...$this->getFillable(), 'status', 'checks', 'withdrawal_reason', 'matrimony_record_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('marriages');
    }
}
