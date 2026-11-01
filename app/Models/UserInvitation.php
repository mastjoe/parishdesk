<?php

namespace App\Models;

use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property list<string>|null $roles
 * @property list<string>|null $permissions
 * @property int|null $invited_by
 * @property int|null $user_id
 * @property int|null $member_id
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member|null $member
 */
#[Fillable(['name', 'email', 'roles', 'permissions', 'invited_by', 'user_id', 'member_id', 'expires_at', 'accepted_at', 'revoked_at'])]
class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory;

    /**
     * How long an invitation link stays valid.
     */
    public const int VALID_FOR_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Get the user who sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the member the invitation links the new account to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Determine whether the invitation is for a member's portal account
     * rather than for a staff user.
     */
    public function isForMember(): bool
    {
        return $this->member_id !== null;
    }

    /**
     * Scope the query to invitations that can still be accepted.
     *
     * @param  Builder<UserInvitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * Determine whether the invitation can still be accepted.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * Get the human readable status of the invitation.
     */
    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    /**
     * Get the signed link the invitee uses to set their password.
     */
    public function acceptUrl(): string
    {
        return URL::temporarySignedRoute('invitations.show', $this->expires_at, ['invitation' => $this->id]);
    }
}
