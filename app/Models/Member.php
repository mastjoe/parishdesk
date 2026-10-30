<?php

namespace App\Models;

use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use Carbon\CarbonInterface;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A parishioner in the parish register.
 *
 * @property int $id
 * @property string $member_no
 * @property string|null $title
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $baptismal_name
 * @property Gender $gender
 * @property Carbon|null $date_of_birth
 * @property MaritalStatus|null $marital_status
 * @property Carbon|null $wedding_date
 * @property string|null $phone
 * @property string|null $alt_phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $occupation
 * @property string|null $state_of_origin
 * @property string|null $lga_of_origin
 * @property int|null $community_id
 * @property int|null $family_id
 * @property FamilyRole|null $family_role
 * @property MemberStatus $status
 * @property Carbon|null $joined_at
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 */
#[Fillable([
    'member_no', 'title', 'first_name', 'middle_name', 'last_name', 'baptismal_name', 'gender', 'date_of_birth',
    'marital_status', 'wedding_date', 'phone', 'alt_phone', 'email', 'address', 'occupation', 'state_of_origin',
    'lga_of_origin', 'community_id', 'family_id', 'family_role', 'status', 'joined_at', 'notes',
])]
class Member extends Model implements HasMedia
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /**
     * The numbering sequence that hands out member numbers.
     */
    public const string NUMBER_SEQUENCE = 'member';

    /**
     * The default member number format, e.g. "MEM-2026-0001".
     */
    public const string NUMBER_FORMAT = 'MEM-{Y}-{seq:4}';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'family_role' => FamilyRole::class,
            'status' => MemberStatus::class,
            'date_of_birth' => 'date',
            'wedding_date' => 'date',
            'joined_at' => 'date',
        ];
    }

    /**
     * Get the member's name without their title, e.g. "Grace Ada Okafor".
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    /**
     * Get the community the member lives in.
     *
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the family the member belongs to.
     *
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Get the portal account linked to the member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the profile changes the member has asked for.
     *
     * @return HasMany<ProfileChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ProfileChangeRequest::class);
    }

    /**
     * Get the latest portal invitation sent to the member.
     *
     * @return HasOne<UserInvitation, $this>
     */
    public function latestInvitation(): HasOne
    {
        return $this->hasOne(UserInvitation::class)->latestOfMany();
    }

    /**
     * Register the member's media: one photo at a time.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Register the square thumbnail shown in avatars across the app.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photo')
            ->nonQueued()
            ->fit(Fit::Crop, 256, 256);
    }

    /**
     * Get the URL of the member's photo thumbnail.
     */
    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo', 'thumb') ?: null;
    }

    /**
     * Scope the query to members whose birthday (or another yearly date)
     * falls within the given number of days from a starting date.
     *
     * February 29 dates are celebrated on February 28 in non-leap years.
     *
     * @param  Builder<Member>  $query
     */
    public function scopeCelebrating(Builder $query, string $column, CarbonInterface $from, int $days): void
    {
        $query->whereNotNull($column)->where(function (Builder $query) use ($column, $from, $days): void {
            foreach (self::celebrationDays($from, $days) as [$month, $day]) {
                $query->orWhere(fn (Builder $query) => $query->whereMonth($column, $month)->whereDay($column, $day));
            }
        });
    }

    /**
     * Get the next date on which a yearly date is celebrated, on or after a day.
     */
    public static function nextOccurrence(CarbonInterface $date, CarbonInterface $from): CarbonInterface
    {
        $from = $from->toImmutable()->startOfDay();
        $candidate = self::inYear($date, $from->year);

        return $candidate->lt($from) ? self::inYear($date, $from->year + 1) : $candidate;
    }

    /**
     * Get the month and day pairs covered by a range of days.
     *
     * @return list<array{0: int, 1: int}>
     */
    private static function celebrationDays(CarbonInterface $from, int $days): array
    {
        $pairs = [];
        $from = $from->toImmutable();

        for ($offset = 0; $offset < max($days, 1); $offset++) {
            $day = $from->addDays($offset);
            $pairs[] = [$day->month, $day->day];

            if ($day->month === 2 && $day->day === 28 && ! $day->isLeapYear()) {
                $pairs[] = [2, 29];
            }
        }

        return $pairs;
    }

    /**
     * Move a yearly date into the given year.
     */
    private static function inYear(CarbonInterface $date, int $year): CarbonInterface
    {
        $date = $date->toImmutable();
        $day = $date->month === 2 && $date->day === 29 && ! $date->setDate($year, 1, 1)->isLeapYear() ? 28 : $date->day;

        return $date->setDate($year, $date->month, $day)->startOfDay();
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
            ->useLogName('people');
    }
}
