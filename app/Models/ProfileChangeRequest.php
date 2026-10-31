<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use BackedEnum;
use Carbon\CarbonInterface;
use Database\Factories\ProfileChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Changes a member asked for from the portal, waiting for staff review.
 *
 * @property int $id
 * @property int $member_id
 * @property int|null $submitted_by
 * @property array<string, string|null> $changes
 * @property array<string, string|null> $original
 * @property string|null $note
 * @property ChangeRequestStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member $member
 */
#[Fillable(['member_id', 'submitted_by', 'changes', 'original', 'note', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class ProfileChangeRequest extends Model
{
    /** @use HasFactory<ProfileChangeRequestFactory> */
    use HasFactory;

    /**
     * The member fields a member may ask to change from the portal.
     */
    public const array FIELDS = [
        'title', 'first_name', 'middle_name', 'last_name', 'baptismal_name', 'date_of_birth',
        'marital_status', 'wedding_date', 'phone', 'alt_phone', 'whatsapp_phone', 'email', 'address', 'city',
        'state', 'occupation', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'original' => 'array',
            'status' => ChangeRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the member's current values for the fields that can be requested,
     * as the strings a form would submit.
     *
     * @return array<string, string|null>
     */
    public static function currentValues(Member $member): array
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $value = $member->getAttribute($field);

            $values[$field] = match (true) {
                $value instanceof BackedEnum => (string) $value->value,
                $value instanceof CarbonInterface => $value->toDateString(),
                $value === null || $value === '' => null,
                default => (string) $value,
            };
        }

        return $values;
    }

    /**
     * Get the member the changes apply to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the user who submitted the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who reviewed the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope the query to requests waiting for review.
     *
     * @param  Builder<ProfileChangeRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ChangeRequestStatus::Pending);
    }

    /**
     * Determine whether the request is still waiting for review.
     */
    public function isPending(): bool
    {
        return $this->status === ChangeRequestStatus::Pending;
    }
}
